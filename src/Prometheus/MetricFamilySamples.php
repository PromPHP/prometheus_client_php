<?php

namespace Prometheus;

class MetricFamilySamples
{
    private $name;
    private $type;
    private $help;
    private $labelNames;
    private $samples = array();

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->type = $data['type'];
        $this->help = $data['help'];
        $this->labelNames = $data['labelNames'];
        foreach ($data['samples'] as $sampleData) {
            $this->samples[] = new Sample($sampleData);
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * @return Sample[]
     */
    public function getSamples()
    {
        return $this->samples;
    }

    /**
     * @return array
     */
    public function getLabelNames()
    {
        return $this->labelNames;
    }

    /**
     * @return bool
     */
    public function hasLabelNames()
    {
        return !empty($this->labelNames);
    }
}
