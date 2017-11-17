<?php


namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class HistogramTest extends AbstractHistogramTest
{
    public function configureAdapter()
    {
        $this->adapter = new Redis(array('host' => REDIS_HOST));
        $this->adapter->flushRedis();
    }
}
