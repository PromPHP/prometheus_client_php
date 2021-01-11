<?php

declare(strict_types=1);

namespace Test\Prometheus\APC;

use Prometheus\Storage\APC;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension apcu
 */
class CounterTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new APC();
        $this->adapter->wipeStorage();
    }
}
