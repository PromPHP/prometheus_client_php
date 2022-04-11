<?php

declare(strict_types=1);

namespace Test\Prometheus\APCng;

use Prometheus\Storage\APCng;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension apcu
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new APCng();
        $this->adapter->wipeStorage();
    }
}
