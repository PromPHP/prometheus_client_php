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
        file_put_contents(
            self::RESULT_FILENAME,
            implode(',', TestCaseResult::getCsvHeaders()) . PHP_EOL
        );
        parent::setUpBeforeClass();
    }

    /**
     * @return array
     */
    public function benchmarkProvider(): array
    {
        return [
//            [AdapterType::REDISNG, MetricType::COUNTER, 1000, 10],
//            [AdapterType::REDISNG, MetricType::COUNTER, 2000, 10],
//            [AdapterType::REDISNG, MetricType::COUNTER, 5000, 10],
//            [AdapterType::REDISNG, MetricType::COUNTER, 10000, 10],
//            [AdapterType::REDISNG, MetricType::GAUGE, 1000, 10],
//            [AdapterType::REDISNG, MetricType::GAUGE, 2000, 10],
//            [AdapterType::REDISNG, MetricType::GAUGE, 5000, 10],
//            [AdapterType::REDISNG, MetricType::GAUGE, 10000, 10],
//            [AdapterType::REDISNG, MetricType::HISTOGRAM, 1000, 10],
//            [AdapterType::REDISNG, MetricType::HISTOGRAM, 2000, 10],
//            [AdapterType::REDISNG, MetricType::HISTOGRAM, 5000, 10],
//            [AdapterType::REDISNG, MetricType::HISTOGRAM, 10000, 10],
//            [AdapterType::REDISNG, MetricType::SUMMARY, 1000, 10],
//            [AdapterType::REDISNG, MetricType::SUMMARY, 2000, 10],
//            [AdapterType::REDISNG, MetricType::SUMMARY, 5000, 10],
//            [AdapterType::REDISNG, MetricType::SUMMARY, 10000, 10],
//            [AdapterType::REDISTXN, MetricType::COUNTER, 1000, 10],
//            [AdapterType::REDISTXN, MetricType::COUNTER, 2000, 10],
//            [AdapterType::REDISTXN, MetricType::COUNTER, 5000, 10],
//            [AdapterType::REDISTXN, MetricType::COUNTER, 10000, 10],
//            [AdapterType::REDISTXN, MetricType::GAUGE, 1000, 10],
//            [AdapterType::REDISTXN, MetricType::GAUGE, 2000, 10],
//            [AdapterType::REDISTXN, MetricType::GAUGE, 5000, 10],
//            [AdapterType::REDISTXN, MetricType::GAUGE, 10000, 10],
//            [AdapterType::REDISTXN, MetricType::HISTOGRAM, 1000, 10],
//            [AdapterType::REDISTXN, MetricType::HISTOGRAM, 2000, 10],
//            [AdapterType::REDISTXN, MetricType::HISTOGRAM, 5000, 10],
//            [AdapterType::REDISTXN, MetricType::HISTOGRAM, 10000, 10],
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
        ini_set('memory_limit','1024M');

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
