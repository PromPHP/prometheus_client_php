<?php

namespace Prometheus;


class Histogram
{
    const TYPE = 'histogram';

    private $namespace;
    private $name;
    private $help;
    private $values = array();
    private $labels;
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
        $this->namespace = $namespace;
        $this->name = $name;
        $this->help = $help;
        $this->labels = $labels;

        if (0 == count($buckets)) {
            throw new \InvalidArgumentException("Histogram must have at least one bucket.");
        }
        for($i = 0; $i < count($buckets) - 1; $i++) {
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
     * @param array $labels e.g. ['controller' => 'status', 'action' => 'opcode']
     */
    public function observe($value, $labels = array())
    {
        if (array_keys($labels) != $this->labels) {
            throw new \InvalidArgumentException(sprintf('Label %s is not defined.', $labels));
        }
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
     * @return array [['name' => 'foo_bar', labels => ['name' => 'foo', value='bar'], value => '23']]
     */
    public function getSamples()
    {
        $samples = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labels = unserialize($serializedLabels);
            $labelValues = array_values($labels);
            foreach ($value['buckets'] as $bucket => $bucketCounter) {
                $samples[] = array(
                    'name' => $this->getFullName() . '_bucket',
                    'labelNames' => array_merge($this->getLabelNames(), array('le')),
                    'labelValues' => array_merge($labelValues, array($bucket)),
                    'value' => $bucketCounter
                );
            }
            $samples[] = array(
                'name' => $this->getFullName() . '_count',
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labelValues,
                'value' => $value['count']
            );
            $samples[] = array(
                'name' => $this->getFullName() . '_sum',
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labelValues,
                'value' => $value['sum']
            );
        }
        return $samples;
    }

    public function getType()
    {
        return self::TYPE;
    }

    public function getFullName()
    {
        return Metric::metricName($this->namespace, $this->name);
    }

    public function getLabelNames()
    {
        return $this->labels;
    }

    public function getHelp()
    {
        return $this->help;
    }
}
