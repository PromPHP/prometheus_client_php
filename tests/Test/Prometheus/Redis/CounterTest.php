<?php

namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis as RedisStorage;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class CounterTest extends AbstractCounterTest
{
    public function configureAdapter()
    {
        $this->adapter = new RedisStorage(['host' => REDIS_HOST]);
        $this->adapter->flushRedis();
    }
}
