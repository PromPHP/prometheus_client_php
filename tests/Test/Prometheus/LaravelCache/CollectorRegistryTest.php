<?php

declare(strict_types=1);

namespace Test\Prometheus\LaravelCache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Prometheus\Storage\LaravelCacheAdapter;
use Test\Prometheus\AbstractCollectorRegistryTest;

class CollectorRegistryTest extends AbstractCollectorRegistryTest
{
    public function configureAdapter(): void
    {
        $arrayStore = new ArrayStore();

        $this->adapter = new LaravelCacheAdapter(new Repository($arrayStore));
        $this->adapter->wipeStorage();
    }
}
