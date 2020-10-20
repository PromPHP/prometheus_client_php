<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractCollectorRegistryTest;

class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->flushMemory();
    }
}
