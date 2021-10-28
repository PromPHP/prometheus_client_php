<?php

declare(strict_types=1);

namespace Test\Performance;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use PHPUnit\Framework\TestCase;

class PerformanceTest extends TestCase
{

    /* Number of items stored in APC, and number of metrics tracked by Prometheus, respectively */
    const NUM_APC_KEYS = [ 1000, 10 * 1000, 100 * 1000, 1000 * 1000 ];
    const NUM_PROM_METRICS = [ 50, 500, 2500 ];

    /**
     * @param int $num How many random (non-prometheus) keys to prepopulate into APC
     */
    private function initializeAPC(int $num): void
    {
        apcu_clear_cache();

        // fill APC with random keys
        $pid = getmypid();
        for ($i = 0; $i < $num; $i++) {
            $k = md5($pid . microtime(true) . $i);
            apcu_add($k, $i);
        }
    }

    /**
     * @test
     * @group Performance
     * Compare the speed of creating new metrics between both engines.
     * Creating new items in APCng gets slower like O(n) as NUM_PROM_ITEMS increases; is unaffected by NUM_APC_ITEMS
     */
    public function testCreates(): void
    {
        $results = [];
        foreach ($this::NUM_APC_KEYS as $num_apc_keys) {
            foreach ($this::NUM_PROM_METRICS as $num_metrics) {
                $this->initializeAPC($num_apc_keys);
                foreach (['Prometheus\Storage\APC', 'Prometheus\Storage\APCng'] as $driverClass) {
                    $begin = microtime(true);
                    $x = new TestEngineSpeed($driverClass, $num_metrics);
                    $x->doCreates();
                    $elapsedTime = microtime(true) - $begin;
                    fprintf(STDOUT, "%7d APC keys and %4d metrics; Creating metrics took %7.4f seconds. (%s)\n", $num_apc_keys, $num_metrics, $elapsedTime, $driverClass);
                    $results["{$num_apc_keys}keys_{$num_metrics}metrics"][$driverClass] = $elapsedTime;
                }
            }
        }

        // should be around the same speed or slightly faster; minimum of 70% the APC speed to account for variations between test runs
        foreach ($results as $resultName => $elapsedTimeByDrivername) {
            self::assertThat(
                $elapsedTimeByDrivername['Prometheus\Storage\APCng'] * 0.7,
                self::lessThan($elapsedTimeByDrivername['Prometheus\Storage\APC']),
                "70% of APCng time of {$elapsedTimeByDrivername['Prometheus\Storage\APCng']} is not less than APC time of {$elapsedTimeByDrivername['Prometheus\Storage\APC']}"
            );
        }
    }

    /**
     * @test
     * @group Performance
     * Compare the speed of incrementing existing metrics beteween both engines.
     * Incrementing items should be O(1) regardless of NUM_APC_ITEMS or NUM_PROM_ITEMS
     */
    public function testIncrements(): void
    {
        $results = [];
        foreach ($this::NUM_APC_KEYS as $num_apc_keys) {
            foreach ($this::NUM_PROM_METRICS as $num_metrics) {
                $this->initializeAPC($num_apc_keys);
                foreach (['Prometheus\Storage\APC', 'Prometheus\Storage\APCng'] as $driverClass) {
                    // create the prometheus items on the first pass, then increment them (and time it) on the second pass
                    $x = new TestEngineSpeed($driverClass, $num_metrics);
                    $x->doCreates();
                    $begin = microtime(true);
                    $x->doCreates();
                    $elapsedTime = microtime(true) - $begin;
                    fprintf(STDOUT, "%7d APC keys and %4d metrics; Incrementing metrics took %7.4f seconds. (%s)\n", $num_apc_keys, $num_metrics, $elapsedTime, $driverClass);
                    $results["{$num_apc_keys}keys_{$num_metrics}metrics"][$driverClass] = $elapsedTime;
                }
            }
        }

        // should be around the same speed or slightly faster; minimum of 70% the APC speed to account for variations between test runs
        foreach ($results as $resultName => $elapsedTimeByDrivername) {
            self::assertThat(
                $elapsedTimeByDrivername['Prometheus\Storage\APCng'] * 0.7,
                self::lessThan($elapsedTimeByDrivername['Prometheus\Storage\APC']),
                "70% of APCng time of {$elapsedTimeByDrivername['Prometheus\Storage\APCng']} is not less than APC time of {$elapsedTimeByDrivername['Prometheus\Storage\APC']}"
            );
        }
    }

    /**
     * @test
     * @group Performance
     * Compare the speed of calling wipeStorage() between both engines.
     * Clearing cache should be unaffected by the number of objects stored in APC
     */
    public function testWipeStorage(): void
    {
        $results = [];
        foreach ($this::NUM_APC_KEYS as $num_apc_keys) {
            foreach ($this::NUM_PROM_METRICS as $num_metrics) {
                $this->initializeAPC($num_apc_keys);
                foreach (['Prometheus\Storage\APC', 'Prometheus\Storage\APCng'] as $driverClass) {
                    $apc      = new $driverClass();
                    $registry = new CollectorRegistry($apc);
                    for ($i = 0; $i < $num_metrics; $i++) {
                        $registry->getOrRegisterCounter("namespace", "counter{$i}", "counter help")->inc();
                        $registry->getOrRegisterGauge("namespace", "gauge{$i}", "gauge help")->inc();
                        $registry->getOrRegisterHistogram("namespace", "histogram{$i}", "histogram help")->observe(1);
                        $registry->getOrRegisterSummary("namespace", "summary{$i}", "summary help")->observe(1);
                    }
                    $begin = microtime(true);
                    $x = new TestEngineSpeed($driverClass, $num_metrics);
                    $x->doWipeStorage();
                    $elapsedTime = microtime(true) - $begin;
                    fprintf(STDOUT, "%7d APC keys and %4d metrics; Wiping stored metrics took %7.4f seconds. (%s)\n", $num_apc_keys, $num_metrics, $elapsedTime, $driverClass);
                    $results["{$num_apc_keys}keys_{$num_metrics}metrics"][$driverClass] = $elapsedTime;
                }
            }
        }

        // should be around the same speed or slightly faster; minimum of 70% the APC speed to account for variations between test runs
        foreach ($results as $resultName => $elapsedTimeByDrivername) {
            self::assertThat(
                $elapsedTimeByDrivername['Prometheus\Storage\APCng'] * 0.7,
                self::lessThan($elapsedTimeByDrivername['Prometheus\Storage\APC']),
                "70% of APCng time of {$elapsedTimeByDrivername['Prometheus\Storage\APCng']} is not less than APC time of {$elapsedTimeByDrivername['Prometheus\Storage\APC']}"
            );
        }
    }

    /**
     * @test
     * @group Performance
     * Compare the speed of collecting the metrics into a report between both engines.
     * Enumerating all values in cache should be unaffected by the number of objects stored in APC
     */
    public function testCollect(): void
    {
        $results = [];
        foreach ($this::NUM_APC_KEYS as $num_apc_keys) {
            foreach ($this::NUM_PROM_METRICS as $num_metrics) {
                $this->initializeAPC($num_apc_keys);
                foreach (['Prometheus\Storage\APC', 'Prometheus\Storage\APCng'] as $driverClass) {
                    $apc      = new $driverClass();
                    $registry = new CollectorRegistry($apc);
                    for ($i = 0; $i < $num_metrics; $i++) {
                        $registry->getOrRegisterCounter("namespace", "counter{$i}", "counter help")->inc();
                        $registry->getOrRegisterGauge("namespace", "gauge{$i}", "gauge help")->inc();
                        $registry->getOrRegisterHistogram("namespace", "histogram{$i}", "histogram help")->observe(1);
                        $registry->getOrRegisterSummary("namespace", "summary{$i}", "summary help")->observe(1);
                    }
                    $begin = microtime(true);
                    $x = new TestEngineSpeed($driverClass, $num_metrics);
                    $x->doCollect();
                    $elapsedTime = microtime(true) - $begin;
                    fprintf(STDOUT, "%7d APC keys and %4d metrics; Collecting/Reporting metrics took %8.4f seconds. (%s)\n", $num_apc_keys, $num_metrics, $elapsedTime, $driverClass);
                    $results["{$num_apc_keys}keys_{$num_metrics}metrics"][$driverClass] = $elapsedTime;
                    $results["{$num_apc_keys}keys_{$num_metrics}metrics"]['n_metrics'] = $num_metrics;
                }
            }
        }

        // should be strictly faster than APC across all runs, by an O(lg2(num_metrics)) factor
        foreach ($results as $resultName => $elapsedTimeByDrivername) {
            self::assertThat(
                $elapsedTimeByDrivername['Prometheus\Storage\APCng'] * (log($elapsedTimeByDrivername['n_metrics'], 2) / 2),
                self::lessThan($elapsedTimeByDrivername['Prometheus\Storage\APC']),
                "70% of APCng time of {$elapsedTimeByDrivername['Prometheus\Storage\APCng']} is not less than APC time of {$elapsedTimeByDrivername['Prometheus\Storage\APC']}"
            );
        }
    }
}
