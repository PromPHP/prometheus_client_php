<?php

declare(strict_types=1);

namespace Test\Prometheus;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prometheus\Gauge;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractGaugeTest extends TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;

    public function setUp(): void
    {
        $this->configureAdapter();
    }

    abstract public function configureAdapter(): void;

    /**
     * @test
     */
    public function itShouldAllowSetWithLabels(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->set(123, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 123,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
        self::assertThat($gauge->getHelp(), self::equalTo('this is for testing'));
        self::assertThat($gauge->getType(), self::equalTo(Gauge::TYPE));
    }

    /**
     * @test
     */
    public function itShouldAllowSetWithoutLabelWhenNoLabelsAreDefined(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing');
        $gauge->set(123);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 123,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
        self::assertThat($gauge->getHelp(), self::equalTo('this is for testing'));
        self::assertThat($gauge->getType(), self::equalTo(Gauge::TYPE));
    }

    /**
     * @test
     */
    public function itShouldAllowSetWithAFloatValue(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing');
        $gauge->set(123.5);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => [],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => [],
                                    'value' => 123.5,
                                ],
                            ],
                        ]
                    ),
                ]
            )
        );
        self::assertThat($gauge->getHelp(), self::equalTo('this is for testing'));
        self::assertThat($gauge->getType(), self::equalTo(Gauge::TYPE));
    }

    /**
     * @test
     */
    public function itShouldIncrementAValue(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->inc(['lalal', 'lululu']);
        $gauge->incBy(123, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 124,
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
    public function itShouldIncrementWithFloatValue(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->inc(['lalal', 'lululu']);
        $gauge->incBy(123.5, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 124.5,
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
    public function itShouldDecrementAValue(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->dec(['lalal', 'lululu']);
        $gauge->decBy(123, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => -124,
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
    public function itShouldDecrementWithFloatValue(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->dec(['lalal', 'lululu']);
        $gauge->decBy(123, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => -124,
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
    public function itShouldOverwriteWhenSettingTwice(): void
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $gauge->set(123, ['lalal', 'lululu']);
        $gauge->set(321, ['lalal', 'lululu']);
        self::assertThat(
            $this->adapter->collect(),
            self::equalTo(
                [
                    new MetricFamilySamples(
                        [
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => ['foo', 'bar'],
                            'samples' => [
                                [
                                    'name' => 'test_some_metric',
                                    'labelNames' => [],
                                    'labelValues' => ['lalal', 'lululu'],
                                    'value' => 321,
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
    public function itShouldRejectInvalidMetricsNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Gauge($this->adapter, 'test', 'some metric invalid metric', 'help');
    }

    /**
     * @test
     */
    public function itShouldRejectInvalidLabelNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Gauge($this->adapter, 'test', 'some_metric', 'help', ['invalid label']);
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
        $histogram = new Gauge($this->adapter, 'test', 'some_metric', 'help', [$label]);
        $histogram->inc([$value]);

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
