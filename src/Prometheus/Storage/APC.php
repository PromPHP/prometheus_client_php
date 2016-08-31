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
        $metrics = $this->collectGauges();
        $metrics = array_merge($metrics, $this->collectCounters());
        return $metrics;
    }

    public function updateHistogram(array $data)
    {
        // TODO: Implement updateHistogram() method.
    }

    public function updateGauge(array $data)
    {
        $new = apc_add($this->valueKey($data), 0);
        if ($new) {
            apc_store($this->metaKey($data), json_encode($this->metaData($data)));
        }
        apc_inc($this->valueKey($data), (int) ($data['value'] * self::PRECISION_FACTOR));
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
                    'value' => $value['value'] / self::PRECISION_FACTOR
                );
            }
            $gauges[] = new MetricFamilySamples($data);
        }
        return $gauges;
    }
}
