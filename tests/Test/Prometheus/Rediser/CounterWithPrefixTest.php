<?php

declare(strict_types=1);

namespace Test\Prometheus\Rediser;

use Prometheus\Storage\Rediser;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class CounterWithPrefixTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $connection = new \Redis();
        $connection->connect(REDIS_HOST);

        $connection->setOption(\Redis::OPT_PREFIX, 'prefix:');

        $this->adapter = Rediser::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
