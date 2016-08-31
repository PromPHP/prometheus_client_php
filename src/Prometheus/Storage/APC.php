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
        /**
         * @var MetricFamilySamples[]
         */
        $metrics = array();
        foreach (new \APCIterator('user', '/^prom:counter:.*:meta/') as $counter) {
            $metaData = json_decode($counter['value'], true);
            $data = array(
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
            );
            foreach (new \APCIterator('user', '/^prom:counter:'. $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':',$value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = array(
                    'name' => $metaData['name'],
                    'labelNames' => array(),
                    'labelValues' => json_decode($labelValues),
                    'value' => $value['value']
                );
            }
            $metrics[] = new MetricFamilySamples($data);

        }
        return $metrics;
    }

    public function updateHistogram(array $data)
    {
        // TODO: Implement updateHistogram() method.
    }

    public function updateGauge(array $data)
    {
        // TODO: Implement updateGauge() method.
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
}
