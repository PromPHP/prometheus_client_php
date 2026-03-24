<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Prometheus\Storage\Predis;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterWithPrefixTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Predis(['host' => REDIS_HOST], ['prefix' => 'prefix:']);
        $this->adapter->wipeStorage();
    }
}
