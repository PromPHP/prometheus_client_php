<?php

namespace Prometheus\Storage;


use Prometheus\Collector;
use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;

class Redis implements Adapter
{
    const PROMETHEUS_PREFIX = 'PROMETHEUS_';

    const PROMETHEUS_METRICS_COUNTER = 'METRICS_COUNTER';
    const PROMETHEUS_METRICS_SAMPLE_COUNTER = 'METRICS_SAMPLE_COUNTER';

    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';
    const PROMETHEUS_SAMPLE_KEYS_SUFFIX = '_SAMPLE_KEYS';

    const PROMETHEUS_SAMPLE_VALUE_KEY = '_VALUE';
    const PROMETHEUS_SAMPLE_LABEL_NAMES_KEY = '_LABEL_NAMES';
    const PROMETHEUS_SAMPLE_LABEL_VALUES_KEY = '_LABEL_VALUES';
    const PROMETHEUS_SAMPLE_NAME_KEY = '_NAME';

    private static $defaultOptions = array();

    private $options;
    private $redis;
    private $metricTypes;

    public function __construct(array $options = array())
    {
        // with php 5.3 we cannot initialize the options directly on the field definition
        // so we initialize them here for now
        if (!isset(self::$defaultOptions['host'])) {
            self::$defaultOptions['host'] = '127.0.0.1';
        }
        if (!isset(self::$defaultOptions['port'])) {
            self::$defaultOptions['port'] = 6379;
        }
        if (!isset(self::$defaultOptions['timeout'])) {
            self::$defaultOptions['timeout'] = 0.1; // in seconds
        }
        if (!isset(self::$defaultOptions['read_timeout'])) {
            self::$defaultOptions['read_timeout'] = 10; // in seconds
        }
        if (!isset(self::$defaultOptions['persistent_connections'])) {
            self::$defaultOptions['persistent_connections'] = false;
        }

        $this->options = array_merge(self::$defaultOptions, $options);
        $this->redis = new \Redis();
        $this->metricTypes = array(
            Gauge::TYPE,
            Counter::TYPE,
            Histogram::TYPE,
        );
    }

    /**
     * @param array $options
     */
    public static function setDefaultOptions(array $options)
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    public function flushRedis()
    {
        $this->openConnection();
        $this->redis->flushAll();
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect()
    {
        $this->openConnection();
        $metrics = array();
        foreach ($this->metricTypes as $metricType) {
            $metrics = array_merge($metrics, $this->fetchMetricsByType($metricType));
        }
        array_multisort($metrics);
        return array_map(
            function (array $metric) {
                return new MetricFamilySamples($metric);
            },
            $metrics
        );
    }

    /**
     * @throws StorageException
     */
    public function store($command, Collector $metric, Sample $sample)
    {
        $this->openConnection();
        $this->storeMetricFamilySample($command, $metric, $sample);
        $this->storeMetricFamilyMetadata($metric);
    }

    /**
     * @param string $metricType
     * @return array
     */
    private function fetchMetricsByType($metricType)
    {
        $keys = $this->redis->sMembers(self::PROMETHEUS_PREFIX . $metricType . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        $metrics = array();
        foreach ($keys as $key) {
            $metricKey = self::PROMETHEUS_PREFIX . $metricType . $key;
            $sampleKeys = $this->redis->sMembers($metricKey . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX);
            $sampleResponses = array();
            foreach ($sampleKeys as $sampleKey) {
                $sample = $this->redis->hGetAll($metricKey . $sampleKey);
                $sampleResponses[] = array(
                    'name' => $sample[self::PROMETHEUS_SAMPLE_NAME_KEY],
                    'labelNames' => unserialize($sample[self::PROMETHEUS_SAMPLE_LABEL_NAMES_KEY]),
                    'labelValues' => unserialize($sample[self::PROMETHEUS_SAMPLE_LABEL_VALUES_KEY]),
                    'value' => $sample[self::PROMETHEUS_SAMPLE_VALUE_KEY]
                );
            }
            array_multisort($sampleResponses);

            $metricResponse = $this->redis->hGetAll($metricKey);
            $metrics[] = array(
                'name' => $metricResponse['name'],
                'help' => $metricResponse['help'],
                'type' => $metricResponse['type'],
                'labelNames' => unserialize($metricResponse['labelNames']),
                'samples' => $sampleResponses
            );
        }
        return $metrics;
    }

    /**
     * @throws StorageException
     */
    private function openConnection()
    {
        try {
            if ($this->options['persistent_connections']) {
                @$this->redis->pconnect($this->options['host'], $this->options['port'], $this->options['timeout']);
            } else {
                @$this->redis->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
            }
            $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->options['read_timeout']);
        } catch (\RedisException $e) {
            throw new StorageException("Can't connect to Redis server", 0, $e);
        }
    }

    /**
     * @param $command
     * @param Collector $metric
     * @param Sample $sample
     */
    private function storeMetricFamilySample($command, Collector $metric, Sample $sample)
    {
        $metricKey = self::PROMETHEUS_PREFIX . $metric->getType() . $metric->getKey();
        $sampleKey = $metricKey . $sample->getKey();
        switch ($command) {
            case self::COMMAND_INCREMENT_INTEGER:
                $this->redis->hIncrBy(
                    $sampleKey,
                    self::PROMETHEUS_SAMPLE_VALUE_KEY,
                    $sample->getValue()
                );
                break;
            case self::COMMAND_INCREMENT_FLOAT:
                $this->redis->hIncrByFloat(
                    $sampleKey,
                    self::PROMETHEUS_SAMPLE_VALUE_KEY,
                    $sample->getValue()
                );
                break;
            case self::COMMAND_SET:
                $this->redis->hSet(
                    $sampleKey,
                    self::PROMETHEUS_SAMPLE_VALUE_KEY,
                    $sample->getValue()
                );
                break;
            default:
                throw new \RuntimeException('Unknown command.');
        }
        $this->redis->eval(<<<LUA
if redis.call('sadd', KEYS[2], KEYS[3]) == 1 then
  redis.call('hMset', KEYS[1], unpack(ARGV))
end
LUA
            ,
            array(
                $sampleKey,
                $metricKey . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX,
                $sample->getKey(),
                self::PROMETHEUS_SAMPLE_LABEL_VALUES_KEY,
                serialize($sample->getLabelValues()),
                self::PROMETHEUS_SAMPLE_LABEL_NAMES_KEY,
                serialize($sample->getLabelNames()),
                self::PROMETHEUS_SAMPLE_NAME_KEY,
                $sample->getName(),
            ),
            3
        );
    }

    /**
     * @param Collector $metric
     */
    private function storeMetricFamilyMetadata(Collector $metric)
    {
        $metricKey = self::PROMETHEUS_PREFIX . $metric->getType() . $metric->getKey();
        $this->redis->eval(<<<LUA
if redis.call('sadd', KEYS[2], KEYS[3]) == 1 then
  redis.call('hMset', KEYS[1], unpack(ARGV))
end
LUA
            ,
            array(
                $metricKey,
                self::PROMETHEUS_PREFIX . $metric->getType() . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $metric->getKey(),
                'name',
                $metric->getName(),
                'help',
                $metric->getHelp(),
                'type',
                $metric->getType(),
                'labelNames',
                serialize($metric->getLabelNames()),
            ),
            3
        );
    }

    public function updateHistogram($value, array $key, array $metaData)
    {
        $bucketToIncrease = null;
        foreach ($metaData['buckets'] as $bucket) {
            if ($value <= $bucket) {
                $bucketToIncrease = 'le_' . $bucket;
                break;
            }
        }
        $this->redis->eval(<<<LUA
local increment = redis.call('hIncrByFloat', KEYS[1], 'sum', ARGV[1])
redis.call('hIncrBy', KEYS[1], KEYS[2], 1)
if increment == ARGV[1] then
    redis.call('hMSet', KEYS[1], 'metaData', ARGV[2])
    redis.call('sAdd', KEYS[3], KEYS[1])
end
LUA
            ,
            array(
                implode('', $key),
                $bucketToIncrease,
                self::PROMETHEUS_PREFIX . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $value,
                serialize($metaData),
            ),
            3
        );
    }
}
