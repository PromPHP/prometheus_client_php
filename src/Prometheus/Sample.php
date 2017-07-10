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
        return (array)$this->labelNames;
    }

    /**
     * @return array
     */
    public function getLabelValues()
    {
        return (array)$this->labelValues;
    }

    /**
     * @return int|double
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function hasLabelNames()
    {
        return !empty($this->labelNames);
    }
}
