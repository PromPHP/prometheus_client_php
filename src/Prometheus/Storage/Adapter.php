<?php
namespace Prometheus\Storage;

use Prometheus\Metric;
use Prometheus\MetricResponse;
use Prometheus\Sample;

interface Adapter
{
    const COMMAND_INCREMENT_INTEGER = 1;
    const COMMAND_INCREMENT_FLOAT = 2;
    const COMMAND_SET = 3;

    /**
     * @return MetricResponse[]
     */
    public function fetchMetrics();

    public function storeSample($command, Metric $metric, Sample $sample);
}
