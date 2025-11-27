<?php

namespace Prometheus\Storage;


use Prometheus\Counter;
use Prometheus\Exception\StorageException;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;

class RedisCluster implements Adapter
{
    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';

    private static $defaultOptions = array();
    private static $prefix = 'PROMETHEUS_';

    private static $hashTag = null; // 新增：哈希标签配置

    private $options;
    private $client;

    public function __construct(array $options = array())
    {
        // 设置默认选项
        if (!isset(self::$defaultOptions['redis_list'])) {
            self::$defaultOptions['redis_list'] = ['tcp://127.0.0.1:6379'];
        }
        if (!isset(self::$defaultOptions['cluster'])) {
            self::$defaultOptions['cluster'] = 'redis';
        }
        if (!isset(self::$defaultOptions['timeout'])) {
            self::$defaultOptions['timeout'] = 0.2;
        }
        if (!isset(self::$defaultOptions['read_write_timeout'])) {
            self::$defaultOptions['read_write_timeout'] = 10;
        }
        if (!isset(self::$defaultOptions['persistent'])) {
            self::$defaultOptions['persistent'] = false;
        }
        if (!isset(self::$defaultOptions['password'])) {
            self::$defaultOptions['password'] = null;
        }

        $this->options = array_merge(self::$defaultOptions, $options);

        $this->client = new \Predis\Client(
            $this->options['redis_list'],
            [
                'cluster' => $this->options['cluster'],
                'parameters' => [
                    'password' => $this->options['password'],
                    'timeout' => $this->options['timeout'],
                    'read_write_timeout' => $this->options['read_write_timeout'],
                    'persistent' => $this->options['persistent'],
                ]
            ]
        );
    }

    /**
     * @param array $options
     */
    public static function setDefaultOptions(array $options)
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }

    public static function setPrefix($prefix)
    {
        self::$prefix = $prefix;
    }

    /**
     * set hash tag
     * @param string $hashTag hash tag content (not contain {})
     */
    public static function setHashTag($hashTag)
    {
        self::$hashTag = $hashTag;
    }


    public function flushRedisCluster()
    {
        $deletedCount = 0;

        $metricKeysKeys = [
            $this->addHashTag($this->toMetricKeyKey(Histogram::TYPE)),
            $this->addHashTag($this->toMetricKeyKey(Gauge::TYPE)),
            $this->addHashTag($this->toMetricKeyKey(Counter::TYPE)),
        ];

        foreach ($metricKeysKeys as $metricKeysKey) {
            $metricKeys = $this->client->smembers($metricKeysKey);

            if (!empty($metricKeys)) {
                $count = $this->client->del($metricKeys);
                $deletedCount += $count;

                $this->client->del([$metricKeysKey]);
            }
        }

        return $deletedCount;
    }

    /**
     * @return MetricFamilySamples[]
     * @throws StorageException
     */
    public function collect()
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        return array_map(
            function (array $metric) {
                return new MetricFamilySamples($metric);
            },
            $metrics
        );
    }

    public function updateHistogram(array $data)
    {
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['labelValues']);

        // add hash tag, ensure all keys in the same slot
        $metricKey = $this->addHashTag($this->toMetricKey($data));
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Histogram::TYPE));

        $sumKey = json_encode(array('b' => 'sum', 'labelValues' => $data['labelValues']));
        $bucketKey = json_encode(array('b' => $bucketToIncrease, 'labelValues' => $data['labelValues']));

        // use pipeline to execute all commands
        $pipe = $this->client->pipeline();

        // add sum
        $pipe->hincrbyfloat($metricKey, $sumKey, (float)$data['value']);

        // add bucket count
        $pipe->hincrby($metricKey, $bucketKey, 1);

        // set meta key (hsetnx: only when it does not exist)
        $pipe->hsetnx($metricKey, '__meta', json_encode($metaData));

        // add to set 
        $pipe->sadd($metricKeysKey, $metricKey);

        // execute
        $pipe->execute();
    }

    public function updateGauge(array $data)
    {
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['labelValues']);
        unset($metaData['command']);

        $metricKey = $this->addHashTag($this->toMetricKey($data));
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Gauge::TYPE));
        $labelKey = json_encode($data['labelValues']);

        // use pipeline to execute all commands
        $pipe = $this->client->pipeline();

        // get redis command
        switch ($data['command']) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                $pipe->hincrby($metricKey, $labelKey, (int)$data['value']);
                break;
            case Adapter::COMMAND_INCREMENT_FLOAT:
                $pipe->hincrbyfloat($metricKey, $labelKey, (float)$data['value']);
                break;
            case Adapter::COMMAND_SET:
                $pipe->hset($metricKey, $labelKey, $data['value']);
                break;
        }

        // set meta key (hsetnx: only when it does not exist)
        $pipe->hsetnx($metricKey, '__meta', json_encode($metaData));

        // set meta key (hsetnx: only when it does not exist)
        $pipe->sadd($metricKeysKey, $metricKey);

        $pipe->execute();
    }

    public function updateCounter(array $data)
    {
        $metaData = $data;
        unset($metaData['value']);
        unset($metaData['labelValues']);
        unset($metaData['command']);

        $metricKey = $this->addHashTag($this->toMetricKey($data));
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Counter::TYPE));
        $labelKey = json_encode($data['labelValues']);

        // use pipeline to execute all commands
        $pipe = $this->client->pipeline();

        // get redis command
        switch ($data['command']) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                $pipe->hincrby($metricKey, $labelKey, (int)$data['value']);
                break;
            case Adapter::COMMAND_INCREMENT_FLOAT:
                $pipe->hincrbyfloat($metricKey, $labelKey, (float)$data['value']);
                break;
        }

        // set meta key (hsetnx: only when it does not exist)
        $pipe->hsetnx($metricKey, '__meta', json_encode($metaData));

        // set meta key (hsetnx: only when it does not exist)
        $pipe->sadd($metricKeysKey, $metricKey);

        $results = $pipe->execute();

        return isset($results[0]) ? $results[0] : 0;
    }

    private function collectHistograms()
    {
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Histogram::TYPE));
        $keys = $this->client->sMembers($metricKeysKey);

        sort($keys);
        $histograms = array();
        foreach ($keys as $key) {
            $raw = $this->client->hGetAll($key);
            $histogram = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $histogram['samples'] = array();

            // Add the Inf bucket so we can compute it later on
            $histogram['buckets'][] = '+Inf';

            $allLabelValues = array();
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
                    $bucketKey = json_encode(array('b' => $bucket, 'labelValues' => $labelValues));
                    if (!isset($raw[$bucketKey])) {
                        $histogram['samples'][] = array(
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => array('le'),
                            'labelValues' => array_merge($labelValues, array($bucket)),
                            'value' => $acc
                        );
                    } else {
                        $acc += $raw[$bucketKey];
                        $histogram['samples'][] = array(
                            'name' => $histogram['name'] . '_bucket',
                            'labelNames' => array('le'),
                            'labelValues' => array_merge($labelValues, array($bucket)),
                            'value' => $acc
                        );
                    }
                }

                // Add the count
                $histogram['samples'][] = array(
                    'name' => $histogram['name'] . '_count',
                    'labelNames' => array(),
                    'labelValues' => $labelValues,
                    'value' => $acc
                );

                // Add the sum
                $histogram['samples'][] = array(
                    'name' => $histogram['name'] . '_sum',
                    'labelNames' => array(),
                    'labelValues' => $labelValues,
                    'value' => $raw[json_encode(array('b' => 'sum', 'labelValues' => $labelValues))]
                );
            }
            $histograms[] = $histogram;
        }
        return $histograms;
    }

    private function collectGauges()
    {
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Gauge::TYPE));
        $keys = $this->client->sMembers($metricKeysKey);

        sort($keys);
        $gauges = array();
        foreach ($keys as $key) {
            $raw = $this->client->hGetAll($key);
            $gauge = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $gauge['samples'] = array();
            foreach ($raw as $k => $value) {
                $gauge['samples'][] = array(
                    'name' => $gauge['name'],
                    'labelNames' => array(),
                    'labelValues' => json_decode($k, true),
                    'value' => $value
                );
            }
            usort($gauge['samples'], function ($a, $b) {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $gauges[] = $gauge;
        }
        return $gauges;
    }

    private function collectCounters()
    {
        $metricKeysKey = $this->addHashTag($this->toMetricKeyKey(Counter::TYPE));
        $keys = $this->client->sMembers($metricKeysKey);

        sort($keys);
        $counters = array();
        foreach ($keys as $key) {
            $raw = $this->client->hGetAll($key);
            $counter = json_decode($raw['__meta'], true);
            unset($raw['__meta']);
            $counter['samples'] = array();
            foreach ($raw as $k => $value) {
                $counter['samples'][] = array(
                    'name' => $counter['name'],
                    'labelNames' => array(),
                    'labelValues' => json_decode($k, true),
                    'value' => $value
                );
            }
            usort($counter['samples'], function ($a, $b) {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });
            $counters[] = $counter;
        }
        return $counters;
    }

    /**
     * @param array $data
     * @return string
     */
    private function toMetricKey(array $data)
    {
        return implode(':', array(self::$prefix, $data['type'], $data['name']));
    }

    private function toMetricKeyKey($metricsType)
    {
        return self::$prefix . ':' . $metricsType . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
    }

    /**
     * add hash tag, ensure all keys in the same slot
     * @param $key
     */
    private function addHashTag($key)
    {
        // 如果key已经包含哈希标签，直接返回
        if (preg_match('/\{[^}]+\}/', $key)) {
            return $key;
        }

        $hashTagContent = self::$hashTag ?: 'PROMETHEUS';

        if (strpos($key, $hashTagContent) !== false) {
            return str_replace($hashTagContent, '{' . $hashTagContent . '}', $key);
        }

        return '{' . $hashTagContent . '}' . $key;
    }
}
