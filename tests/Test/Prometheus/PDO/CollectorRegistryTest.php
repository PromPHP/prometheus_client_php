<?php

declare(strict_types=1);

namespace Test\Prometheus\PDO;

use Prometheus\Storage\PDO;
use Test\Prometheus\AbstractCollectorRegistryTest;

class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new PDO(new \PDO('sqlite::memory:'));
        $this->adapter->wipeStorage();
    }
}
