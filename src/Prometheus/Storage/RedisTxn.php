<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use InvalidArgumentException;
use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\RedisTxn\Metadata;
use Prometheus\Storage\RedisTxn\Metric;
use Prometheus\Summary;
use RedisException;
use RuntimeException;
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
     * @param mixed[] $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    /**
     * @param string $prefix
     */
    public static function setPrefix(string $prefix): void
    {
        self::$prefix = $prefix;
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
     * @param mixed[] $data
     *
     * @return string
     */
    private function metaKey(array $data): string
    {
        return implode(':', [
            $data['name'],
            'meta'
        ]);
    }

    /**
     * @param mixed[] $data
     *
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value'
        ]);
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect(): array
    {
        $this->ensureOpenConnection();
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        $metrics = array_merge($metrics, $this->collectSummaries());
        return array_map(
            function (array $metric): MetricFamilySamples {
                return new MetricFamilySamples($metric);
            },
            $metrics
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
     * @param array $data
     * @return void
     * @throws StorageException
     * @throws RedisException
     */
    public function updateSummary(array $data): void
    {
        $this->ensureOpenConnection();

        // Prepare summary metadata
        $metaHashKey = self::$prefix . self::PROMETHEUS_METRIC_META_SUFFIX;
        $summaryMetadata = $this->toMetadata($data);
        $ttl = $summaryMetadata->getMaxAgeSeconds();

        // Create summary key
        $keyPrefix = self::$prefix . Summary::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        $summaryKey = implode(':', [$keyPrefix, $data['name'], $summaryMetadata->getLabelValuesEncoded()]);
        $summaryRegistryKey = implode(':', [$keyPrefix, 'keys']);

        // Get summary sample
        //
        // NOTE: When we persist a summary metric sample into Redis, we write it into a Redis sorted set.
        // We append the current time in microseconds as a suffix on the observed value to make each observed value
        // durable and unique in the sorted set in accordance with best-practice guidelines described in the article,
        // "Redis Best Practices: Sorted Set Time Series" [1].
        //
        // See MetricBuilder::processSamples() for the complementary part of this operation.
        //
        // [1] https://redis.com/redis-best-practices/time-series/sorted-set-time-series/
        $value = implode(':', [$data['value'], microtime(true)]);
        $currentTime = time();

        // Commit the observed metric value
        $this->redis->eval(<<<LUA
-- Parse script input
local summaryRegistryKey = KEYS[1]
local metaHashKey = KEYS[2]
local summaryKey = KEYS[3]
local summaryMetadata = ARGV[1]
local summaryValue = ARGV[2]
local currentTime = ARGV[3]
local ttlFieldValue = ARGV[4]

-- NOTE: We must construct this key on the Redis server to account for cases where 
-- a global key prefix has been configured on a Redis client. If we construct this
-- key in the application, we will inadvertently omit any configured global key prefix.
local ttlFieldName = summaryKey .. ":ttl"

-- Persist the observed metric value
redis.call('sadd', summaryRegistryKey, summaryKey)
redis.call('zadd', summaryKey, currentTime, summaryValue)
redis.call('hset', metaHashKey, summaryKey, summaryMetadata)
redis.call('hset', metaHashKey, ttlFieldName, ttlFieldValue)
LUA
            ,
            [
                $summaryRegistryKey,
                $metaHashKey,
                $summaryKey,
                $summaryMetadata->toJson(),
                $value,
                $currentTime,
                $ttl,
            ],
            3
        );
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateGauge(array $data): void
    {
        $this->ensureOpenConnection();
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues'], $metaData['command']);
        $this->redis->eval(
            <<<LUA
local result = redis.call(ARGV[1], KEYS[1], ARGV[2], ARGV[3])

if ARGV[1] == 'hSet' then
    if result == 1 then
        redis.call('hSet', KEYS[1], '__meta', ARGV[4])
        redis.call('sAdd', KEYS[2], KEYS[1])
    end
else
    if result == ARGV[3] then
        redis.call('hSet', KEYS[1], '__meta', ARGV[4])
        redis.call('sAdd', KEYS[2], KEYS[1])
    end
end
LUA
            ,
            [
                $this->toMetricKey($data),
                self::$prefix . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $this->getRedisCommand($data['command']),
                json_encode($data['labelValues']),
                $data['value'],
                json_encode($metaData),
            ],
            2
        );
    }

    /**
     * @param mixed[] $data
     * @throws StorageException
     */
    public function updateCounter(array $data): void
    {
        $this->ensureOpenConnection();
        $metaData = $data;
        unset($metaData['value'], $metaData['labelValues'], $metaData['command']);
        $this->redis->eval(
            <<<LUA
local result = redis.call(ARGV[1], KEYS[1], ARGV[3], ARGV[2])
local added = redis.call('sAdd', KEYS[2], KEYS[1])
if added == 1 then
    redis.call('hMSet', KEYS[1], '__meta', ARGV[4])
end
return result
LUA
            ,
            [
                $this->toMetricKey($data),
                self::$prefix . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $this->getRedisCommand($data['command']),
                $data['value'],
                json_encode($data['labelValues']),
                json_encode($metaData),
            ],
            2
        );
    }


    /**
     * @param mixed[] $data
     * @return Metadata
     */
    private function toMetadata(array $data): Metadata
    {
        return Metadata::newBuilder()
            ->withName($data['name'])
            ->withHelp($data['help'])
            ->withLabelNames($data['labelNames'])
            ->withLabelValues($data['labelValues'])
            ->withQuantiles($data['quantiles'])
            ->withMaxAgeSeconds($data['maxAgeSeconds'])
            ->build();
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
     * @param string $key
     *
     * @return string
     */
    private function removePrefixFromKey(string $key): string
    {
        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        if ($this->redis->getOption(\Redis::OPT_PREFIX) === null) {
            return $key;
        }
        // @phpstan-ignore-next-line false positive, phpstan thinks getOptions returns int
        return substr($key, strlen($this->redis->getOption(\Redis::OPT_PREFIX)));
    }

    /**
     * @return array
     */
    private function collectSummaries(): array
    {
        // Register summary key
        $keyPrefix = self::$prefix . Summary::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        $summaryRegistryKey = implode(':', [$keyPrefix, 'keys']);
        $metaHashKey = self::$prefix . self::PROMETHEUS_METRIC_META_SUFFIX;
        $currentTime = time();

        $result = $this->redis->eval(<<<LUA
-- Parse script input
local summaryRegistryKey = KEYS[1]
local metaHashKey = KEYS[2]
local currentTime = tonumber(ARGV[1])
local result = {}

-- Process each registered summary metric
local summaryKeys = redis.call('smembers', summaryRegistryKey)
for i, summaryKey in ipairs(summaryKeys) do
    -- Get metric sample TTL
    local ttlFieldName = summaryKey .. ":ttl"
    redis.call('set', 'foo', ttlFieldName)
    local summaryTtl = redis.call("hget", metaHashKey, ttlFieldName)
    if summaryTtl ~= nil then
        summaryTtl = tonumber(summaryTtl)
    end
    
    -- Remove TTL'd metric samples
    local startScore = "-inf"
    if summaryTtl ~= nil and currentTime ~= nil and summaryTtl > 0 and summaryTtl < currentTime then
        local startScore = currentTime - summaryTtl
	    redis.call("zremrangebyscore", summaryKey, "-inf", startScore)
    end
    
    -- Retrieve the set of remaining metric samples
    local numSamples = redis.call('zcard', summaryKey)
	local summaryMetadata = {}
	local summarySamples = {}
    if numSamples > 0 then
        -- Configure results
        summaryMetadata = redis.call("hget", metaHashKey, summaryKey)
        summarySamples = redis.call("zrange", summaryKey, startScore, "+inf", "byscore")
    else            
        -- Remove the metric's associated metadata if there are no associated samples remaining
        redis.call('srem', summaryRegistryKey, summaryKey)    
        redis.call('hdel', metaHashKey, summaryKey)
        redis.call('hdel', metaHashKey, ttlFieldName)
    end 
	
    -- Add the processed metric to the set of results
	result[summaryKey] = {}
	result[summaryKey]["metadata"] = summaryMetadata
	result[summaryKey]["samples"] = summarySamples
end

-- Return the set of summary metrics
return cjson.encode(result)
LUA
            ,
            [
                $summaryRegistryKey,
                $metaHashKey,
                $currentTime,
            ],
            2
        );

        // Format summary metrics and hand them off to the calling collector
        $summaries = [];
        $redisSummaries = json_decode($result, true);
        foreach ($redisSummaries as $summary) {
            $serializedSummary = Metric::newBuilder()
                ->withMetadata($summary['metadata'])
                ->withSamples($summary['samples'])
                ->build()
                ->toArray();
            $summaries[] = $serializedSummary;
        }
        return $summaries;
    }

    /**
     * @return mixed[]
     */
    private function collectGauges(): array
    {
        $keys = $this->redis->sMembers(self::$prefix . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $gauges = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $gauge = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $gauge['samples'] = [];
            foreach ($raw as $k => $value) {
                $gauge['samples'][] = [
                    'name' => $gauge['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($gauge['samples'], function ($a, $b): int {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $gauges[] = $gauge;
        }
        return $gauges;
    }

    /**
     * @return mixed[]
     */
    private function collectCounters(): array
    {
        $keys = $this->redis->sMembers(self::$prefix . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        sort($keys);
        $counters = [];
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll(str_replace($this->redis->_prefix(''), '', $key));
            $counter = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $counter['samples'] = [];
            foreach ($raw as $k => $value) {
                $counter['samples'][] = [
                    'name' => $counter['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($k, true),
                    'value' => $value,
                ];
            }
            usort($counter['samples'], function ($a, $b): int {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $counters[] = $counter;
        }
        return $counters;
    }

    /**
     * @param int $cmd
     * @return string
     */
    private function getRedisCommand(int $cmd): string
    {
        switch ($cmd) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                return 'hIncrBy';
            case Adapter::COMMAND_INCREMENT_FLOAT:
                return 'hIncrByFloat';
            case Adapter::COMMAND_SET:
                return 'hSet';
            default:
                throw new InvalidArgumentException("Unknown command");
        }
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function toMetricKey(array $data): string
    {
        return implode(':', [self::$prefix, $data['type'], $data['name']]);
    }

    /**
     * @param mixed[] $values
     * @return string
     * @throws RuntimeException
     */
    private function encodeLabelValues(array $values): string
    {
        $json = json_encode($values);
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }
        return base64_encode($json);
    }

    /**
     * @param string $values
     * @return mixed[]
     * @throws RuntimeException
     */
    private function decodeLabelValues(string $values): array
    {
        $json = base64_decode($values, true);
        if (false === $json) {
            throw new RuntimeException('Cannot base64 decode label values');
        }
        $decodedValues = json_decode($json, true);
        if (false === $decodedValues) {
            throw new RuntimeException(json_last_error_msg());
        }
        return $decodedValues;
    }
}
