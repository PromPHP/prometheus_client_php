<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Prometheus\Storage\Predis;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class GaugeWithPrefixTest extends AbstractGaugeTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Predis(['host' => REDIS_HOST], ['prefix' => 'prefix:']);
        $this->adapter->wipeStorage();
    }
}
