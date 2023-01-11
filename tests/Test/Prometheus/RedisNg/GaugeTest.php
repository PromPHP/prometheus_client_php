<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisNg;

use Prometheus\Storage\Redis;
use Prometheus\Storage\RedisNg;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class GaugeTest extends AbstractGaugeTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new RedisNg(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
