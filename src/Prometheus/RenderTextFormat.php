<?php

declare(strict_types=1);

namespace Prometheus;

class RenderTextFormat implements RendererInterface
{
    const MIME_TYPE = 'text/plain; version=0.0.4';

    /**
     * @param MetricFamilySamples[] $metrics
     * @return string
     */
    public function render(array $metrics): string
    {
        usort($metrics, function (MetricFamilySamples $a, MetricFamilySamples $b): int {
            return strcmp($a->getName(), $b->getName());
        });

        $lines = [];
        foreach ($metrics as $metric) {
            $lines[] = "# HELP " . $metric->getName() . " {$metric->getHelp()}";
            $lines[] = "# TYPE " . $metric->getName() . " {$metric->getType()}";
            foreach ($metric->getSamples() as $sample) {
                $lines[] = $this->renderSample($metric, $sample);
            }
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * @param MetricFamilySamples $metric
     * @param Sample $sample
     * @return string
     */
    private function renderSample(MetricFamilySamples $metric, Sample $sample): string
    {
        $labelNames = $metric->getLabelNames();
        if ($metric->hasLabelNames() || $sample->hasLabelNames()) {
            $escapedLabels = $this->escapeAllLabels($labelNames, $sample);
            return $sample->getName() . '{' . implode(',', $escapedLabels) . '} ' . $sample->getValue();
        }
        return $sample->getName() . ' ' . $sample->getValue();
    }

    /**
     * @param string $v
     * @return string
     */
    private function escapeLabelValue(string $v): string
    {
        return str_replace(["\\", "\n", "\""], ["\\\\", "\\n", "\\\""], $v);
    }

    /**
     * @param string[] $labelNames
     * @param Sample $sample
     *
     * @return string[]
     */
    private function escapeAllLabels(array $labelNames, Sample $sample): array
    {
        $escapedLabels = [];

        $labels = array_combine(array_merge($labelNames, $sample->getLabelNames()), $sample->getLabelValues());

        foreach ($labels as $labelName => $labelValue) {
            $escapedLabels[] = $labelName . '="' . $this->escapeLabelValue((string)$labelValue) . '"';
        }

        return $escapedLabels;
    }
}
