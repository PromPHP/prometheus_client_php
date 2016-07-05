<?php

namespace Prometheus;


use Prometheus\Storage\Adapter;

abstract class Metric
{
    const RE_METRIC_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:]*$/';
    protected $storageAdapter;
    protected $name;
    protected $help;
    protected $labels;
    protected $values = array();

    /**
     * @param Adapter $storageAdapter
     * @param string $namespace
     * @param string $name
     * @param string $help
     * @param array $labels
     */
    public function __construct(Adapter $storageAdapter, $namespace, $name, $help, $labels = array())
    {
        $this->storageAdapter = $storageAdapter;
        $metricName = Metric::metricName($namespace, $name);
        if (!preg_match(self::RE_METRIC_NAME, $metricName)) {
            throw new \InvalidArgumentException("Invalid metric name: '" . $metricName . "'");
        }
        $this->name = $metricName;
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

    public function getName()
    {
        return $this->name;
    }

    public function getLabelNames()
    {
        return $this->labels;
    }

    public function getHelp()
    {
        return $this->help;
    }

    public function getKey()
    {
        return sha1($this->getName() . serialize($this->getLabelNames()));
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
