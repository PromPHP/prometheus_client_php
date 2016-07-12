<?php
namespace Prometheus\Storage;

use Prometheus\Collector;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;

interface Adapter
{
    const COMMAND_INCREMENT_INTEGER = 'hIncrBy';
    const COMMAND_INCREMENT_FLOAT = 'hIncrByFloat';
    const COMMAND_SET = 'hSet';

    /**
     * @return MetricFamilySamples[]
     */
    public function collect();

    public function store($command, Collector $metric, Sample $sample);

    public function updateHistogram(array $data);

    public function updateGauge(array $data);
}
