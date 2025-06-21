<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractGaugeTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class GaugeTest extends AbstractGaugeTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->wipeStorage();
    }
}
