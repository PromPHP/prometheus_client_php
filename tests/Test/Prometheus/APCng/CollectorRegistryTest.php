<?php

declare(strict_types=1);

namespace Test\Prometheus\APCng;

use Prometheus\Storage\APCng;
use Test\Prometheus\AbstractCollectorRegistryTestCase;

/**
 * @requires extension apcu
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new APCng();
        $this->adapter->wipeStorage();
    }
}
