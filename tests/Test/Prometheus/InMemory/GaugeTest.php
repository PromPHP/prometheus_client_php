<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class GaugeTest extends AbstractGaugeTest
{

    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->flushMemory();
    }
}
