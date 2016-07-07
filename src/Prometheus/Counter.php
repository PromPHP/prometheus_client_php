<?php

namespace Prometheus;


use Prometheus\Storage\Adapter;

class Counter extends Collector
{
    const TYPE = 'counter';

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

        $this->storageAdapter->store(
            Adapter::COMMAND_INCREMENT_INTEGER,
            $this,
            new Sample(
                array(
                    'name' => $this->getName(),
                    'labelNames' => array(),
                    'labelValues' => $labels,
                    'value' => $count
                )
            )
        );
    }
}
