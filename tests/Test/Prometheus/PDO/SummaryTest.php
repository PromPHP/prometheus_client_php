<?php

declare(strict_types=1);

namespace Test\Prometheus\PDO;

use Prometheus\Storage\PDO;
use Test\Prometheus\AbstractSummaryTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class SummaryTest extends AbstractSummaryTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new PDO(new \PDO('sqlite::memory:'));
        $this->adapter->wipeStorage();
    }
}
