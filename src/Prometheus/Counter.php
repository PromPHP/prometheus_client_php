<?php

namespace Prometheus;


class Counter extends Metric
{
    const TYPE = 'counter';

    /**
     * @return Sample[]
     */
    public function getSamples()
    {
        $metrics = array();
        foreach ($this->values as $serializedLabels => $value) {
            $labels = unserialize($serializedLabels);
            $metrics[] = new Sample(
                array(
                    'name' => $this->getName(),
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => array_values($labels),
                    'value' => $value
                )
            );
        }
        return $metrics;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return self::TYPE;
    }

    /**
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function inc(array $labels = array())
    {
        $this->incBy(1, $labels);
    }

    /**
     * @param int $count e.g. 2
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function incBy($count, array $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        if (!isset($this->values[serialize($labels)])) {
            $this->values[serialize($labels)] = 0;
        }
        $this->values[serialize($labels)] += $count;
    }
}
