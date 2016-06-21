<?php

namespace Prometheus;


class Counter
{
    const TYPE = 'counter';

    private $namespace;
    private $name;
    private $help;
    private $values = array();
    private $labels;

    /**
     * @param string $namespace
     * @param string $name
     * @param string $help
     * @param array $labels
     */
    public function __construct($namespace, $name, $help, $labels = array())
    {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->help = $help;
        $this->labels = $labels;
    }

    /**
     * @return Sample[]
     */
    public function getSamples()
    {
        $metrics = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labels = unserialize($serializedLabels);
            $metrics[] = new Sample(
                array(
                    'name' => $this->getFullName(),
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => array_values($labels),
                    'value' => $value
                )
            );
        }
        return $metrics;
    }

    public function getType()
    {
        return self::TYPE;
    }

    /**
     * @param array $labels e.g. ['controller' => 'status', 'action' => 'opcode']
     */
    public function increase(array $labels = array())
    {
        $this->increaseBy(1, $labels);
    }

    /**
     * @param int $count e.g. 2
     * @param array $labels e.g. ['controller' => 'status', 'action' => 'opcode']
     */
    public function increaseBy($count, array $labels = array())
    {
        if (array_keys($labels) != $this->labels) {
            throw new \InvalidArgumentException(sprintf('Label %s is not defined.', $labels));
        }
        if (!isset($this->values[serialize($labels)])) {
            $this->values[serialize($labels)] = 0;
        }
        $this->values[serialize($labels)] += $count;
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
