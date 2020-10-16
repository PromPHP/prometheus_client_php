<?php

declare(strict_types=1);

namespace Test\Prometheus\APC;

use Prometheus\Storage\APC;
use Test\Prometheus\AbstractCollectorRegistryTest;

/**
 * @requires extension apcu
 */
class CollectorRegistryTest extends AbstractCollectorRegistryTest
{

    public function configureAdapter(): void
    {
        $this->adapter = new APC();
        $this->adapter->flushAPC();
    }
}
