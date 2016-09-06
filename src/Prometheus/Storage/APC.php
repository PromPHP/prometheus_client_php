<?php


namespace Prometheus\Storage;


use Prometheus\MetricFamilySamples;

class APC implements Adapter
{
    const PROMETHEUS_PREFIX = 'prom';

    /**
     * @return MetricFamilySamples[]
     */
    public function collect()
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        return $metrics;
    }

    public function updateHistogram(array $data)
    {
        // Initialize the sum
        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        $new = apc_add($sumKey, $this->toInteger(0));

        // If sum does not exist, assume a new histogram and store the metadata
        if ($new) {
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        }

        // Atomically increment the sum
        // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
        $done = false;
        while (!$done) {
            $old = apc_fetch($sumKey);
            $done = apc_cas($sumKey, $old, $this->toInteger($this->fromInteger($old) + $data['value']));
        }

        // Figure out in which bucket the observation belongs
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }

        // Initialize and increment the bucket
        apc_add($this->histogramBucketValueKey($data, $bucketToIncrease), 0);
        apc_inc($this->histogramBucketValueKey($data, $bucketToIncrease));
    }

    public function updateGauge(array $data)
    {
        $valueKey = $this->valueKey($data);
        if ($data['command'] == Adapter::COMMAND_SET) {
            apc_store($valueKey, $this->toInteger($data['value']));
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        } else {
            $new = apc_add($valueKey, $this->toInteger(0));
            if ($new) {
                apc_store($this->metaKey($data), json_encode($this->metaData($data)));
            }
            // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
            $done = false;
            while (!$done) {
                $old = apc_fetch($valueKey);
                $done = apc_cas($valueKey, $old, $this->toInteger($this->fromInteger($old) + $data['value']));
            }
        }
    }

    public function updateCounter(array $data)
    {
        $new = apc_add($this->valueKey($data), 0);
        if ($new) {
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        }
        apc_inc($this->valueKey($data), $data['value']);
    }

    public function flushAPC()
    {
       apc_clear_cache('user');
    }

    /**
     * @param array $data
     * @return string
     */
    private function metaKey(array $data)
    {
        return implode(':', array(self::PROMETHEUS_PREFIX, $data['type'], $data['name'], 'meta'));
    }

    /**
     * @param array $data
     * @return string
     */
    private function valueKey(array $data)
    {
        return implode(':', array(self::PROMETHEUS_PREFIX, $data['type'], $data['name'], json_encode($data['labelValues']), 'value'));
    }

    /**
     * @param array $data
     * @return string
     */
    private function histogramBucketValueKey(array $data, $bucket)
    {
        return implode(':', array(self::PROMETHEUS_PREFIX, $data['type'], $data['name'], json_encode($data['labelValues']), $bucket, 'value'));
    }

    /**
     * @param array $data
     * @return array
     */
    private function metaData(array $data)
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value']);
        unset($metricsMetaData['command']);
        unset($metricsMetaData['labelValues']);
        return $metricsMetaData;
    }

    /**
     * @return array
     */
    private function collectCounters()
    {
        $counters = array();
        foreach (new \APCIterator('user', '/^prom:counter:.*:meta/') as $counter) {
            $metaData = json_decode($counter['value'], true);
            $data = array(
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
            );
            foreach (new \APCIterator('user', '/^prom:counter:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = array(
                    'name' => $metaData['name'],
                    'labelNames' => array(),
                    'labelValues' => json_decode($labelValues),
                    'value' => $value['value']
                );
            }
            $this->sortSamples($data['samples']);
            $counters[] = new MetricFamilySamples($data);
        }
        return $counters;
    }

    /**
 * @return array
 */
    private function collectGauges()
    {
        $gauges = array();
        foreach (new \APCIterator('user', '/^prom:gauge:.*:meta/') as $gauge) {
            $metaData = json_decode($gauge['value'], true);
            $data = array(
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
            );
            foreach (new \APCIterator('user', '/^prom:gauge:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = array(
                    'name' => $metaData['name'],
                    'labelNames' => array(),
                    'labelValues' => json_decode($labelValues),
                    'value' => $this->fromInteger($value['value'])
                );
            }

            $this->sortSamples($data['samples']);
            $gauges[] = new MetricFamilySamples($data);
        }
        return $gauges;
    }

    /**
     * @return array
     */
    private function collectHistograms()
    {
        $histograms = array();
        foreach (new \APCIterator('user', '/^prom:histogram:.*:meta/') as $histogram) {
            $metaData = json_decode($histogram['value'], true);
            $data = array(
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'buckets' => $metaData['buckets']
            );

            // Add the Inf bucket so we can compute it later on
            $data['buckets'][] = '+Inf';

            $histogramBuckets = array();
            foreach (new \APCIterator('user', '/^prom:histogram:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $bucket = $parts[4];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value['value'];
            }

            // Compute all buckets
            $labels = array_keys($histogramBuckets);
            sort($labels);
            foreach ($labels as $labelValues) {
                $acc = 0;
                $decodedLabelValues = json_decode($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string) $bucket;
                    if (!isset($histogramBuckets[$labelValues][$bucket])) {
                        $data['samples'][] = array(
                            'name' => $metaData['name'] . '_bucket',
                            'labelNames' => array('le'),
                            'labelValues' => array_merge($decodedLabelValues, array($bucket)),
                            'value' => $acc
                        );
                    } else {
                        $acc += $histogramBuckets[$labelValues][$bucket];
                        $data['samples'][] = array(
                            'name' => $metaData['name'] . '_' . 'bucket',
                            'labelNames' => array('le'),
                            'labelValues' => array_merge($decodedLabelValues, array($bucket)),
                            'value' => $acc
                        );
                    }
                }

                // Add the count
                $data['samples'][] = array(
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => array(),
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc
                );

                // Add the sum
                $data['samples'][] = array(
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => array(),
                    'labelValues' => $decodedLabelValues,
                    'value' => $this->fromInteger($histogramBuckets[$labelValues]['sum'])
                );

            }
            $histograms[] = new MetricFamilySamples($data);
        }
        return $histograms;
    }

    /**
     * @param mixed $val
     * @return int
     */
    private function toInteger($val)
    {
        return unpack('Q', pack('d', $val))[1];
    }

    /**
     * @param mixed $val
     * @return int
     */
    private function fromInteger($val)
    {
        return unpack('d', pack('Q', $val))[1];
    }

    private function sortSamples(array &$samples)
    {
        usort($samples, function($a, $b){
            return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
        });
    }
}
