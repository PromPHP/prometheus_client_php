<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisTxn;

use Prometheus\Storage\RedisTxn;
use Test\Prometheus\AbstractSummaryTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class SummaryWithPrefixTest extends AbstractSummaryTest
{
    public function configureAdapter(): void
    {
        $connection = new \Redis();
        $connection->connect(REDIS_HOST);

        $connection->setOption(\Redis::OPT_PREFIX, 'prefix:');

        $this->adapter = RedisTxn::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
