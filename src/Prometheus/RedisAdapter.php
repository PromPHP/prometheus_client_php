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

    const PROMETHEUS_LABEL_VALUES_SUFFIX = '_labelValues';

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
            $this->redis->hSet(self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX, serialize($sample['labelValues']), $sample['value']);
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

    public function deleteMetrics()
    {
        $this->openConnection();

        $keys = $this->redis->sMembers(self::PROMETHEUS_GAUGE_KEYS);
        foreach ($keys as $key) {
            $this->redis->delete(self::PROMETHEUS_GAUGES . $key);
            $this->redis->delete(self::PROMETHEUS_GAUGES . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX);
        }
        $this->redis->del(self::PROMETHEUS_GAUGE_KEYS);

        $keys = $this->redis->sMembers(self::PROMETHEUS_COUNTER_KEYS);
        foreach ($keys as $key) {
            $this->redis->delete(self::PROMETHEUS_COUNTERS . $key);
            $this->redis->delete(self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX);
        }
        $this->redis->del(self::PROMETHEUS_COUNTER_KEYS);

        $keys = $this->redis->sMembers(self::PROMETHEUS_HISTOGRAMS_KEYS);
        foreach ($keys as $key) {
            $this->redis->delete(self::PROMETHEUS_HISTOGRAMS . $key);
            $this->redis->delete(self::PROMETHEUS_HISTOGRAMS . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX);
        }
        $this->redis->del(self::PROMETHEUS_HISTOGRAMS_KEYS);
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
                self::PROMETHEUS_COUNTERS . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX,
                serialize($sample['labelValues']), $sample['value']
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
        $this->openConnection();
        $keys = $this->redis->sMembers(self::PROMETHEUS_HISTOGRAMS_KEYS);
        $gauges = array();
        foreach ($keys as $key) {
            $sample = array();
            $sample['value'] = $this->redis->hGet(self::PROMETHEUS_HISTOGRAMS . $key, 'value');
            $sample['labels'] = unserialize($this->redis->hGet(self::PROMETHEUS_HISTOGRAMS . $key, 'labels'));
            $sample['name'] = $this->redis->hGet(self::PROMETHEUS_HISTOGRAMS . $key, 'name');
            $sample['help'] = $this->redis->hGet(self::PROMETHEUS_HISTOGRAMS . $key, 'help');
            $sample['type'] = $this->redis->hGet(self::PROMETHEUS_HISTOGRAMS . $key, 'type');
            $gauges[] = $sample;
        }
        return $gauges;
    }

    public function storeHistogram(Histogram $histogram)
    {
        $this->openConnection();
        $key = sha1($histogram['name'] . '_' . serialize($histogram['labels']));
        $this->redis->sAdd(self::PROMETHEUS_HISTOGRAMS_KEYS, $key);
        $this->redis->hIncrBy(self::PROMETHEUS_HISTOGRAMS . $key, 'value', $histogram['value']);
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'labels', serialize($histogram['labels']));
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'name', $histogram['name']);
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'help', $histogram['help']);
        $this->redis->hSet(self::PROMETHEUS_HISTOGRAMS . $key, 'type', $histogram['type']);
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
            $labelNames = unserialize($redisGauge['labelNames']);
            $redisLabelValues = $this->redis->hGetAll($typePrefix . $key . self::PROMETHEUS_LABEL_VALUES_SUFFIX);
            $listOfLabelValues = array_map(function ($value) {
                return unserialize($value);
            }, array_keys($redisLabelValues));
            $values = array_values($redisLabelValues);
            foreach ($listOfLabelValues as $i => $labelValues) {
                $gauge['samples'][] = array(
                    'name' => $gauge['name'],
                    'labels' => array_combine($labelNames, $labelValues),
                    'value' => $values[$i]
                );

            }
            $gauges[] = $gauge;
        }
        return $gauges;
    }
}
