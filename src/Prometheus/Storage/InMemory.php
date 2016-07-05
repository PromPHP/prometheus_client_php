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
     * @param \Prometheus\Metric[] $metrics
     */
    public function storeMetrics($metrics)
    {
        foreach ($metrics as $metric) {
            $this->metrics[$metric->getKey()] = $metric;
        }
    }

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
