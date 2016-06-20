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
                $this->values[serialize($labels)]['buckets'][$bucket] = 0;
            }
        }
        $this->values[serialize($labels)]['sum'] += $value;
        $this->values[serialize($labels)]['count']++;
        foreach ($this->values[serialize($labels)]['buckets'] as $bucket => $bucketCounter) {
            if ($value <= $bucket) {
                $this->values[serialize($labels)]['buckets'][$bucket] = $bucketCounter + 1;
            }
        }

    }

    /**
     * @return array [['name' => 'foo_bar', labels => ['name' => 'foo', value='bar'], value => '23']]
     */
    public function getSamples()
    {
        $metrics = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labels = array();
            foreach (unserialize($serializedLabels) as $labelName => $labelValue) {
                $labels[] = array('name' => $labelName, 'value' => $labelValue);
            }
            foreach ($value['buckets'] as $bucket => $bucketCounter) {
                $metrics[] = array(
                    'name' => Metric::metricName($this->namespace, $this->name),
                    'labels' => array_merge($labels, array(array('name' => 'le', 'value' => $bucket))),
                    'value' => $bucketCounter,
                    'help' => $this->help,
                    'type' => $this->getType()
                );
            }
            $metrics[] = array(
                'name' => Metric::metricName($this->namespace, $this->name) . '_sum',
                'labels' => $labels,
                'value' => $value['sum'],
                'help' => $this->help,
                'type' => $this->getType()
            );
            $metrics[] = array(
                'name' => Metric::metricName($this->namespace, $this->name) . '_count',
                'labels' => $labels,
                'value' => $value['count'],
                'help' => $this->help,
                'type' => $this->getType()
            );
        }
        return $metrics;
    }

    private function getType()
    {
        return self::TYPE;
    }
}
