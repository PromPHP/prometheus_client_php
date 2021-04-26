<?php

declare(strict_types=1);

namespace Test\Prometheus;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prometheus\Summary;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractSummaryTest extends TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;


    /**
     * @var string
     */
    private $savedPrecision;

    private const HIGH_PRECISION = "17";

    public function setUp(): void
    {
        $this->configureAdapter();
        $savedPrecision = ini_get('serialize_precision');
        if (!is_string($savedPrecision)) {
            $savedPrecision = '-1';
        }
        $this->savedPrecision = $savedPrecision;
        ini_set('serialize_precision', self::HIGH_PRECISION);
    }

    public function tearDown(): void
    {
        ini_set('serialize_precision', $this->savedPrecision);
    }

    abstract public function configureAdapter(): void;

    /**
     * @test
     */
    public function itShouldObserveWithLabels(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            ['foo', 'bar'],
            60,
            [0.1, 0.5, 0.9]
        );
        foreach (range(0, 10) as $i) {
            $summary->observe($i, ['lalal', 'lululu']);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => ['lalal', 'lululu', 0.1],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => ['lalal', 'lululu', 0.5],
                                    'value' => 5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => ['lalal', 'lululu', 0.9],
                                    'value' => 9,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 11,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 55,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveWithoutLabelWhenNoLabelsAreDefined(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            60,
            [0.1, 0.5, 0.9]
        );
        foreach (range(0, 10) as $i) {
            $summary->observe($i);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.1],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.5],
                                    'value' => 5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.9],
                                    'value' => 9,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 11,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 55,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveValuesOfTypeDouble(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            60,
            [0.1, 0.5, 0.9]
        );
        foreach (range(0, 1,0.1) as $i) {
            $summary->observe($i);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.1],
                                    'value' => 0.1,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.5],
                                    'value' => 0.5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.9],
                                    'value' => 0.9,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 11,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 5.5,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveComputeMedianCorrectlyWithEvenSerieLength(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            60,
            [0.1, 0.5, 0.9]
        );
        foreach (range(1,10) as $i) {
            $summary->observe($i);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.1],
                                    'value' => 1.9,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.5],
                                    'value' => 5.5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.9],
                                    'value' => 9.1,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 10,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 55,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveValuesOfTypeDoubleWithUnusualPrecision(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            60,
            [0.5]
        );
        ini_set("serialize_precision", "17");
        $summary->observe(1.1 * 2 ** 53);
        self::assertThat(
            $this->adapter->collect(),
            self::logicalNot(self::isEmpty())
        );
    }

    /**
     * @test
     */
    public function itShouldProvideDefaultBuckets(): void
    {
        // .01, 05, .5, .95, .99

        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            60
        );
        foreach (range(0, 100) as $i) {
            $summary->observe($i);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.01],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.05],
                                    'value' => 5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.5],
                                    'value' => 50,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.95],
                                    'value' => 95,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.99],
                                    'value' => 99,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 101,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 5050,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldIgnoreSerieItemsOlderThanMaxAgeSeconds(): void
    {
        $summary = new Summary(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            1,
            [0.1, 0.5, 0.9]
        );
        $summary->observe(100);
        usleep(1050*1000);
        foreach (range(1,10) as $i) {
            $summary->observe($i);
        }
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Summary::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.1],
                                    'value' => 1.9,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.5],
                                    'value' => 5.5,
                                ],
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => ['quantile'],
                                    'labelValues' => [0.9],
                                    'value' => 9.1,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 10,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 55,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenTheQuantilesAreNotIncreasing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary quantiles must be in increasing order');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', [], 60, [0.5, 0.5]);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenTheQuantileIsLowerThanZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected number between 0 and 1.');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', [], 60, [0]);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenTheQuantileIsGreaterThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected number between 0 and 1.');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', [], 60, [1]);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenThereIsLessThanOneQuantile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary must have at least one quantile');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', [], 60, []);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenMaxAgeSecondsLowerThanZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected number greater than 0.');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', [], -1, [0.5]);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenThereIsALabelNamedQuantile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary cannot have a label named');
        new Summary($this->adapter, 'test', 'some_metric', 'this is for testing', ['quantile'], 60, [0.5]);
    }

    /**
     * @test
     */
    public function itShouldRejectInvalidMetricsNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metric name');
        new Summary($this->adapter, 'test', 'some invalid metric', 'help', [], 60, [0.5]);
    }

    /**
     * @test
     */
    public function itShouldRejectInvalidLabelNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid label name');
        new Summary($this->adapter, 'test', 'some_metric', 'help', ['invalid label'], 60, [0.5]);
    }

    /**
     * @test
     * @dataProvider labelValuesDataProvider
     *
     * @param mixed $value The label value
     */
    public function isShouldAcceptAnySequenceOfBasicLatinCharactersForLabelValues($value): void
    {
        $label = 'foo';
        $summary = new Summary($this->adapter, 'test', 'some_metric', 'help', [$label], 60, [0.5]);
        $summary->observe(1, [$value]);

        $metrics = $this->adapter->collect();
        self::assertCount(1, $metrics);
        self::assertContainsOnlyInstancesOf(MetricFamilySamples::class, $metrics);

        $metric = reset($metrics);
        self::assertInstanceOf(MetricFamilySamples::class, $metric);
        $samples = $metric->getSamples();
        self::assertContainsOnlyInstancesOf(Sample::class, $samples);

        foreach ($samples as $sample) {
            $labels = array_combine(
                array_merge($metric->getLabelNames(), $sample->getLabelNames()),
                $sample->getLabelValues()
            );
            self::assertIsArray($labels);
            self::assertEquals($value, $labels[$label]);
        }
    }

    /**
     * @return mixed[]
     * @see isShouldAcceptArbitraryLabelValues
     */
    public function labelValuesDataProvider(): array
    {
        $cases = [];
        // Basic Latin
        // See https://en.wikipedia.org/wiki/List_of_Unicode_characters#Basic_Latin
        for ($i = 32; $i <= 121; $i++) {
            $cases['ASCII code ' . $i] = [chr($i)];
        }
        return $cases;
    }
}
