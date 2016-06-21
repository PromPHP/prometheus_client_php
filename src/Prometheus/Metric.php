<?php

namespace Prometheus;


class Metric
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
     * @param $labels
     */
    protected function assertLabelsAreDefinedCorrectly($labels)
    {
        if (count($labels) != count($this->labels)) {
            throw new \InvalidArgumentException(sprintf('Labels are not defined correctly: ', print_r($labels, true)));
        }
    }
}
