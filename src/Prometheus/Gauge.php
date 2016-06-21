<?php


namespace Prometheus;


class Gauge extends Metric
{
    const TYPE = 'gauge';

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function set($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);
        $this->values[serialize($labels)] = $value;
    }

    /**
     * @return Sample[]
     */
    public function getSamples()
    {
        $metrics = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labelValues = unserialize($serializedLabels);
            $metrics[] = new Sample(
                array(
                    'name' => $this->getFullName(),
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labelValues,
                    'value' => $value
                )
            );
        }
        return $metrics;
    }

    public function getType()
    {
        return self::TYPE;
    }

    public function getHelp()
    {
        return $this->help;
    }

    public function getFullName()
    {
        return Metric::metricName($this->namespace, $this->name);
    }

    public function getLabelNames()
    {
        return $this->labels;
    }
}
