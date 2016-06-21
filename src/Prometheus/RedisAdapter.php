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
        foreach ($gauge->getSamples() as $sample) {
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                serialize($sample['labelValues']),
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                serialize($sample['labelValues']),
                $sample['name']
            );
        }
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'name', $gauge->getFullName());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'help', $gauge->getHelp());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'type', $gauge->getType());
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'labelNames', serialize($gauge->getLabelNames()));
        $this->redis->sAdd(self::PROMETHEUS_GAUGE_KEYS, $key);
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
        foreach ($counter->getSamples() as $sample) {
            $this->redis->hIncrBy(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                serialize($sample['labelValues']),
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                serialize($sample['labelValues']),
                $sample['name']
            );
        }
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'name', $counter->getFullName());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'help', $counter->getHelp());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'type', $counter->getType());
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'labelNames', serialize($counter->getLabelNames()));
        $this->redis->sAdd(self::PROMETHEUS_COUNTER_KEYS, $key);
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
        foreach ($histogram->getSamples() as $sample) {
            $this->redis->hIncrBy(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                serialize($sample['labelValues']),
                $sample['value']
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelValues'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                serialize($sample['labelValues']),
                serialize($sample['labelNames'])
            );
            $this->redis->hSet(
                self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                serialize($sample['labelValues']),
                $sample['name']
            );
        }
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'name', $histogram->getFullName());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'help', $histogram->getHelp());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'type', $histogram->getType());
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'labelNames', serialize($histogram->getLabelNames()));
        $this->redis->sAdd(self::PROMETHEUS_HISTOGRAMS_KEYS, $key);
    }

    /**
     * @return array
     */
    private function fetchMetricsByType($typeKeysPrefix, $typePrefix)
    {
        $this->openConnection();
        $keys = $this->redis->sMembers($typeKeysPrefix);
        $gauges = array();
        foreach ($keys as $key) {
            $redisGauge = $this->redis->hGetAll($typePrefix . $key);
            $gauge = array(
                'name' => $redisGauge['name'],
                'help' => $redisGauge['help'],
                'type' => $redisGauge['type'],
                'samples' => array()
            );

            // Fill samples
            $values = $this->redis->hGetAll($typePrefix . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX);
            $listOfLabelValues = array_map(
                function ($labelValue) {return unserialize($labelValue);},
                $this->redis->hGetAll($typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX)
            );
            foreach ($listOfLabelValues as $sampleKey => $labelValues) {
                $labelNames = unserialize(
                    $this->redis->hGet($typePrefix . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX, serialize($labelValues))
                );
                $name = $this->redis->hGet($typePrefix . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX, serialize($labelValues));
                $gauge['samples'][] = array(
                    'name' => $name,
                    'labels' => array_combine($labelNames, $labelValues),
                    'value' => $values[$sampleKey]
                );

            }
            $gauges[] = $gauge;
        }
        return array_reverse($gauges);
    }
}
