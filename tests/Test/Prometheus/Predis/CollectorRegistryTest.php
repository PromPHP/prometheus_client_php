<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Predis\Client;
use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension redis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $connection = new Client(['host' => REDIS_HOST]);

        $this->adapter = Redis::fromExistingConnection($connection);
        $this->adapter->wipeStorage();
    }
}
