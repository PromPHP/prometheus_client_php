<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCuIterator;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;

/**
 * @requires extension apcu
 */
class APCngTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldNotClearWholeAPCacheOnFlush(): void
    {
        apcu_clear_cache();
        apcu_add("not a prometheus metric key", "data");

        $apc      = new APCng();
        $registry = new CollectorRegistry($apc);
        $registry->getOrRegisterCounter("namespace", "counter", "counter help")->inc();
        $registry->getOrRegisterGauge("namespace", "gauge", "gauge help")->inc();
        $registry->getOrRegisterHistogram("namespace", "histogram", "histogram help")->observe(1);
        $registry->getOrRegisterSummary("namespace", "summary", "summary help")->observe(1);
        $apc->wipeStorage();

        $cacheEntries = iterator_to_array(new APCuIterator(null), true);
        $cacheMap     = array_map(function ($item) {
            return $item['value'];
        }, $cacheEntries);

        self::assertThat(
            $cacheMap,
            self::equalTo([
                'not a prometheus metric key' => 'data',
            ])
        );
    }

    /**
     * @test
     */
    public function nonStringLabelValuesAreCastToStrings(): void
    {
        apcu_clear_cache();

        $adapter = new APCng();
        $registry = new CollectorRegistry($adapter);
        $registry->getOrRegisterCounter(
            'ns',
            'int_label_values',
            'test int label values',
            ['int_label'],
        )->inc([3]);

        $jsonLabels = json_encode(["3"]);
        $encodedLabels = base64_encode($jsonLabels);

        $counter = apcu_fetch("prom:counter:ns_int_label_values:{$encodedLabels}:value");
        $this->assertSame(1000, $counter);
    }

    /**
     * @test
     */
    public function itShouldUseConfiguredPrefix(): void
    {
        $apc = new APCng('custom_prefix');
        $apc->wipeStorage();

        $registry = new CollectorRegistry($apc);

        $registry->getOrRegisterCounter('namespace', 'counter', 'counter help')->inc();
        $registry->getOrRegisterGauge('namespace', 'gauge', 'gauge help')->inc();
        $registry->getOrRegisterHistogram('namespace', 'histogram', 'histogram help')->observe(1);
        $registry->getOrRegisterSummary("namespace", "summary", "summary help")->observe(1);

        $entries = iterator_to_array(new APCuIterator('/^custom_prefix:.*:meta$/'), true);

        $cacheKeys = array_map(function ($item) {
            return $item['key'];
        }, $entries);

        self::assertArrayHasKey('custom_prefix:counter:namespace_counter:meta', $cacheKeys);
        self::assertArrayHasKey('custom_prefix:gauge:namespace_gauge:meta', $cacheKeys);
        self::assertArrayHasKey('custom_prefix:histogram:namespace_histogram:meta', $cacheKeys);
        self::assertArrayHasKey('custom_prefix:summary:namespace_summary:meta', $cacheKeys);
    }

    /**
     * @test
     * Test that orphaned items added after the metainfo cache has been created get "picked up" and stored
     * in the metainfo cache.
     */
    public function itShouldNotNeedToHaveTTLForMetaInfoCache(): void
    {
        $apc = new APCng();
        $registry = $this->metainfoCacheTestPartOne($apc);

        $metrics = $apc->collect();
        $metricHelpStrings = array_map(function ($item): string {
            return $item->getHelp();
        }, $metrics);

        // all metrics are available after each collect() call
        self::assertContains('gauge help', $metricHelpStrings);
        self::assertContains('counter help', $metricHelpStrings);
        self::assertContains('histogram help', $metricHelpStrings);
        self::assertContains('summary help', $metricHelpStrings);

        // Let the TTL expire, the hidden metric will appear. Increment counter before & after cache expiry to prove all inc() calls were processed
        $registry->getOrRegisterCounter("namespace", "counter", "counter help")->incBy(3);
        $registry->getOrRegisterCounter("namespace", "counter", "counter help")->incBy(5);
        $metrics = $apc->collect();
        foreach ($metrics as $metric) {
            if ('counter' === $metric->getType()) {
                self::assertEquals(9, $metric->getSamples()[0]->getValue());
            }
        }
    }

    /* Helper function for metainfo cache testing, reduces copypaste */
    private function metainfoCacheTestPartOne(APCng $apc): CollectorRegistry
    {
        apcu_clear_cache();

        $registry = new CollectorRegistry($apc);
        $registry->getOrRegisterGauge("namespace", "gauge", "gauge help")->inc();
        $registry->getOrRegisterHistogram("namespace", "histogram", "histogram help")->observe(1);
        $registry->getOrRegisterSummary("namespace", "summary", "summary help")->observe(1);

        $metrics = $apc->collect();
        $metricHelpStrings = array_map(function ($item): string {
            return $item->getHelp();
        }, $metrics);
        self::assertContains('gauge help', $metricHelpStrings);
        self::assertNotContains('counter help', $metricHelpStrings);
        self::assertContains('histogram help', $metricHelpStrings);
        self::assertContains('summary help', $metricHelpStrings);

        $registry->getOrRegisterCounter("namespace", "counter", "counter help")->inc();
        return $registry;
    }
}
