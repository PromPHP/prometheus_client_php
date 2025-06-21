<?php

declare(strict_types=1);

namespace Test\Prometheus\APCng;

use Prometheus\Storage\APCng;
use Test\Prometheus\AbstractSummaryTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension apcu
 */
class SummaryTest extends AbstractSummaryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new APCng();
        $this->adapter->wipeStorage();
    }
}
