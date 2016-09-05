<?php


namespace Prometheus\Storage;


use Prometheus\MetricFamilySamples;

class APC implements Adapter
{
    const PROMETHEUS_PREFIX = 'prom';

    // Multiply floats by this factor so we can use them
    // with APC.
    // The number has been picked so we have
    // approximately 245 years left to
    // measure time in seconds with 7 digits
    // of precision.
    // This effectively limits the largest value
    // that can be used with Gauges and Histograms
    // to 9223372036.8547764 (2**63/10**9).
    const PRECISION_FACTOR = 10**9;

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
        $new = apc_add($this->histogramBucketValueKey($data, 'sum'), 0);

        // If sum does not exist, assume a new histogram and store the metadata
        if ($new) {
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        }

        // Increment the sum
        apc_inc($this->histogramBucketValueKey($data, 'sum'), $this->toInteger($data['value']));

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
        if ($data['command'] == Adapter::COMMAND_SET) {
            apc_store($this->valueKey($data), $this->toInteger($data['value']));
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        } else {
            $new = apc_add($this->valueKey($data), 0);
            if ($new) {
                apc_store($this->metaKey($data), json_encode($this->metaData($data)));
            }
            apc_inc($this->valueKey($data), $this->toInteger($data['value']));
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
            foreach (array_keys($histogramBuckets) as $labelValues) {
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

                $histograms[] = new MetricFamilySamples($data);
            }

        }
        return $histograms;
    }

    /**
     * @param mixed $val
     * @return int
     */
    private function toInteger($val)
    {
        return (int)($val * self::PRECISION_FACTOR);
    }

    /**
     * @param mixed $val
     * @return int
     */
    private function fromInteger($val)
    {
        return $val / self::PRECISION_FACTOR;
    }

    private function sortSamples(array &$samples)
    {
        usort($samples, function($a, $b){
            return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
        });
    }
}
