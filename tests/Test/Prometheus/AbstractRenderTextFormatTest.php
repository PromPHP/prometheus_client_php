<?php

declare(strict_types=1);

namespace Test\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\MetricFamilySamples;
use Prometheus\RenderTextFormat;
use PHPUnit\Framework\TestCase;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Adapter;

abstract class AbstractRenderTextFormatTest extends TestCase
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

    public function testOutputMatchesExpectations(): void
    {
        $metrics = $this->buildSamples();

        $renderer = new RenderTextFormat();
        $output = $renderer->render($metrics);

        self::assertSame($this->getExpectedOutput(), $output);
    }

    /**
     * @return MetricFamilySamples[]
     * @throws MetricsRegistrationException
     */
    private function buildSamples(): array
    {
        $namespace = 'mynamespace';
        $registry = new CollectorRegistry($this->adapter, false);
        $registry->getOrRegisterCounter($namespace, 'counter', 'counter-help-text', ['label1', 'label2'])
                 ->inc(['bob', 'al\ice']);
        $registry->getOrRegisterCounter($namespace, 'counter', 'counter-help-text')
                 ->incBy(12);
        $registry->getOrRegisterGauge($namespace, 'gauge', 'counter-help-text', ['label1', 'label2'])
                 ->inc(["bo\nb", 'ali\"ce']);
        $registry->getOrRegisterGauge($namespace, 'gauge', 'counter-help-text')
                 ->set(25);
        $registry->getOrRegisterHistogram($namespace, 'histogram', 'counter-help-text', ['label1', 'label2'], [0, 10, 100])
                 ->observe(5, ['bob', 'alice']);
        $registry->getOrRegisterHistogram($namespace, 'histogram', 'counter-help-text')
                 ->observe(6);

        return $registry->getMetricFamilySamples();
    }

    private function getExpectedOutput(): string
    {
        return <<<TEXTPLAIN
# HELP mynamespace_counter counter-help-text
# TYPE mynamespace_counter counter
mynamespace_counter 12
mynamespace_counter{label1="bob",label2="al\\\\ice"} 1
# HELP mynamespace_gauge counter-help-text
# TYPE mynamespace_gauge gauge
mynamespace_gauge 25
mynamespace_gauge{label1="bo\\nb",label2="ali\\\\\"ce"} 1
# HELP mynamespace_histogram counter-help-text
# TYPE mynamespace_histogram histogram
mynamespace_histogram_bucket{le="0.005"} 0
mynamespace_histogram_bucket{le="0.01"} 0
mynamespace_histogram_bucket{le="0.025"} 0
mynamespace_histogram_bucket{le="0.05"} 0
mynamespace_histogram_bucket{le="0.075"} 0
mynamespace_histogram_bucket{le="0.1"} 0
mynamespace_histogram_bucket{le="0.25"} 0
mynamespace_histogram_bucket{le="0.5"} 0
mynamespace_histogram_bucket{le="0.75"} 0
mynamespace_histogram_bucket{le="1"} 0
mynamespace_histogram_bucket{le="2.5"} 0
mynamespace_histogram_bucket{le="5"} 0
mynamespace_histogram_bucket{le="7.5"} 1
mynamespace_histogram_bucket{le="10"} 1
mynamespace_histogram_bucket{le="+Inf"} 1
mynamespace_histogram_count 1
mynamespace_histogram_sum 6
mynamespace_histogram_bucket{label1="bob",label2="alice",le="0"} 0
mynamespace_histogram_bucket{label1="bob",label2="alice",le="10"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="100"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="+Inf"} 1
mynamespace_histogram_count{label1="bob",label2="alice"} 1
mynamespace_histogram_sum{label1="bob",label2="alice"} 5

TEXTPLAIN;
    }
}
