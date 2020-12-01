<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCuIterator;
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
        $apc->flushAPC();

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
}
