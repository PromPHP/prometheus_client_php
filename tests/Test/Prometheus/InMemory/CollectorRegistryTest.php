<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractCollectorRegistryTestCase;

class CollectorRegistryTest extends AbstractCollectorRegistryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->wipeStorage();
    }
}
