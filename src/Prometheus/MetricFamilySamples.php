<?php

namespace Prometheus;

class MetricFamilySamples
{
    protected $name;
    protected $type;
    protected $help;
    protected $samples = array();

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->type = $data['type'];
        $this->help = $data['help'];
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
}
