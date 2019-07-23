<?php

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractCollectorRegistryTest;

class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter()
    {
        $this->adapter = new InMemory();
        $this->adapter->flushMemory();
    }
}
