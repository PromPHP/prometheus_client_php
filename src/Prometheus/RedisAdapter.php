<?php

namespace Prometheus;


class RedisAdapter
{
    const PROMETHEUS_PREFIX = 'PROMETHEUS_';

    const PROMETHEUS_METRICS_COUNTER = 'METRICS_COUNTER';
    const PROMETHEUS_METRICS_SAMPLE_COUNTER = 'METRICS_SAMPLE_COUNTER';

    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';
    const PROMETHEUS_SAMPLE_KEYS_SUFFIX = '_SAMPLE_KEYS';

    const PROMETHEUS_SAMPLE_VALUE_SUFFIX = '_VALUE';
    const PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX = '_LABEL_NAMES';
    const PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX = '_LABEL_VALUES';
    const PROMETHEUS_SAMPLE_NAME_SUFFIX = '_NAME';

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
        $this->storeMetricByType($gauge, 'hSet');
    }

    /**
     * @return MetricResponse[]
     */
    public function fetchGauges()
    {
        return $this->fetchMetricsByType(Gauge::TYPE);
    }

    public function storeCounter(Counter $counter)
    {
        $this->storeMetricByType($counter, 'hIncrBy');
    }

    /**
     * @return MetricResponse[]
     */
    public function fetchCounters()
    {
        return $this->fetchMetricsByType(Counter::TYPE);
    }

    public function storeHistogram(Histogram $histogram)
    {
        $this->storeMetricByType($histogram, 'hIncrByFloat');
    }

    /**
     * @return MetricResponse[]
     */
    public function fetchHistograms()
    {
        return $this->fetchMetricsByType(Histogram::TYPE);
    }

    /**
     * @param string $metricType
     * @return MetricResponse[]
     */
    private function fetchMetricsByType($metricType)
    {
        $this->openConnection();
        $keys = $this->redis->zRange(
            self::PROMETHEUS_PREFIX . $metricType . self::PROMETHEUS_METRIC_KEYS_SUFFIX, 0, -1
        );
        $metrics = array();
        foreach ($keys as $key) {
            $values = $this->redis->hGetAll(self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX);
            $labelValuesList = $this->redis->hGetAll(self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX);
            $sampleKeys = $this->redis->zRange(self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX, 0, -1);
            $samples = array();
            foreach ($sampleKeys as $sampleKey) {
                $labelNames = unserialize(
                    $this->redis->hGet(self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX, $sampleKey)
                );
                $name = $this->redis->hGet(self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX, $sampleKey);
                $samples[] = array(
                    'name' => $name,
                    'labelNames' => $labelNames,
                    'labelValues' => unserialize($labelValuesList[$sampleKey]),
                    'value' => $values[$sampleKey]
                );
            }
            $redisGauge = $this->redis->hGetAll(self::PROMETHEUS_PREFIX . $metricType . $key);
            $metrics[] = new MetricResponse(
                array(
                    'name' => $redisGauge['name'],
                    'help' => $redisGauge['help'],
                    'type' => $redisGauge['type'],
                    'samples' => $samples
                )
            );
        }
        return array_reverse($metrics);
    }

    /**
     * @param Metric $metric
     * @param string $storeValueCommand
     */
    private function storeMetricByType(Metric $metric, $storeValueCommand)
    {
        $this->openConnection();
        $type = $metric->getType();
        $key = $metric->getKey();
        foreach ($metric->getSamples() as $sample) {
            $sampleKey = $sample->getKey();
            $this->redis->$storeValueCommand(
                self::PROMETHEUS_PREFIX . $type . $key . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sampleKey,
                $sample->getValue()
            );
            $this->redis->hSet(
                self::PROMETHEUS_PREFIX . $type . $key . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sampleKey,
                serialize($sample->getLabelValues())
            );
            $this->redis->hSet(
                self::PROMETHEUS_PREFIX . $type . $key . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sampleKey,
                serialize($sample->getLabelNames())
            );
            $this->redis->hSet(
                self::PROMETHEUS_PREFIX . $type . $key . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sampleKey,
                $sample->getName()
            );
            $this->storeNewMetricSampleKey($type, $key, $sampleKey);
        }
        $this->redis->hSet(self::PROMETHEUS_PREFIX . $type . $key, 'name', $metric->getName());
        $this->redis->hSet(self::PROMETHEUS_PREFIX . $type . $key, 'help', $metric->getHelp());
        $this->redis->hSet(self::PROMETHEUS_PREFIX . $type . $key, 'type', $metric->getType());
        $this->redis->hSet(self::PROMETHEUS_PREFIX . $type . $key, 'labelNames', serialize($metric->getLabelNames()));
        $this->storeNewMetricKey($type, $key);
    }

    /**
     * @param string $metricType
     * @param string $key
     * @param string $sampleKey
     */
    private function storeNewMetricSampleKey($metricType, $key, $sampleKey)
    {
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_PREFIX . self::PROMETHEUS_METRICS_SAMPLE_COUNTER);
        $this->redis->zAdd(
            self::PROMETHEUS_PREFIX . $metricType . $key . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX,
            $currentMetricCounter,
            $sampleKey
        );
    }

    /**
     * @param string $metricType
     * @param string $key
     */
    private function storeNewMetricKey($metricType, $key)
    {
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_PREFIX . self::PROMETHEUS_METRICS_COUNTER);
        $this->redis->zAdd(
            self::PROMETHEUS_PREFIX . $metricType . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
            $currentMetricCounter,
            $key
        );
    }

    private function openConnection()
    {
        $this->redis->connect($this->hostname);
    }
}
