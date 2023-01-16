<?php

declare(strict_types=1);

namespace Test\Prometheus\Rediser;

use Prometheus\Storage\Rediser;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class CounterTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Rediser(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
