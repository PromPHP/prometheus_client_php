<?php

namespace Prometheus\Exception;

use Exception;

/**
 * Exception thrown if a metric can't be found in the CollectorRegistry.
 */
class MetricJsonException extends Exception
{

    private $metricMetaData;
    public function __construct($message = "", $code = 0, Exception $previous = null, string $metricMetaData)
    {
        parent::__construct($message, $code, $previous);
        $this->metricMetaData = $metricMetaData;
    }

    public function getMetricMetaData(): string
    {
        return $this->metricMetaData;
    }
}
