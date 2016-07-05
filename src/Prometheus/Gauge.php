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

        $this->storageAdapter->storeSample(
            'hSet',
            $this,
            new Sample(
                array(
                    'name' => $this->getName(),
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labels,
                    'value' => $value
                )
            )
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return self::TYPE;
    }
}
