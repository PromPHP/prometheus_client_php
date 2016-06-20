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
}
