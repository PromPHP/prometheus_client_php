<?php

namespace Prometheus;


abstract class Metric
{
    protected $labels;
    protected $namespace;
    protected $name;
    protected $help;
    protected $values = array();

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

    public static function metricName($namespace, $name)
    {
        return ($namespace ? $namespace . '_' : '') . $name;
    }

    public static function metricIdentifier($namespace, $name, $labels)
    {
        if (empty($labels)) {
            return self::metricName($namespace, $name);
        }
        return self::metricName($namespace, $name) . '_' . implode('_', $labels);
    }

    /**
     * @return string
     */
    public abstract function getType();

    /**
     * @return Sample[]
     */
    public abstract function getSamples();

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

    /**
     * @param $labels
     */
    protected function assertLabelsAreDefinedCorrectly($labels)
    {
        if (count($labels) != count($this->labels)) {
            throw new \InvalidArgumentException(sprintf('Labels are not defined correctly: ', print_r($labels, true)));
        }
    }
}
