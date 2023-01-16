<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisTxn;

use Prometheus\Storage\RedisTxn;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class HistogramWithPrefixTest extends AbstractHistogramTest
{
    public function configureAdapter(): void
    {
        $connection = new \Redis();
        $connection->connect(REDIS_HOST);

        $connection->setOption(\Redis::OPT_PREFIX, 'prefix:');

        $this->adapter = RedixTxn::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
