<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Predis\Client;
use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 *
 * @requires extension redis
 */
class CounterTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $connection = new Client(['host' => REDIS_HOST]);

        $this->adapter = Redis::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
