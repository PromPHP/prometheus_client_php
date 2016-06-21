<?php

namespace Prometheus;


class RenderTextFormat
{
    public function render($metrics)
    {
        $lines = array();
        foreach ($metrics as $metric) {
            $lines[] = "# HELP " . $metric['name'] . " {$metric['help']}";
            $lines[] = "# TYPE " . $metric['name'] . " {$metric['type']}";
            foreach ($metric['samples'] as $sample) {
                $lines[] = $this->renderSample($sample);
            }
        }
        return implode("\n", $lines) . "\n";
    }


    /**
     * @param array $sample
     * @return string
     */
    private function renderSample(array $sample)
    {
        $escapedLabels = array();
        if (!empty($sample['labels'])) {
            foreach ($sample['labels'] as $labelName => $labelValue) {
                $escapedLabels[] = $labelName . '="' . $this->escapeLabelValue($labelValue) . '"';
            }
            return $sample['name'] . '{' . implode(',', $escapedLabels) . '} ' . $sample['value'];
        }
        return $sample['name'] . ' ' . $sample['value'];
    }

    private function escapeLabelValue($v)
    {
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace("\"", "\\\"", $v);
        return $v;
    }
}
