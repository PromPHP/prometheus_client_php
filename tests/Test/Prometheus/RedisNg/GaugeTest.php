<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisNg;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\RedisNg;
use Test\Prometheus\AbstractGaugeTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class GaugeTest extends AbstractGaugeTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new RedisNg(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldUpdateMetadataOnSubsequentWritesWithoutWipe(): void
    {
        $registry = new CollectorRegistry($this->adapter, false);

        $gaugeV1 = $registry->getOrRegisterGauge('test', 'some_metric', 'help', ['foo']);
        $gaugeV1->set(42, ['val1']);

        $metrics = $this->adapter->collect();
        self::assertSame(['foo'], $metrics[0]->getLabelNames());

        $registry = new CollectorRegistry($this->adapter, false);

        $gaugeV2 = $registry->getOrRegisterGauge('test', 'some_metric', 'help', ['foo', 'bar']);
        $gaugeV2->set(99, ['val1', 'val2']);

        $metrics = $this->adapter->collect();
        self::assertSame(['foo', 'bar'], $metrics[0]->getLabelNames());
    }
}
