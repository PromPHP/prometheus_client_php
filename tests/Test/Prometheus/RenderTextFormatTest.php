<?php

declare(strict_types=1);

namespace Test\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\MetricFamilySamples;
use Prometheus\RenderTextFormat;
use PHPUnit\Framework\TestCase;
use Prometheus\Storage\InMemory;

class RenderTextFormatTest extends TestCase
{

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
        $registry = new CollectorRegistry(new InMemory(), false);
        $registry->getOrRegisterCounter($namespace, 'counter', 'counter-help-text', ['label1', 'label2'])
                 ->inc(['bob', 'al\ice']);
        $registry->getOrRegisterGauge($namespace, 'gauge', 'gauge-help-text', ['label1', 'label2'])
                 ->inc(["bo\nb", 'ali\"ce']);
        $registry->getOrRegisterHistogram($namespace, 'histogram', 'histogram-help-text', ['label1', 'label2'], [0, 10, 100])
                 ->observe(5, ['bob', 'alice']);
        $registry->getOrRegisterSummary($namespace, 'histogram', 'summary-help-text', ['label1', 'label2'], 60, [0.1, 0.5, 0.9])
            ->observe(5, ['bob', 'alice']);

        return $registry->getMetricFamilySamples();
    }

    private function getExpectedOutput(): string
    {
        return <<<TEXTPLAIN
# HELP mynamespace_counter counter-help-text
# TYPE mynamespace_counter counter
mynamespace_counter{label1="bob",label2="al\\\\ice"} 1
# HELP mynamespace_gauge gauge-help-text
# TYPE mynamespace_gauge gauge
mynamespace_gauge{label1="bo\\nb",label2="ali\\\\\"ce"} 1
# HELP mynamespace_histogram histogram-help-text
# TYPE mynamespace_histogram histogram
mynamespace_histogram_bucket{label1="bob",label2="alice",le="0"} 0
mynamespace_histogram_bucket{label1="bob",label2="alice",le="10"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="100"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="+Inf"} 1
mynamespace_histogram_count{label1="bob",label2="alice"} 1
mynamespace_histogram_sum{label1="bob",label2="alice"} 5
# HELP mynamespace_summary summary-help-text
# TYPE mynamespace_summary summary
mynamespace_summary{label1="bob",label2="alice",quantile="0.1"} 5
mynamespace_summary{label1="bob",label2="alice",quantile="0.5"} 5
mynamespace_summary{label1="bob",label2="alice",quantile="0.9"} 5
mynamespace_summary_count{label1="bob",label2="alice"} 1
mynamespace_summary_sum{label1="bob",label2="alice"} 5

TEXTPLAIN;
    }
}
