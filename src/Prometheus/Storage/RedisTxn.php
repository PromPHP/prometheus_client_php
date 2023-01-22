<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\Exception\StorageException;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\RedisTxn\Collecter\CounterCollecter;
use Prometheus\Storage\RedisTxn\Collecter\GaugeCollecter;
use Prometheus\Storage\RedisTxn\Collecter\SummaryCollecter;
use Prometheus\Storage\RedisTxn\Updater\CounterUpdater;
use Prometheus\Storage\RedisTxn\Updater\GaugeUpdater;
use Prometheus\Storage\RedisTxn\Updater\SummaryUpdater;
use function \sort;

/**
 * This is a storage adapter that persists Prometheus metrics in Redis.
 *
 * This library currently has two alternative Redis adapters:
 * - {@see \Prometheus\Storage\Redis}: Initial Redis adapter written for this library.
 * - {@see \Prometheus\Storage\RedisNg}: "Next-generation" adapter refactored to avoid use of the KEYS command to improve performance.
 *
 * While the next-generation adapter was an enormous performance improvement over the first, it still suffers from
 * performance degradation that scales significantly as the number of metrics grows. This is largely due to the fact
 * that the "collect" phase for metrics generally involves at least one network request per metric of each type.
 *
 * This adapter refactors the {@see \Prometheus\Storage\RedisNg} adapter to generally try and execute the "update" and
 * "collect" operations of each metric type within a single Redis transaction.
 *
 * @todo Only summary metrics have been refactored so far. Complete refactor for counter, gauge, and histogram metrics.
 * @todo Reimplement wipeStorage() to account for reorganized keys in Redis.
 * @todo Reimplement all Redis scripts with redis.pcall() to trap runtime errors that are ignored by redis.call().
 */
class RedisTxn implements Adapter
{
    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';

    const PROMETHEUS_METRIC_META_SUFFIX = '_METRIC_META';

    /**
     * @var mixed[]
     */
    private static $defaultOptions = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_timeout' => '10',
        'persistent_connections' => false,
        'password' => null,
    ];

    /**
     * @var string
     */
    private static $prefix = 'PROMETHEUS_';

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var boolean
     */
    private $connectionInitialized = false;

    /**
     * Redis constructor.
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$defaultOptions, $options);
        $this->redis = new \Redis();
    }

    /**
     * @param \Redis $redis
     * @return self
     * @throws StorageException
     */
    public static function fromExistingConnection(\Redis $redis): self
    {
        if ($redis->isConnected() === false) {
            throw new StorageException('Connection to Redis server not established');
        }

        $self = new self();
        $self->connectionInitialized = true;
        $self->redis = $redis;

        return $self;
    }

    /**
     * @throws StorageException
     * @deprecated use replacement method wipeStorage from Adapter interface
     */
    public function flushRedis(): void
    {
        $this->wipeStorage();
    }

    /**
     * @inheritDoc
     */
    public function wipeStorage(): void
    {
        $this->ensureOpenConnection();

        $searchPattern = "";

        $globalPrefix = $this->redis->getOption(\Redis::OPT_PREFIX);
        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        if (is_string($globalPrefix)) {
            $searchPattern .= $globalPrefix;
        }

        $searchPattern .= self::$prefix;
        $searchPattern .= '*';

        $this->redis->eval(
            <<<LUA
local cursor = "0"
repeat
    local results = redis.call('SCAN', cursor, 'MATCH', ARGV[1])
    cursor = results[1]
    for _, key in ipairs(results[2]) do
        redis.call('DEL', key)
    end
until cursor == "0"
LUA
            ,
            [$searchPattern],
            0
        );
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect(): array
    {
        // Ensure Redis connection
        $this->ensureOpenConnection();

        $metrics = $this->collectHistograms();
        $metricFamilySamples = array_map(
            function (array $metric): MetricFamilySamples {
                return new MetricFamilySamples($metric);
            },
            $metrics
        );

        // Collect all metrics
        $counters = $this->collectCounters();
        $gauges = $this->collectGauges();
        $summaries = $this->collectSummaries();
        return array_merge(
            $metricFamilySamples,
            $counters,
            $gauges,
            $summaries
        );
    }

    /**
     * @throws StorageException
     */
    private function ensureOpenConnection(): void
    {
        if ($this->connectionInitialized === true) {
            return;
        }

        $this->connectToServer();

        if ($this->options['password'] !== null) {
            $this->redis->auth($this->options['password']);
        }

        if (isset($this->options['database'])) {
            $this->redis->select($this->options['database']);
        }

        $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->options['read_timeout']);

        $this->connectionInitialized = true;
    }

    /**
     * @throws StorageException
     */
    private function connectToServer(): void
    {
        try {
            $connection_successful = false;
            if ($this->options['persistent_connections'] !== false) {
                $connection_successful = $this->redis->pconnect(
                    $this->options['host'],
                    (int)$this->options['port'],
                    (float)$this->options['timeout']
                );
            } else {
                $connection_successful = $this->redis->connect($this->options['host'], (int)$this->options['port'], (float)$this->options['timeout']);
            }
            if (!$connection_successful) {
                throw new StorageException("Can't connect to Redis server", 0);
            }
        } catch (\RedisException $e) {
            throw new StorageException("Can't connect to Redis server", 0, $e);
        }
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateHistogram(array $data): void
    {
        $this->ensureOpenConnection();
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues']);

        $this->redis->eval(
            <<<LUA
local result = redis.call('hIncrByFloat', KEYS[1], ARGV[1], ARGV[3])
redis.call('hIncrBy', KEYS[1], ARGV[2], 1)
if tonumber(result) >= tonumber(ARGV[3]) then
    redis.call('hSet', KEYS[1], '__meta', ARGV[4])
    redis.call('sAdd', KEYS[2], KEYS[1])
end
return result
LUA
            ,
            [
                $this->toMetricKey($data),
                self::$prefix . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                json_encode(['b' => 'sum', 'labelValues' => $data['labelValues']]),
                json_encode(['b' => $bucketToIncrease, 'labelValues' => $data['labelValues']]),
                $data['value'],
                json_encode($metaData),
            ],
            2
        );
    }

    /**
     * @inheritDoc
     */
    public function updateSummary(array $data): void
    {
        // Ensure Redis connection
        $this->ensureOpenConnection();

        // Update metric
        $updater = new SummaryUpdater($this->redis);
        $updater->update($data);
    }

    /**
     * @inheritDoc
     */
    public function updateGauge(array $data): void
    {
        // Ensure Redis connection
        $this->ensureOpenConnection();

        // Update metric
        $updater = new GaugeUpdater($this->redis);
        $updater->update($data);
    }

    /**
     * @inheritDoc
     */
    public function updateCounter(array $data): void
    {
        // Ensure Redis connection
        $this->ensureOpenConnection();

        // Update metric
        $updater = new CounterUpdater($this->redis);
        $updater->update($data);
    }

    /**
     * @return mixed[]
     */
    private function collectHistograms(): array
    {
        $keys = $this->redis->sMembers(self::$prefix . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $histograms = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $histogram = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $histogram['samples'] = [];

            // Add the Inf bucket so we can compute it later on
            $histogram['buckets'][] = '+Inf';

            $allLabelValues = [];
            foreach (array_keys($raw) as $k) {
                $d = json_decode($k, true);
                if ($d['b'] == 'sum') {
                    continue;
                }
                $allLabelValues[] = $d['labelValues'];
            }

            // We need set semantics.
            // This is the equivalent of array_unique but for arrays of arrays.
            $allLabelValues = array_map("unserialize", array_unique(array_map("serialize", $allLabelValues)));
            sort($allLabelValues);

            foreach ($allLabelValues as $labelValues) {
                // Fill up all buckets.
                // If the bucket doesn't exist fill in values from
                // the previous one.
                $acc = 0;
                foreach ($histogram['buckets'] as $bucket) {
                    $bucketKey = json_encode(['b' => $bucket, 'labelValues' => $labelValues]);
                    if (!isset($raw[$bucketKey])) {
                        $histogram['samples'][] = [
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($labelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $raw[$bucketKey];
                        $histogram['samples'][] = [
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($labelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $histogram['samples'][] = [
                    'name' => $histogram['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $labelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $histogram['samples'][] = [
                    'name' => $histogram['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $labelValues,
                    'value' => $raw[json_encode(['b' => 'sum', 'labelValues' => $labelValues])],
                ];
            }
            $histograms[] = $histogram;
        }
        return $histograms;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectSummaries(): array
    {
        $collector = new SummaryCollecter($this->redis);
        return $collector->getMetricFamilySamples();
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectGauges(): array
    {
        $collector = new GaugeCollecter($this->redis);
        return $collector->getMetricFamilySamples();
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectCounters(): array
    {
        $collector = new CounterCollecter($this->redis);
        return $collector->getMetricFamilySamples();
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function toMetricKey(array $data): string
    {
        return implode(':', [self::$prefix, $data['type'], $data['name']]);
    }
}
