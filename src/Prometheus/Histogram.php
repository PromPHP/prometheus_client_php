<?php

namespace Prometheus;


class Histogram extends Metric
{
    const TYPE = 'histogram';

    private $buckets;

    /**
     * @param string $namespace
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param array $buckets
     */
    public function __construct($namespace, $name, $help, $labels = array(), $buckets = array())
    {
        parent::__construct($namespace, $name, $help, $labels);

        if (0 == count($buckets)) {
            throw new \InvalidArgumentException("Histogram must have at least one bucket.");
        }
        for ($i = 0; $i < count($buckets) - 1; $i++) {
            if ($buckets[$i] >= $buckets[$i + 1]) {
                throw new \InvalidArgumentException(
                    "Histogram buckets must be in increasing order: " .
                    $buckets[$i] . " >= " . $buckets[$i + 1]
                );
            }
        }
        foreach ($buckets as $bucket) {
            if ($bucket == 'le') {
                throw new \InvalidArgumentException("Histogram cannot have a label named 'le'.");
            }
        }
        $this->buckets = $buckets;
    }

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function observe($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        if (!isset($this->values[serialize($labels)])) {
            $this->values[serialize($labels)] = array(
                'sum' => 0,
                'count' => 0
            );
            foreach ($this->buckets as $bucket) {
                $this->values[serialize($labels)]['buckets'][(string)$bucket] = 0;
            }
            $this->values[serialize($labels)]['buckets']['+Inf'] = 0;
        }
        $this->values[serialize($labels)]['sum'] += $value;
        $this->values[serialize($labels)]['count']++;
        foreach ($this->values[serialize($labels)]['buckets'] as $bucket => $bucketCounter) {
            if ($value <= $bucket) {
                $this->values[serialize($labels)]['buckets'][(string)$bucket] = $bucketCounter + 1;
            }
        }
        $this->values[serialize($labels)]['buckets']['+Inf']++;
    }

    /**
     * @return Sample[]
     */
    public function getSamples()
    {
        $samples = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labelValues = unserialize($serializedLabels);
            foreach ($value['buckets'] as $bucket => $bucketCounter) {
                $samples[] = new Sample(
                    array(
                        'name' => $this->getName() . '_bucket',
                        'labelNames' => array_merge($this->getLabelNames(), array('le')),
                        'labelValues' => array_merge($labelValues, array($bucket)),
                        'value' => $bucketCounter
                    )
                );
            }
            $samples[] = new Sample(
                array(
                    'name' => $this->getName() . '_count',
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labelValues,
                    'value' => $value['count']
                )
            );
            $samples[] = new Sample(
                array(
                    'name' => $this->getName() . '_sum',
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labelValues,
                    'value' => $value['sum']
                )
            );
        }
        return $samples;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return self::TYPE;
    }
}
