<?php

declare(strict_types=1);

namespace Test\Benchmark;

use PHPUnit\Framework\TestCase;
use Test\Benchmark\TestCase as BenchmarkTestCase;

class BenchmarkTest extends TestCase
{
    /**
     * @var string
     */
    public const RESULT_FILENAME = 'benchmark.csv';

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        file_put_contents(self::RESULT_FILENAME, implode(',', [
            'adapter',
            'metric',
            'num-keys',
            'num-samples',
            'write-p50',
            'write-p75',
            'write-p95',
            'write-p99',
            'write-min',
            'write-max',
            'write-avg',
            'render-p50',
            'render-p75',
            'render-p95',
            'render-p99',
            'render-min',
            'render-max',
            'render-avg',
        ]));
        parent::setUpBeforeClass();
    }

    /**
     * @return array
     */
    public function benchmarkProvider(): array
    {
        return [
            [AdapterType::REDISNG, MetricType::SUMMARY, 1000, 10],
            [AdapterType::REDISNG, MetricType::SUMMARY, 2000, 10],
            [AdapterType::REDISNG, MetricType::SUMMARY, 5000, 10],
            [AdapterType::REDISNG, MetricType::SUMMARY, 10000, 10],
            [AdapterType::REDISTXN, MetricType::SUMMARY, 1000, 10],
            [AdapterType::REDISTXN, MetricType::SUMMARY, 2000, 10],
            [AdapterType::REDISTXN, MetricType::SUMMARY, 5000, 10],
            [AdapterType::REDISTXN, MetricType::SUMMARY, 10000, 10],
        ];
    }

    /**
     * @dataProvider benchmarkProvider
     * @group Benchmark
     * @param int $adapter
     * @param int $metric
     * @param int $numKeys
     * @param int $numSamples
     * @return void
     * @test
     */
    public function benchmark(
        int $adapter,
        int $metric,
        int $numKeys,
        int $numSamples
    ): void
    {
        // Create and execute test case
        $testCase = BenchmarkTestCase::newBuilder()
            ->withAdapterType($adapter)
            ->withMetricType($metric)
            ->withReportType(ReportType::CSV)
            ->withNumKeys($numKeys)
            ->withNumSamples($numSamples)
            ->build();

        // Sanity check test structure
        $this->assertEquals($adapter, $testCase->getAdapterType());
        $this->assertEquals($metric, $testCase->getMetricType());
        $this->assertEquals($numKeys, $testCase->getNumKeys());
        $this->assertEquals($numSamples, $testCase->getNumSamples());

        // Record results
        $result = $testCase->execute();
        file_put_contents(self::RESULT_FILENAME, $result->report() . PHP_EOL, FILE_APPEND);
    }
}
