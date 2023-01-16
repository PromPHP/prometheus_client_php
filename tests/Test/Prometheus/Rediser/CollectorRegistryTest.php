<?php

declare(strict_types=1);

namespace Test\Prometheus\Rediser;

use Prometheus\Storage\Rediser;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension redis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Rediser(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
