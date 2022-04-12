<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCUIterator;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;

/**
 * @requires extension apcu
 */
class APCTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldNotClearWholeAPCacheOnFlush(): void
    {
        apcu_clear_cache();
        apcu_add("not a prometheus metric key", "data");

        $apc      = new APC();
        $registry = new CollectorRegistry($apc);
        $registry->getOrRegisterCounter("namespace", "counter", "counter help")->inc();
        $registry->getOrRegisterGauge("namespace", "gauge", "gauge help")->inc();
        $registry->getOrRegisterHistogram("namespace", "histogram", "histogram help")->observe(1);
        $apc->wipeStorage();

        $cacheEntries = iterator_to_array(new APCUIterator(null), true);
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
    public function itShouldUseConfiguredPrefix(): void
    {
        $apc = new APC('custom_prefix');
        $apc->wipeStorage();

        $registry = new CollectorRegistry($apc);

        $registry->getOrRegisterCounter('namespace', 'counter', 'counter help')->inc();
        $registry->getOrRegisterGauge('namespace', 'gauge', 'gauge help')->inc();
        $registry->getOrRegisterHistogram('namespace', 'histogram', 'histogram help')->observe(1);

        $entries = iterator_to_array(new APCUIterator('/^custom_prefix:.*:meta$/'), true);

        $cacheKeys = array_map(function ($item) {
            return $item['key'];
        }, $entries);

        self::assertArrayHasKey('custom_prefix:counter:namespace_counter:meta', $cacheKeys);
        self::assertArrayHasKey('custom_prefix:gauge:namespace_gauge:meta', $cacheKeys);
        self::assertArrayHasKey('custom_prefix:histogram:namespace_histogram:meta', $cacheKeys);
    }
}
