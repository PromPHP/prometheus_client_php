<?php

declare(strict_types=1);

namespace Test\Prometheus\APCng;

use Prometheus\Storage\APCng;
use Test\Prometheus\AbstractCounterTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension apcu
 */
class CounterTest extends AbstractCounterTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new APCng();
        $this->adapter->wipeStorage();
    }
}
