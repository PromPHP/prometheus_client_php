<?php

namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis as RedisStorage;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension redis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter()
    {
        $this->adapter = new RedisStorage(['host' => REDIS_HOST]);
        $this->adapter->flushRedis();
    }
}
