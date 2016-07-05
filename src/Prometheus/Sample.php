<?php

namespace Prometheus;


class Sample
{
    private $name;
    private $labelNames;
    private $labelValues;
    private $value;

    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->labelNames = $data['labelNames'];
        $this->labelValues = $data['labelValues'];
        $this->value = $data['value'];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getLabelNames()
    {
        return $this->labelNames;
    }

    /**
     * @return array
     */
    public function getLabelValues()
    {
        return $this->labelValues;
    }

    /**
     * @return int|double
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return sha1($this->getName() . serialize($this->getLabelValues()));
    }

    /**
     * @return array
     */
    public function getLabels()
    {
        $labelNames = $this->getLabelNames();
        if (empty($labelNames)) {
            return array();
        }
        return array_combine($labelNames, $this->getLabelValues());
    }
}
