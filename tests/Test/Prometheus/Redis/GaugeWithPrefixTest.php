<?php

namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis as RedisStorage;
use Redis;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class GaugeWithPrefixTest extends AbstractGaugeTest
{
    public function configureAdapter()
    {
        $connection = new Redis();
        $connection->connect(REDIS_HOST);

        $connection->setOption(Redis::OPT_PREFIX, 'prefix:');

        $this->adapter = RedisStorage::fromExistingConnection($connection);
        $this->adapter->flushRedis();
    }
}
