<?php

declare(strict_types=1);

namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractCollectorRegistryTestCase;

/**
 * @requires extension redis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new Redis(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
