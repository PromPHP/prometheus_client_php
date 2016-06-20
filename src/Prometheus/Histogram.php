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
     */
    public function __construct($namespace, $name, $help, $labels = array(), $buckets=array())
    {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->help = $help;
        $this->labels = $labels;
        $this->buckets = $buckets;
        $this->sum = 0;
        $this->count = 0;
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
        $this->count += 1;
        $this->sum += $value;
        $this->values[serialize($labels)] = $value;

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
            $metrics[] = array(
                'name' => Metric::metricName($this->namespace, $this->name),
                'labels' => $labels,
                'value' => $value,
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

    /**
     * @param int $count e.g. 2
     * @param array $labels e.g. ['controller' => 'status', 'action' => 'opcode']
     */
    public function increaseBy($count, array $labels)
    {
        if (array_keys($labels) != $this->labels) {
            throw new \InvalidArgumentException(sprintf('Label %s is not defined.', $labels));
        }
        if (!isset($this->values[serialize($labels)])) {
            $this->values[serialize($labels)] = 0;
        }
        $this->values[serialize($labels)] += $count;
    }
}
