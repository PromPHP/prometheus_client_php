<?php

declare(strict_types=1);

namespace Test\Prometheus\Redis;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractCounterTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class CounterTest extends AbstractCounterTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Redis(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldUpdateMetadataOnSubsequentWritesWithoutWipe(): void
    {
        $registry = new CollectorRegistry($this->adapter, false);

        $counterV1 = $registry->getOrRegisterCounter('test', 'some_metric', 'help', ['foo']);
        $counterV1->inc(['val1']);

        $metrics = $this->adapter->collect();
        self::assertSame(['foo'], $metrics[0]->getLabelNames());

        $registry = new CollectorRegistry($this->adapter, false);

        $counterV2 = $registry->getOrRegisterCounter('test', 'some_metric', 'help', ['foo', 'bar']);
        $counterV2->inc(['val1', 'val2']);

        $metrics = $this->adapter->collect();
        self::assertSame(['foo', 'bar'], $metrics[0]->getLabelNames());
    }
}
