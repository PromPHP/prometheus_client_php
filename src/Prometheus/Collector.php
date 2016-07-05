<?php

namespace Prometheus;


use Prometheus\Storage\Adapter;

abstract class Collector
{
    const RE_METRIC_LABEL_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:]*$/';

    protected $storageAdapter;
    protected $name;
    protected $help;
    protected $labels;

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
        $metricName = ($namespace ? $namespace . '_' : '') . $name;
        if (!preg_match(self::RE_METRIC_LABEL_NAME, $metricName)) {
            throw new \InvalidArgumentException("Invalid metric name: '" . $metricName . "'");
        }
        $this->name = $metricName;
        $this->help = $help;
        foreach ($labels as $label) {
            if (!preg_match(self::RE_METRIC_LABEL_NAME, $label)) {
                throw new \InvalidArgumentException("Invalid label name: '" . $metricName . "'");
            }
        }
        $this->labels = $labels;
    }

    /**
     * @return string
     */
    public abstract function getType();

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
