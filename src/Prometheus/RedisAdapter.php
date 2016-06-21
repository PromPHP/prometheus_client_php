<?php

namespace Prometheus;


class RedisAdapter
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_GAUGE_KEYS = 'PROMETHEUS_GAUGE_KEYS';
    const PROMETHEUS_COUNTERS = 'PROMETHEUS_COUNTERS_';
    const PROMETHEUS_COUNTER_KEYS = 'PROMETHEUS_COUNTER_KEYS';
    const PROMETHEUS_HISTOGRAMS_KEYS = 'PROMETHEUS_HISTOGRAM_KEYS';
    const PROMETHEUS_HISTOGRAMS = 'PROMETHEUS_HISTOGRAMS_';
    const PROMETHEUS_METRICS_COUNTER = 'PROMETHEUS_METRICS_COUNTER';

    private $hostname;
    private $redis;

    const PROMETHEUS_SAMPLE_VALUE_SUFFIX = '_value';
    const PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX = '_labelNames';
    const PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX = '_labelValues';
    const PROMETHEUS_SAMPLE_NAME_SUFFIX = '_name';


    public function __construct($hostname)
    {
        $this->hostname = $hostname;
        $this->redis = new \Redis();
    }

    public function storeGauge(Gauge $gauge)
    {
        $this->openConnection();
        $key = sha1($gauge->getFullName() . '_' . implode('_', $gauge->getLabelNames()));
        $sampleKeys = array();
        foreach ($gauge->getSamples() as $sample) {
            $sampleKey = $sample['name'] . serialize($sample['labelValues']);
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sampleKey,
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sampleKey,
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sampleKey,
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sampleKey,
                $sample['name']
            );
            $sampleKeys[] = $sampleKey;
        }
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'name', $gauge->getFullName());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'help', $gauge->getHelp());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'type', $gauge->getType());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'labelNames', serialize($gauge->getLabelNames()));
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'sampleKeys', serialize($sampleKeys));
        $this->storeNewMetricKey(self::PROMETHEUS_GAUGE_KEYS, $key);
    }

    public function fetchGauges()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_GAUGE_KEYS, self::PROMETHEUS_GAUGES);
    }

    public function flushRedis()
    {
        $this->openConnection();

        $this->redis->flushAll();
    }

    private function openConnection()
    {
        $this->redis->connect($this->hostname);
    }

    public function storeCounter(Counter $counter)
    {
        $this->openConnection();
        $key = sha1($counter->getFullName() . '_' . implode('_', $counter->getLabelNames()));
        $sampleKeys = array();
        foreach ($counter->getSamples() as $sample) {
            $sampleKey = $sample['name'] . serialize($sample['labelValues']);
            $this->redis->hIncrBy(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sampleKey,
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sampleKey,
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sampleKey,
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sampleKey,
                $sample['name']
            );
            $sampleKeys[] = $sampleKey;
        }
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'name', $counter->getFullName());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'help', $counter->getHelp());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'type', $counter->getType());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'labelNames', serialize($counter->getLabelNames()));
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'sampleKeys', serialize($sampleKeys));
        $this->storeNewMetricKey(self::PROMETHEUS_COUNTER_KEYS, $key);
    }

    public function fetchCounters()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_COUNTER_KEYS, self::PROMETHEUS_COUNTERS);
    }

    public function fetchHistograms()
    {
        return $this->fetchMetricsByType(self::PROMETHEUS_HISTOGRAMS_KEYS, self::PROMETHEUS_HISTOGRAMS);
    }

    public function storeHistogram(Histogram $histogram)
    {
        $this->openConnection();
        $key = sha1($histogram->getFullName() . '_' . implode('_', $histogram->getLabelNames()));
        $sampleKeys = array();
        foreach ($histogram->getSamples() as $sample) {
            $sampleKey = $sample['name'] . serialize($sample['labelValues']);
            $this->redis->hIncrBy(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sampleKey,
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sampleKey,
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sampleKey,
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sampleKey,
                $sample['name']
            );
            $sampleKeys[] = $sampleKey;
        }
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'name', $histogram->getFullName());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'help', $histogram->getHelp());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'type', $histogram->getType());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'labelNames', serialize($histogram->getLabelNames()));
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'sampleKeys', serialize($sampleKeys));
        $this->storeNewMetricKey(self::PROMETHEUS_HISTOGRAMS_KEYS, $key);
    }

    /**
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
            $sampleKeys = unserialize($redisGauge['sampleKeys']);
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
     * @param string $typePrefix
     * @param string $key
     */
    private function storeNewMetricKey($typePrefix, $key)
    {
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_METRICS_COUNTER);
        $this->redis->zAdd($typePrefix, $currentMetricCounter, $key);
    }
}
