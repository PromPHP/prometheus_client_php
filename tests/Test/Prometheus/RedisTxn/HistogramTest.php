<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisTxn;

use Prometheus\Storage\RedisTxn;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class HistogramTest extends AbstractHistogramTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new RedixTxn(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
