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
        $registry->getOrRegisterGauge($namespace, 'gauge', 'counter-help-text', ['label1', 'label2'])
                 ->inc(["bo\nb", 'ali\"ce']);
        $registry->getOrRegisterHistogram($namespace, 'histogram', 'counter-help-text', ['label1', 'label2'], [0, 10, 100])
                 ->observe(5, ['bob', 'alice']);

        return $registry->getMetricFamilySamples();
    }

    private function getExpectedOutput(): string
    {
        return <<<TEXTPLAIN
# HELP mynamespace_counter counter-help-text
# TYPE mynamespace_counter counter
mynamespace_counter{label1="bob",label2="al\\\\ice"} 1
# HELP mynamespace_gauge counter-help-text
# TYPE mynamespace_gauge gauge
mynamespace_gauge{label1="bo\\nb",label2="ali\\\\\"ce"} 1
# HELP mynamespace_histogram counter-help-text
# TYPE mynamespace_histogram histogram
mynamespace_histogram_bucket{label1="bob",label2="alice",le="0"} 0
mynamespace_histogram_bucket{label1="bob",label2="alice",le="10"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="100"} 1
mynamespace_histogram_bucket{label1="bob",label2="alice",le="+Inf"} 1
mynamespace_histogram_count{label1="bob",label2="alice"} 1
mynamespace_histogram_sum{label1="bob",label2="alice"} 5

TEXTPLAIN;
    }
}
