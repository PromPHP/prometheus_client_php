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
    private $metricTypes;

    public function __construct($hostname)
    {
        $this->hostname = $hostname;
        $this->redis = new \Redis();
        $this->metricTypes = array(
            Gauge::TYPE,
            Counter::TYPE,
            Histogram::TYPE,
        );
    }

    public function flushRedis()
    {
        $this->openConnection();
        $this->redis->flushAll();
    }

    /**
     * @param Metric[] $metrics
     */
    public function storeMetrics($metrics)
    {
        $this->openConnection();
        foreach ($metrics as $metric) {
            $this->storeMetric($metric);
        }
    }

    public function fetchMetrics()
    {
        $this->openConnection();
        $metrics = array();
        foreach ($this->metricTypes as $metricType) {
            $metrics = array_merge($metrics, $this->fetchMetricsByType($metricType));
        }
        return $metrics;
    }

    /**
     * @param Metric $metric
     */
    private function storeMetric(Metric $metric)
    {
        $metricKey = self::PROMETHEUS_PREFIX . $metric->getType() . $metric->getKey();
        foreach ($metric->getSamples() as $sample) {
            switch ($metric->getType()) {
                case Counter::TYPE:
                    $storeValueCommand = 'hIncrBy';
                    break;
                case Gauge::TYPE:
                    $storeValueCommand = 'hSet';
                    break;
                case Histogram::TYPE:
                    $storeValueCommand = 'hIncrByFloat';
                    break;
                default:
                    throw new \RuntimeException('Invalid metric type!');
            }
            $this->redis->$storeValueCommand(
                $metricKey . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                $sample->getKey(),
                $sample->getValue()
            );
            $this->redis->hSet(
                $metricKey . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX,
                $sample->getKey(),
                serialize($sample->getLabelValues())
            );
            $this->redis->hSet(
                $metricKey . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX,
                $sample->getKey(),
                serialize($sample->getLabelNames())
            );
            $this->redis->hSet(
                $metricKey . self::PROMETHEUS_SAMPLE_NAME_SUFFIX,
                $sample->getKey(),
                $sample->getName()
            );
            $this->storeNewMetricSampleKey($metric->getType(), $metric->getKey(), $sample->getKey());
        }
        $this->redis->hSet($metricKey, 'name', $metric->getName());
        $this->redis->hSet($metricKey, 'help', $metric->getHelp());
        $this->redis->hSet($metricKey, 'type', $metric->getType());
        $this->redis->hSet($metricKey, 'labelNames', serialize($metric->getLabelNames()));
        $this->storeNewMetricKey($metric->getType(), $metric->getKey());
    }

    /**
     * @param string $metricType
     * @return MetricResponse[]
     */
    private function fetchMetricsByType($metricType)
    {
        $keys = $this->redis->zRange(
            self::PROMETHEUS_PREFIX . $metricType . self::PROMETHEUS_METRIC_KEYS_SUFFIX, 0, -1
        );
        $metrics = array();
        foreach ($keys as $key) {
            $metricKey = self::PROMETHEUS_PREFIX . $metricType . $key;
            $values = $this->redis->hGetAll($metricKey . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX);
            $labelValuesList = $this->redis->hGetAll($metricKey . self::PROMETHEUS_SAMPLE_LABEL_VALUES_SUFFIX);
            $sampleKeys = $this->redis->zRange($metricKey . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX, 0, -1);
            $sampleResponses = array();
            foreach ($sampleKeys as $sampleKey) {
                $labelNames = unserialize(
                    $this->redis->hGet($metricKey . self::PROMETHEUS_SAMPLE_LABEL_NAMES_SUFFIX, $sampleKey)
                );
                $name = $this->redis->hGet($metricKey . self::PROMETHEUS_SAMPLE_NAME_SUFFIX, $sampleKey);
                $sampleResponses[] = array(
                    'name' => $name,
                    'labelNames' => $labelNames,
                    'labelValues' => unserialize($labelValuesList[$sampleKey]),
                    'value' => $values[$sampleKey]
                );
            }
            $metricResponse = $this->redis->hGetAll($metricKey);
            $metrics[] = new MetricResponse(
                array(
                    'name' => $metricResponse['name'],
                    'help' => $metricResponse['help'],
                    'type' => $metricResponse['type'],
                    'samples' => $sampleResponses
                )
            );
        }
        return array_reverse($metrics);
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
