<?php

namespace Prometheus;


class RenderTextFormat
{
    /**
     * @param MetricResponse[] $metrics
     * @return string
     */
    public function render(array $metrics)
    {
        $lines = array();
        foreach ($metrics as $metric) {
            $lines[] = "# HELP " . $metric->getName() . " {$metric->getHelp()}";
            $lines[] = "# TYPE " . $metric->getName() . " {$metric->getType()}";
            foreach ($metric->getSamples() as $sample) {
                $lines[] = $this->renderSample($sample);
            }
        }
        return implode("\n", $lines) . "\n";
    }


    /**
     * @param Sample $sample
     * @return string
     */
    private function renderSample(Sample $sample)
    {
        $escapedLabels = array();
        if (!empty($sample->getLabels())) {
            foreach ($sample->getLabels() as $labelName => $labelValue) {
                $escapedLabels[] = $labelName . '="' . $this->escapeLabelValue($labelValue) . '"';
            }
            return $sample->getName() . '{' . implode(',', $escapedLabels) . '} ' . $sample->getValue();
        }
        return $sample->getName() . ' ' . $sample->getValue();
    }

    private function escapeLabelValue($v)
    {
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace("\"", "\\\"", $v);
        return $v;
    }
}
