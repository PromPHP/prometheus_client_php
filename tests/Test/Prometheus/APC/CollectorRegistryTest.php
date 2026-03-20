<?php

declare(strict_types=1);

namespace Test\Prometheus\APC;

use Prometheus\Storage\APC;
use Test\Prometheus\AbstractCollectorRegistryTestCase;

/**
 * @requires extension apcu
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new APC();
        $this->adapter->wipeStorage();
    }
}
