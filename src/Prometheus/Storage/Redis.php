<?php

namespace Prometheus\Storage;


use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;

class Redis implements Adapter
{
    const PROMETHEUS_PREFIX = 'PROMETHEUS_';
    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';

    private static $defaultOptions = array();

    private $options;
    private $redis;

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
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
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

    public function updateHistogram(array $data)
    {
        $this->openConnection();
        $bucketToIncrease = 'le_+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = 'le_' . $bucket;
                break;
            }
        }
        $metaData = $data;
        unset($metaData['value']);
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
                implode('', array_merge(array($data['name']), $data['labelValues'])),
                $bucketToIncrease,
                self::PROMETHEUS_PREFIX . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $data['value'],
                serialize($metaData),
            ),
            3
        );
    }

    public function updateGauge(array $data)
    {
        $this->openConnection();
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['command']);
        $this->redis->eval(<<<LUA
local result = redis.call(KEYS[2], KEYS[1], 'value', ARGV[1])

if KEYS[2] == 'hSet' then
    if result == 1 then
        redis.call('hMSet', KEYS[1], 'metaData', ARGV[2])
        redis.call('sAdd', KEYS[3], KEYS[1])
    end
else
    if result == ARGV[1] then
        redis.call('hMSet', KEYS[1], 'metaData', ARGV[2])
        redis.call('sAdd', KEYS[3], KEYS[1])
    end
end
LUA
            ,
            array(
                implode('', array_merge(array($data['name']), $data['labelValues'])),
                $this->getRedisCommand($data['command']),
                self::PROMETHEUS_PREFIX . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
                $data['value'],
                serialize($metaData),
            ),
            3
        );
    }

    public function updateCounter(array $data)
    {
        $this->openConnection();
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['command']);
        $args = array(
            implode('', array_merge(array($data['name']), $data['labelValues'])),
            $this->getRedisCommand($data['command']),
            self::PROMETHEUS_PREFIX . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
            $data['value'],
            serialize($metaData),
        );
        $result = $this->redis->eval(<<<LUA
local result = redis.call(KEYS[2], KEYS[1], 'value', ARGV[1])
if result == tonumber(ARGV[1]) then
    redis.call('hMSet', KEYS[1], 'metaData', ARGV[2])
    redis.call('sAdd', KEYS[3], KEYS[1])
end
return result
LUA
            ,
            $args,
            3
        );
        return $result;
    }

    private function collectHistograms()
    {
        $keys = $this->redis->sMembers(self::PROMETHEUS_PREFIX . Histogram::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        $histograms = array();
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll($key);
            $histogram = unserialize($raw['metaData']);

            $histogram['samples'] = array();

            // Fill up all buckets.
            // If the bucket doesn't exist fill in values from
            // the previous one.
            $acc = 0;
            $histogram['buckets'][] = '+Inf';
            foreach ($histogram['buckets'] as $bucket) {
                $bucketKey = 'le_' . $bucket;
                if (!isset($raw[$bucketKey])) {
                    $histogram['samples'][] = array(
                       'name' => $histogram['name'] . '_bucket',
                       'labelNames' => array('le'),
                       'labelValues' => array_merge($histogram['labelValues'], array($bucket)),
                       'value' => $acc
                   );
                } else {
                    $acc += $raw[$bucketKey];
                    $histogram['samples'][] = array(
                        'name' => $histogram['name'] . '_bucket',
                        'labelNames' => array('le'),
                        'labelValues' => array_merge($histogram['labelValues'], array($bucket)),
                        'value' => $acc
                    );
                }

            }

            // Add the count
            $histogram['samples'][] = array(
                'name' => $histogram['name'] . '_count',
                'labelNames' => array(),
                'labelValues' => $histogram['labelValues'],
                'value' => $acc
            );

            // Add the sum
            $histogram['samples'][] = array(
                'name' => $histogram['name'] . '_sum',
                'labelNames' => array(),
                'labelValues' => $histogram['labelValues'],
                'value' => $raw['sum']
            );

            $histograms[] = $histogram;
        }

        // group metrics by name
        $groupedHistograms = array();
        foreach ($histograms as $histogram) {
            $groupingKey = $histogram['name'] . serialize($histogram['labelNames']);
            if (!isset($groupedHistograms[$groupingKey])) {
                $groupedHistograms[$groupingKey] = array(
                    'name' => $histogram['name'],
                    'type' => $histogram['type'],
                    'help' => $histogram['help'],
                    'labelNames' => $histogram['labelNames'],
                    'samples' => array(),
                );
            }
            $groupedHistograms[$groupingKey]['samples'] = array_merge(
                $groupedHistograms[$groupingKey]['samples'],
                $histogram['samples']
            );
        }

        return array_values($groupedHistograms);
    }

    private function collectGauges()
    {
        $keys = $this->redis->sMembers(self::PROMETHEUS_PREFIX . Gauge::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        $gauges = array();
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll($key);
            $gauge = unserialize($raw['metaData']);
            $gauge['value'] = $raw['value'];
            $gauges[] = $gauge;
        }

        // group metrics by name
        $groupedGauges = array();
        foreach ($gauges as $gauge) {
            $groupingKey = $gauge['name'] . serialize($gauge['labelNames']);
            if (!isset($groupedGauges[$groupingKey])) {
                $groupedGauges[$groupingKey] = array(
                    'name' => $gauge['name'],
                    'type' => $gauge['type'],
                    'help' => $gauge['help'],
                    'labelNames' => $gauge['labelNames'],
                    'samples' => array(),
                );
            }
            $groupedGauges[$groupingKey]['samples'][] = array(
                'name' => $gauge['name'],
                'labelNames' => array(),
                'labelValues' => $gauge['labelValues'],
                'value' => $gauge['value'],
            );
        }

        return array_values($groupedGauges);
    }

    private function collectCounters()
    {
        $keys = $this->redis->sMembers(self::PROMETHEUS_PREFIX . Counter::TYPE . self::PROMETHEUS_METRIC_KEYS_SUFFIX);
        $counters = array();
        foreach ($keys as $key) {
            $raw = $this->redis->hGetAll($key);
            $counter = unserialize($raw['metaData']);
            $counter['value'] = $raw['value'];
            $counters[] = $counter;
        }

        // group metrics by name
        $groupedCounters = array();
        foreach ($counters as $counter) {
            $groupingKey = $counter['name'] . serialize($counter['labelNames']);
            if (!isset($groupedCounters[$groupingKey])) {
                $groupedCounters[$groupingKey] = array(
                    'name' => $counter['name'],
                    'type' => $counter['type'],
                    'help' => $counter['help'],
                    'labelNames' => $counter['labelNames'],
                    'samples' => array(),
                );
            }
            $groupedCounters[$groupingKey]['samples'][] = array(
                'name' => $counter['name'],
                'labelNames' => array(),
                'labelValues' => $counter['labelValues'],
                'value' => $counter['value'],
            );
        }

        return array_values($groupedCounters);
    }

    private function getRedisCommand($cmd)
    {
        switch ($cmd) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                return 'hIncrBy';
            case Adapter::COMMAND_INCREMENT_FLOAT:
                return 'hIncrByFloat';
            case Adapter::COMMAND_SET:
                return 'hSet';
            default:
                throw new \InvalidArgumentException("Unknown command");
        }
    }

}
