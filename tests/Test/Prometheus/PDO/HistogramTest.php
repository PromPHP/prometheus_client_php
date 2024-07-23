<?php

declare(strict_types=1);

namespace Test\Prometheus\PDO;

use Prometheus\Storage\PDO;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class HistogramTest extends AbstractHistogramTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new PDO(new \PDO('sqlite::memory:'));
        $this->adapter->wipeStorage();
    }
}
