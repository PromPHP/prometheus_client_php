<?php

namespace Prometheus\Storage;


use Prometheus\Collector;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;

class InMemory implements Adapter
{
    /**
     * @var Collector[]
     */
    private $metrics = array();
    /**
     * @var array
     */
    private $samples = array();

    /**
     * @return MetricFamilySamples[]
     */
    public function collect()
    {
        $responses = array();
        foreach ($this->metrics as $metric) {
            $samples = $this->samples[$metric->getKey()];
            array_multisort($samples);
            $responses[] = new MetricFamilySamples(
                array(
                    'name' => $metric->getName(),
                    'type' => $metric->getType(),
                    'help' => $metric->getHelp(),
                    'samples' => $samples,
                    'labelNames' => $metric->getLabelNames()
                )
            );
        }
        array_multisort($responses);
        return $responses;
    }

    /**
     * @return Sample[]
     */
    public function fetchSamples()
    {
        return array_map(
            function ($data) { return new Sample($data); },
            array_values(array_reduce(array_values($this->samples), 'array_merge', array()))
        );
    }

    public function updateHistogram(array $data)
    {
        // TODO: Implement incrementByFloat() method.
    }

    public function updateGauge(array $data)
    {
        // TODO: Implement updateGauge() method.
    }

    public function updateCounter(array $data)
    {
        // TODO: Implement updateCounter() method.
    }


}
