<?php

namespace Prometheus\Exception;

use Exception;

/**
 * Exception thrown if a metric can't be found in the CollectorRegistry.
 */
class MetricJsonException extends Exception
{
    private ?string $metricName;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        ?string $metricName = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->metricName = $metricName;
    }

    public function getMetricName(): ?string
    {
        return $this->metricName;
    }
}
