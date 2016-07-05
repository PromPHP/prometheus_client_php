<?php

namespace Prometheus\Storage;


use Prometheus\Collector;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;

class InMemory implements Adapter
{
    /**
     * @var array
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
            $responses[] = new MetricFamilySamples(
                array(
                    'name' => $metric->getName(),
                    'type' => $metric->getType(),
                    'help' => $metric->getHelp(),
                    'samples' => $this->samples[$metric->getKey()]
                )
            );
        }
        return $responses;
    }

    public function store($command, Collector $metric, Sample $sample)
    {
        if (isset($this->samples[$metric->getKey()][$sample->getKey()])) {
            switch ($command) {
                case Adapter::COMMAND_INCREMENT_INTEGER:
                case Adapter::COMMAND_INCREMENT_FLOAT:
                    $this->samples[$metric->getKey()][$sample->getKey()]['value'] += $sample->getValue();
                    break;
                case Adapter::COMMAND_SET:
                    $this->samples[$metric->getKey()][$sample->getKey()]['value'] = $sample->getValue();
                    break;
                default:
                    throw new \RuntimeException('Unknown command.');
            }
        } else {
            $this->samples[$metric->getKey()][$sample->getKey()] = array(
                'name' => $sample->getName(),
                'labelNames' => $sample->getLabelNames(),
                'labelValues' => $sample->getLabelValues(),
                'value' => $sample->getValue(),
            );
            $this->metrics[$metric->getKey()] = $metric;
        }
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
}
