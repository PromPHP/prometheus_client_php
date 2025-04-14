<?php

namespace Prometheus\Exception;

use RuntimeException;

/**
 * Exception thrown if a metric can't be found in the CollectorRegistry.
 */
class MetricNotFoundException extends RuntimeException
{
}
