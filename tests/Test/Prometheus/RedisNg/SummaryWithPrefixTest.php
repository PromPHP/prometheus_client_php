<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisNg;

use Prometheus\Storage\Redis;
use Prometheus\Storage\RedisNg;
use Test\Prometheus\AbstractSummaryTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class SummaryWithPrefixTest extends AbstractSummaryTestCase
{
    public function configureAdapter(): void
    {
        $connection = new \Redis();
        $connection->connect(REDIS_HOST);

        $connection->setOption(\Redis::OPT_PREFIX, 'prefix:');

        $this->adapter = RedisNg::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
