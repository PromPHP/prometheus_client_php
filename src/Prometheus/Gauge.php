<?php


namespace Prometheus;


class Gauge
{
    const TYPE = 'gauge';

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
     * @param double $value e.g. 123
     * @param array $labels e.g. ['controller' => 'status', 'action' => 'opcode']
     */
    public function set($value, $labels = array())
    {
        if (array_keys($labels) != $this->labels) {
            throw new \InvalidArgumentException(sprintf('Label combination %s is not defined.', print_r($labels, true)));
        }
        $this->values[serialize($labels)] = $value;
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

    public function getHelp()
    {
        return $this->help;
    }

    public function getFullName()
    {
        return Metric::metricName($this->namespace, $this->name);
    }

    public function getLabelNames()
    {
        return $this->labels;
    }
}
