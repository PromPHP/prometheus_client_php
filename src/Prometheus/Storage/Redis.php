<?php

namespace Prometheus\Storage;


use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Collector;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;

class Redis implements Adapter
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

    private $options;
    private $redis;
    private $metricTypes;

    public function __construct(array $options)
    {
        if (!isset($options['host'])) {
            $options['host'] = '127.0.0.1';
        }
        if (!isset($options['port'])) {
            $options['port'] = 6379;
        }
        if (!isset($options['connect_timeout'])) {
            $options['connect_timeout'] = 0.1; // in seconds
        }
        if (!isset($options['persistent_connections'])) {
            $options['persistent_connections'] = false;
        }
        $this->options = $options;
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
     * @return MetricFamilySamples[]
     */
    public function collect()
    {
        $this->openConnection();
        $metrics = array();
        foreach ($this->metricTypes as $metricType) {
            $metrics = array_merge($metrics, $this->fetchMetricsByType($metricType));
        }
        return $metrics;
    }

    public function store($command, Collector $metric, Sample $sample)
    {
        $this->openConnection();
        $this->storeMetricFamilySample($command, $metric, $sample);
        $this->storeMetricFamilyMetadata($metric);
    }

    /**
     * @param string $metricType
     * @return MetricFamilySamples[]
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
            $metrics[] = new MetricFamilySamples(
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

    private function openConnection()
    {
        if ($this->options['persistent_connections']) {
            $this->redis->pconnect($this->options['host'], $this->options['port'], $this->options['connect_timeout']);
        } else {
            $this->redis->connect($this->options['host'], $this->options['port'], $this->options['connect_timeout']);
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
        switch ($command) {
            case self::COMMAND_INCREMENT_INTEGER:
                $this->redis->hIncrBy(
                    $metricKey . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                    $sample->getKey(),
                    $sample->getValue()
                );
                break;
            case self::COMMAND_INCREMENT_FLOAT:
                $this->redis->hIncrByFloat(
                    $metricKey . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                    $sample->getKey(),
                    $sample->getValue()
                );
                break;
            case self::COMMAND_SET:
                $this->redis->hSet(
                    $metricKey . self::PROMETHEUS_SAMPLE_VALUE_SUFFIX,
                    $sample->getKey(),
                    $sample->getValue()
                );
                break;
            default:
                throw new \RuntimeException('Unknown command.');
        }
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

        /**
         * Store new metric family sample keys in order of occurence.
         * This guarantees that the keys can be retrieved in the same order
         * and don't need to be sorted again.
         */
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_PREFIX . self::PROMETHEUS_METRICS_SAMPLE_COUNTER);
        $this->redis->zAdd(
            $metricKey . self::PROMETHEUS_SAMPLE_KEYS_SUFFIX,
            $currentMetricCounter,
            $sample->getKey()
        );
    }

    /**
     * @param Collector $metric
     */
    private function storeMetricFamilyMetadata(Collector $metric)
    {
        $metricKey = self::PROMETHEUS_PREFIX . $metric->getType() . $metric->getKey();
        $this->redis->hSet($metricKey, 'name', $metric->getName());
        $this->redis->hSet($metricKey, 'help', $metric->getHelp());
        $this->redis->hSet($metricKey, 'type', $metric->getType());
        $this->redis->hSet($metricKey, 'labelNames', serialize($metric->getLabelNames()));

        /**
         * Store new metric family keys in order of occurence.
         * This guarantees that the keys can be retrieved in the same order
         * and don't need to be sorted again.
         */
        $currentMetricCounter = $this->redis->incr(self::PROMETHEUS_PREFIX . self::PROMETHEUS_METRICS_COUNTER);
        $this->redis->zAdd(
            self::PROMETHEUS_PREFIX . $metric->getType() . self::PROMETHEUS_METRIC_KEYS_SUFFIX,
            $currentMetricCounter,
            $metric->getKey()
        );
    }
}
