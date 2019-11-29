<?php

namespace Test\Prometheus;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractHistogramTest extends TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;

    public function setUp(): void
    {
        $this->configureAdapter();
    }

    abstract public function configureAdapter();

    /**
     * @test
     */
    public function itShouldObserveWithLabels()
    {
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            ['foo', 'bar'],
            [100, 200, 300]
        );
        $histogram->observe(123, ['lalal', 'lululu']);
        $histogram->observe(245, ['lalal', 'lululu']);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['lalal', 'lululu', 100],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['lalal', 'lululu', 200],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['lalal', 'lululu', 300],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['lalal', 'lululu', '+Inf'],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 368,
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
    public function itShouldObserveWithoutLabelWhenNoLabelsAreDefined()
    {
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            [100, 200, 300]
        );
        $histogram->observe(245);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [100],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [200],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [300],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['+Inf'],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 245,
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
    public function itShouldObserveValuesOfTypeDouble()
    {
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            [],
            [0.1, 0.2, 0.3]
        );
        $histogram->observe(0.11);
        $histogram->observe(0.3);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.1],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.2],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.3],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['+Inf'],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 0.41,
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
    public function itShouldProvideDefaultBuckets()
    {
        // .005, .01, .025, .05, .075, .1, .25, .5, .75, 1.0, 2.5, 5.0, 7.5, 10.0

        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            []
        );
        $histogram->observe(0.11);
        $histogram->observe(0.03);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.005],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.01],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.025],
                                    'value' => 0,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.05],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.075],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.1],
                                    'value' => 1,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.25],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.5],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [0.75],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [1.0],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [2.5],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [5],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [7.5],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => [10],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => ['le'],
                                    'labelValues' => ['+Inf'],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 2,
                                ],
                                [
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 0.14,
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
    public function itShouldThrowAnExceptionWhenTheBucketSizesAreNotIncreasing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Histogram buckets must be in increasing order');
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', [], [1, 1]);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenThereIsLessThanOneBucket()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Histogram must have at least one bucket');
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', [], []);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenThereIsALabelNamedLe()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Histogram cannot have a label named');
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', ['le'], [1]);
    }

    /**
     * @test
     */
    public function itShouldRejectInvalidMetricsNames()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metric name');
        new Histogram($this->adapter, 'test', 'some invalid metric', 'help', [], [1]);
    }

    /**
     * @test
     */
    public function itShouldRejectInvalidLabelNames()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid label name');
        new Histogram($this->adapter, 'test', 'some_metric', 'help', ['invalid label'], [1]);
    }

    /**
     * @test
     * @dataProvider labelValuesDataProvider
     *
     * @param mixed $value The label value
     */
    public function isShouldAcceptAnySequenceOfBasicLatinCharactersForLabelValues($value)
    {
        $label = 'foo';
        $histogram = new Histogram($this->adapter, 'test', 'some_metric', 'help', [$label], [1]);
        $histogram->observe(1, [$value]);

        $metrics = $this->adapter->collect();
        $this->assertIsArray($metrics);
        $this->assertCount(1, $metrics);
        $this->assertContainsOnlyInstancesOf(MetricFamilySamples::class, $metrics);

        $metric = reset($metrics);
        $samples = $metric->getSamples();
        $this->assertContainsOnlyInstancesOf(Sample::class, $samples);

        foreach ($samples as $sample) {
            $labels = array_combine(
                array_merge($metric->getLabelNames(), $sample->getLabelNames()),
                $sample->getLabelValues()
            );
            $this->assertEquals($value, $labels[$label]);
        }
    }

    /**
     * @test
     */
    public function itShouldBeAbleToGenerateExponentialBucketsGivenSpecificBounds()
    {
        $start = 0.05;
        $growthFactor = 1.5;
        $numberOfbuckets = 14;

        $generatedBuckets = Histogram::exponentialBuckets($start, $growthFactor, $numberOfbuckets);

        $expectedBuckets = [
            0.05,
            0.075,
            0.1125,
            0.16875,
            0.253125,
            0.3796875,
            0.56953125,
            0.854296875,
            1.2814453125,
            1.92216796875,
            2.883251953125,
            4.3248779296875,
            6.4873168945313,
            9.7309753417969,
        ];

        $this->assertEquals($generatedBuckets, $expectedBuckets);
    }

    /**
     * @return array
     * @see isShouldAcceptArbitraryLabelValues
     */
    public function labelValuesDataProvider()
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
