<?php

namespace Prometheus\Storage;


use Prometheus\Metric;
use Prometheus\MetricResponse;
use Prometheus\Sample;

class InMemory implements Adapter
{
    /**
     * @var \Prometheus\Metric[]
     */
    private $metrics = array();
    private $samples = array();

    /**
     * @return MetricResponse[]
     */
    public function fetchMetrics()
    {
        $responses = array();
        foreach ($this->metrics as $metric) {
            $responses[] = new MetricResponse(
                array(
                    'name' => $metric->getName(),
                    'type' => $metric->getType(),
                    'help' => $metric->getHelp(),
                    'samples' => $metric->getSamples()
                )
            );
        }
        return $responses;
    }

    public function storeSample($command, Metric $metric, Sample $sample)
    {
        $this->samples[$sample->getKey()] = $sample;
    }
}
