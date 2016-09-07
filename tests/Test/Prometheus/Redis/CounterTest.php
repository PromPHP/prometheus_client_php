<?php


namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterTest extends AbstractCounterTest
{

    public function configureAdapter()
    {
        $this->adapter = new Redis(array('host' => REDIS_HOST));
        $this->adapter->flushRedis();
    }
}
