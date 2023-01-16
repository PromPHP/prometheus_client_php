<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisTxn;

use Prometheus\Storage\RedisTxn;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension redis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new RedisTxn(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
