<?php
namespace Prometheus\Storage;

use Prometheus\Metric;
use Prometheus\MetricResponse;
use Prometheus\Sample;

interface Adapter
{
    /**
     * @return MetricResponse[]
     */
    public function fetchMetrics();

    public function storeSample($command, Metric $metric, Sample $sample);
}
