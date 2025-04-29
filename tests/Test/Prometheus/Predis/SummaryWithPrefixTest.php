<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Predis\Client;
use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractSummaryTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 *
 * @requires extension redis
 */
class SummaryWithPrefixTest extends AbstractSummaryTest
{
    public function configureAdapter(): void
    {
        $connection = new Client(['host' => REDIS_HOST, 'prefix' => 'prefix:']);

        $this->adapter = Redis::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
