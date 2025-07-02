<?php

declare(strict_types=1);

namespace Test\Prometheus\APCng;

use Prometheus\Storage\APCng;
use Test\Prometheus\AbstractHistogramTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension apcu
 */
class HistogramTest extends AbstractHistogramTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new APCng();
        $this->adapter->wipeStorage();
    }
}
