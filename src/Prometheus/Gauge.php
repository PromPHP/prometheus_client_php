<?php


namespace Prometheus;


class Gauge
{
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
            throw new \InvalidArgumentException(sprintf('Label %s is not defined.', $labels));
        }
        $this->values[serialize($labels)] = $value;
    }

    public static function metricName($namespace, $name)
    {
        return ($namespace ? $namespace . '_' : '') . $name;
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
            $metrics[] = array('name' => self::metricName($this->namespace, $this->name), 'labels' => $labels, 'value' => $value, 'help' => $this->help);
        }
        return $metrics;
    }
}
