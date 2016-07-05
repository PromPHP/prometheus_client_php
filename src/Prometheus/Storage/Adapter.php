<?php
namespace Prometheus\Storage;

use Prometheus\Metric;
use Prometheus\MetricResponse;
use Prometheus\Sample;

interface Adapter
{
    /**
     * @param Metric[] $metrics
     */
    public function storeMetrics($metrics);

    /**
     * @return MetricResponse[]
     */
    public function fetchMetrics();

    public function storeSample($command, Metric $metric, Sample $sample);
}
