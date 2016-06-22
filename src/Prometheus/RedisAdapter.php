<?php

namespace Prometheus;


class RedisAdapter
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_GAUGE_KEYS = 'PROMETHEUS_GAUGE_KEYS';
    const PROMETHEUS_COUNTERS = 'PROMETHEUS_COUNTERS_';
    const PROMETHEUS_COUNTER_KEYS = 'PROMETHEUS_COUNTER_KEYS';
    const PROMETHEUS_HISTOGRAM_KEYS = 'PROMETHEUS_HISTOGRAM_KEYS';
    const PROMETHEUS_HISTOGRAMS = 'PROMETHEUS_HISTOGRAMS_';
    const PROMETHEUS_METRICS_COUNTER = 'PROMETHEUS_METRICS_COUNTER';
    const PROMETHEUS_METRICS_SAMPLE_COUNTER = 'PROMETHEUS_METRICS_SAMPLE_COUNTER';

    const PROMETHEUS_SAMPLE_VALUE_SUFFIX = '_value';
    const PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX = '_labelNames';
    const PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX = '_labelValues';
    const PROMETHEUS_SAMPLE_NAME_SUFFIX = '_name';
    const PROMETHEUS_SAMPLE_KEYS_SUFFIX = '_sampleKeys';

    private $hostname;
    private $redis;

    public function __construct($hostname)
    {
        $this->hostname = $hostname;
        $this->redis = new \Redis();
    }

    public function flushRedis()
    {
        $this->openConnection();
        $this->redis->flushAll();
    }

    public function storeGauge(Gauge $gauge)
    {
        $this->storeMetricByType($gauge, 'hSet', self::PROMETHEUS_GAUGE_KEYS, self::PROMETHEUS_GAUGES);
    }

    public function fetchGauges()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_GAUGE_KEYS, self::PROMETHEUS_GAUGES);
    }

    public function storeCounter(Counter $counter)
    {
        $this->storeMetricByType($counter, 'hIncrBy', self::PROMETHEUS_COUNTER_KEYS, self::PROMETHEUS_COUNTERS);
    }

    public function fetchCounters()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_COUNTER_KEYS, self::PROMETHEUS_COUNTERS);
    }

    public function storeHistogram(Histogram $histogram)
    {
        $this->storeMetricByType($histogram, 'hIncrByFloat', self::PROMETHEUS_HISTOGRAM_KEYS, self::PROMETHEUS_HISTOGRAMS);
    }

    public function fetchHistograms()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_HISTOGRAM_KEYS, self::PROMETHEUS_HISTOGRAMS);
    }

    /**
     * @param string $typeKeysPrefix
     * @param string $typePrefix
     * @return array
     */
    private function fetchMetricsByType($typeKeysPrefix, $typePrefix)
    {
        $this->openConnection();
        $keys = $this->redis->zRange($typeKeysPrefix, 0, -1);
        $metrics = array();
        foreach ($keys as $key) {
            $redisGauge = $this->redis->hGetAll($typePrefix . $key);
            $metric = array(
                'name' => $redisGauge['name'],
                'help' => $redisGauge['help'],
                'type' => $redisGauge['type'],
                'samples' => array()
            );

            // Fill samples
            $values = $this->redis->hGetAll($typePrefix . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX);
            $labelValuesList = $this->redis->hGetAll($typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX);
            $sampleKeys = $this->redis->zRange($typePrefix  . $key . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX, 0, -1);
            foreach ($sampleKeys as $sampleKey) {
                $labelNames = unserialize(
                    $this->redis->hGet($typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX, $sampleKey)
                );
                $name = $this->redis->hGet($typePrefix . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX, $sampleKey);
                $metric['samples'][] = array(
                    'name' => $name,
                    'labels' => array_combine($labelNames, unserialize($labelValuesList[$sampleKey])),
                    'value' => $values[$sampleKey]
                );

            }
            $metrics[] = $metric;
        }
        return array_reverse($metrics);
    }

    /**
     * @param Metric $metric
     * @param string $storeValueCommand
     * @param string $typeKeysPrefix
     * @param string $typePrefix
     */
    private function storeMetricByType(Metric $metric, $storeValueCommand, $typeKeysPrefix, $typePrefix)
    {
        $this->openConnection();
        $key = sha1($metric->getFullName() . '_' . implode('_', $metric->getLabelNames()));
        foreach ($metric->getSamples() as $sample) {
            $sampleKey = $sample->getKey();
            $this->redis->$storeValueCommand(
                $typePrefix . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sampleKey,
                $sample->getValue()
            );
            $this->redis->hSet(
                $typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sampleKey,
                serialize($sample->getLabelValues())
            );
            $this->redis->hSet(
                $typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sampleKey,
                serialize($sample->getLabelNames())
            );
            $this->redis->hSet(
                $typePrefix . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sampleKey,
                $sample->getName()
            );
            $this->storeNewMetricSampleKey($typePrefix, $key, $sampleKey);
        }
        $this->redis->hSet($typePrefix . $key, 'name', $metric->getFullName());
        $this->redis->hSet($typePrefix . $key, 'help', $metric->getHelp());
        $this->redis->hSet($typePrefix . $key, 'type', $metric->getType());
        $this->redis->hSet($typePrefix . $key, 'labelNames', serialize($metric->getLabelNames()));
        $this->storeNewMetricKey($typeKeysPrefix, $key);
    }

    /**
     * @param string $typePrefix
     * @param string $key
     * @param string $sampleKey
     */
    private function storeNewMetricSampleKey($typePrefix, $key, $sampleKey)
    {
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_METRICS_SAMPLE_COUNTER);
        $this->redis->zAdd($typePrefix . $key . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX, $currentMetricCounter, $sampleKey);
    }

    /**
     * @param string $typePrefix
     * @param string $key
     */
    private function storeNewMetricKey($typePrefix, $key)
    {
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_METRICS_COUNTER);
        $this->redis->zAdd($typePrefix, $currentMetricCounter, $key);
    }

    private function openConnection()
    {
        $this->redis->connect($this->hostname);
    }
}
