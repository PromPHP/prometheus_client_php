<?php

declare(strict_types=1);

namespace Test\Prometheus\LaravelCache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Prometheus\Storage\LaravelCacheAdapter;
use Test\Prometheus\AbstractSummaryTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class SummaryTest extends AbstractSummaryTest
{
    public function configureAdapter(): void
    {
        $arrayStore = new ArrayStore();

        $this->adapter = new LaravelCacheAdapter(new Repository($arrayStore));
        $this->adapter->wipeStorage();
    }
}
