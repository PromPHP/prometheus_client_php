<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Predis\Client;
use Prometheus\Storage\Predis;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires predis
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Predis(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
