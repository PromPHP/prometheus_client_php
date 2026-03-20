<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractSummaryTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class SummaryTest extends AbstractSummaryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->wipeStorage();
    }
}
