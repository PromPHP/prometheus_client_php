<?php

declare(strict_types=1);

namespace Test\Prometheus;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Exception\MetricNotFoundException;

abstract class AbstractCollectorRegistryTest extends TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;

    /**
     * @var RenderTextFormat
     */
    private $renderer;

    public function setUp(): void
    {
        $this->configureAdapter();
        $this->renderer = new RenderTextFormat();
    }

    /**
     * @test
     */
    public function itShouldHaveDefaultMetrics(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $expected = <<<EOF
# HELP php_info Information about the PHP environment.
# TYPE php_info gauge
php_info{version="%s"} 1

EOF;
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                sprintf($expected, phpversion())
            )
        );
    }

    /**
     * @test
     */
    public function itShouldNotHaveDefaultMetricsWhenTheyAreDisabled(): void
    {
        $registry = new CollectorRegistry($this->adapter, false);
        $expected = <<<EOF
# HELP php_info Information about the PHP environment.
# TYPE php_info gauge
php_info{version="%s"} 1

EOF;
        self::assertStringNotContainsString(
            sprintf($expected, phpversion()),
            $this->renderer->render($registry->getMetricFamilySamples())
        );
    }


    /**
     * @test
     */
    public function itShouldSaveGauges(): void
    {
        $registry = new CollectorRegistry($this->adapter);

        $g = $registry->registerGauge('test', 'some_metric', 'this is for testing', ['foo']);
        $g->set(35, ['bbb']);
        $g->set(35, ['ddd']);
        $g->set(35, ['aaa']);
        $g->set(35, ['ccc']);


        $registry = new CollectorRegistry($this->adapter);
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric gauge
test_some_metric{foo="aaa"} 35
test_some_metric{foo="bbb"} 35
test_some_metric{foo="ccc"} 35
test_some_metric{foo="ddd"} 35

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveCounters(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerCounter('test', 'some_metric', 'this is for testing', ['foo', 'bar']);
        $metric->incBy(2, ['lalal', 'lululu']);
        $registry->getCounter('test', 'some_metric', ['foo', 'bar'])->inc(['lalal', 'lululu']);
        $registry->getCounter('test', 'some_metric', ['foo', 'bar'])->inc(['lalal', 'lvlvlv']);

        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric counter
test_some_metric{foo="lalal",bar="lululu"} 3
test_some_metric{foo="lalal",bar="lvlvlv"} 1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveHistograms(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerHistogram(
            'test',
            'some_metric',
            'this is for testing',
            ['foo', 'bar'],
            [0.1, 1, 5, 10]
        );
        $metric->observe(2, ['lalal', 'lululu']);
        $registry->getHistogram('test', 'some_metric', ['foo', 'bar'])->observe(7.1, ['lalal', 'lvlvlv']);
        $registry->getHistogram('test', 'some_metric', ['foo', 'bar'])->observe(13, ['lalal', 'lululu']);
        $registry->getHistogram('test', 'some_metric', ['foo', 'bar'])->observe(7.1, ['lalal', 'lululu']);
        $registry->getHistogram('test', 'some_metric', ['foo', 'bar'])->observe(7.1, ['gnaaha', 'hihihi']);

        $registry = new CollectorRegistry($this->adapter);
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric histogram
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="0.1"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="1"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="5"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="10"} 1
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="+Inf"} 1
test_some_metric_count{foo="gnaaha",bar="hihihi"} 1
test_some_metric_sum{foo="gnaaha",bar="hihihi"} 7.1
test_some_metric_bucket{foo="lalal",bar="lululu",le="0.1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="5"} 1
test_some_metric_bucket{foo="lalal",bar="lululu",le="10"} 2
test_some_metric_bucket{foo="lalal",bar="lululu",le="+Inf"} 3
test_some_metric_count{foo="lalal",bar="lululu"} 3
test_some_metric_sum{foo="lalal",bar="lululu"} 22.1
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="0.1"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="1"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="5"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="10"} 1
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="+Inf"} 1
test_some_metric_count{foo="lalal",bar="lvlvlv"} 1
test_some_metric_sum{foo="lalal",bar="lvlvlv"} 7.1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveHistogramsWithoutLabels(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerHistogram('test', 'some_metric', 'this is for testing');
        $metric->observe(2);
        $registry->getHistogram('test', 'some_metric')->observe(13);
        $registry->getHistogram('test', 'some_metric')->observe(7.1);

        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric histogram
test_some_metric_bucket{le="0.005"} 0
test_some_metric_bucket{le="0.01"} 0
test_some_metric_bucket{le="0.025"} 0
test_some_metric_bucket{le="0.05"} 0
test_some_metric_bucket{le="0.075"} 0
test_some_metric_bucket{le="0.1"} 0
test_some_metric_bucket{le="0.25"} 0
test_some_metric_bucket{le="0.5"} 0
test_some_metric_bucket{le="0.75"} 0
test_some_metric_bucket{le="1"} 0
test_some_metric_bucket{le="2.5"} 1
test_some_metric_bucket{le="5"} 1
test_some_metric_bucket{le="7.5"} 2
test_some_metric_bucket{le="10"} 2
test_some_metric_bucket{le="+Inf"} 3
test_some_metric_count 3
test_some_metric_sum 22.1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldIncreaseACounterWithoutNamespace(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $registry
            ->registerCounter('', 'some_quick_counter', 'just a quick measurement')
            ->inc();

        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP some_quick_counter just a quick measurement
# TYPE some_quick_counter counter
some_quick_counter 1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldForbidRegisteringTheSameCounterTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $registry->registerCounter('foo', 'metric', 'help');

        $this->expectException(MetricsRegistrationException::class);
        $registry->registerCounter('foo', 'metric', 'help');
    }

    /**
     * @test
     */
    public function itShouldAllowRegisteringTheSameCounterWithDifferentLabels(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric1 = $registry->registerCounter('foo', 'metric', 'help', ["foo", "bar"]);
        $metric1->incBy(2, ['fooval', 'barval']);
        $metric2 = $registry->registerCounter('foo', 'metric', 'help', ["spam", "eggs"]);
        $metric2->incBy(5, ['spamval', 'eggsval']);
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP foo_metric help
# TYPE foo_metric counter
foo_metric{foo="fooval",bar="barval"} 2
foo_metric{spam="spamval",eggs="eggsval"} 5

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldForbidRegisteringTheSameHistogramTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $registry->registerHistogram('foo', 'metric', 'help');

        $this->expectException(MetricsRegistrationException::class);
        $registry->registerHistogram('foo', 'metric', 'help');
    }

    /**
     * @test
     */
    public function itShouldAllowRegisteringTheSameHistogramWithDifferentLabels(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric1 = $registry->registerHistogram('foo', 'metric', 'help', ["foo", "bar"]);
        $metric1->observe(2, ['fooval', 'barval']);
        $metric1->observe(2.5, ['fooval', 'barval']);
        $metric2 = $registry->registerHistogram('foo', 'metric', 'help', ["spam", "eggs"]);
        $metric2->observe(5, ['spamval', 'eggsval']);
        $metric2->observe(8, ['spamval', 'eggsval']);
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP foo_metric help
# TYPE foo_metric histogram
foo_metric_bucket{foo="fooval",bar="barval",le="0.005"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.01"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.025"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.05"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.075"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.1"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.25"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.5"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="0.75"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="1"} 0
foo_metric_bucket{foo="fooval",bar="barval",le="2.5"} 2
foo_metric_bucket{foo="fooval",bar="barval",le="5"} 2
foo_metric_bucket{foo="fooval",bar="barval",le="7.5"} 2
foo_metric_bucket{foo="fooval",bar="barval",le="10"} 2
foo_metric_bucket{foo="fooval",bar="barval",le="+Inf"} 2
foo_metric_count{foo="fooval",bar="barval"} 2
foo_metric_sum{foo="fooval",bar="barval"} 4.5
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.005"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.01"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.025"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.05"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.075"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.1"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.25"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.5"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="0.75"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="1"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="2.5"} 0
foo_metric_bucket{spam="spamval",eggs="eggsval",le="5"} 1
foo_metric_bucket{spam="spamval",eggs="eggsval",le="7.5"} 1
foo_metric_bucket{spam="spamval",eggs="eggsval",le="10"} 2
foo_metric_bucket{spam="spamval",eggs="eggsval",le="+Inf"} 2
foo_metric_count{spam="spamval",eggs="eggsval"} 2
foo_metric_sum{spam="spamval",eggs="eggsval"} 13

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldForbidRegisteringTheSameGaugeTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $registry->registerGauge('foo', 'metric', 'help');

        $this->expectException(MetricsRegistrationException::class);
        $registry->registerGauge('foo', 'metric', 'help');
    }

    /**
     * @test
     */
    public function itShouldAllowRegisteringTheSameGaugeWithDifferentLabels(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric1 = $registry->registerGauge('foo', 'metric', 'help', ["foo", "bar"]);
        $metric1->set(2, ['fooval', 'barval']);
        $metric2 = $registry->registerGauge('foo', 'metric', 'help', ["spam", "eggs"]);
        $metric2->set(5, ['spamval', 'eggsval']);
        self::assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            self::stringContains(
                <<<EOF
# HELP foo_metric help
# TYPE foo_metric gauge
foo_metric{foo="fooval",bar="barval"} 2
foo_metric{spam="spamval",eggs="eggsval"} 5

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionWhenGettingANonExistentMetric(): void
    {
        $registry = new CollectorRegistry($this->adapter);

        $this->expectException(MetricNotFoundException::class);
        $registry->getGauge("not_here", "go_away");
    }

    /**
     * @test
     */
    public function itShouldNotRegisterACounterTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $counterA = $registry->getOrRegisterCounter("foo", "bar", "Help text");
        $counterB = $registry->getOrRegisterCounter("foo", "bar", "Help text");

        self::assertSame($counterA, $counterB);
    }

    /**
     * @test
     */
    public function itShouldNotRegisterAGaugeTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $gaugeA = $registry->getOrRegisterGauge("foo", "bar", "Help text");
        $gaugeB = $registry->getOrRegisterGauge("foo", "bar", "Help text");

        self::assertSame($gaugeA, $gaugeB);
    }

    /**
     * @test
     */
    public function itShouldNotRegisterAHistogramTwice(): void
    {
        $registry = new CollectorRegistry($this->adapter);
        $histogramA = $registry->getOrRegisterHistogram("foo", "bar", "Help text");
        $histogramB = $registry->getOrRegisterHistogram("foo", "bar", "Help text");

        self::assertSame($histogramA, $histogramB);
    }

    /**
     * @test
     * @dataProvider itShouldThrowAnExceptionOnInvalidMetricNamesDataProvider
     */
    public function itShouldThrowAnExceptionOnInvalidMetricNames(string $namespace, string $metricName): void
    {
        $registry = new CollectorRegistry($this->adapter);

        $this->expectException(\InvalidArgumentException::class);
        $registry->registerGauge($namespace, $metricName, 'help', ["foo", "bar"]);
    }

    /**
     * @return string[][]
     */
    public function itShouldThrowAnExceptionOnInvalidMetricNamesDataProvider(): array
    {
        return [
            [
                "foo",
                "invalid-metric-name"
            ],
            [
                "invalid-namespace",
                "foo"
            ],
            [
                "invalid-namespace",
                "both-invalid"
            ],
        ];
    }

    /**
     * @test
     * @dataProvider itShouldThrowAnExceptionOnInvalidMetricLabelDataProvider
     */
    public function itShouldThrowAnExceptionOnInvalidMetricLabel(string $invalidLabel): void
    {
        $registry = new CollectorRegistry($this->adapter);

        $this->expectException(\InvalidArgumentException::class);
        $registry->registerGauge("foo", "bar", 'help', [$invalidLabel]);
    }

    /**
     * @return string[][]
     */
    public function itShouldThrowAnExceptionOnInvalidMetricLabelDataProvider(): array
    {
        return [
            [
                "invalid-label"
            ],
        ];
    }


    abstract public function configureAdapter(): void;
}
