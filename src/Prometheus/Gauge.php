<?php


namespace Prometheus;


use Prometheus\Storage\Adapter;

class Gauge extends Collector
{
    const TYPE = 'gauge';

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function set($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateGauge(
            array(
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'value' => $value,
                'command' => Adapter::COMMAND_SET
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

    public function inc($labels = array())
    {
        $this->incBy(1, $labels);
    }

    public function incBy($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateGauge(
            array(
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'value' => $value,
                'command' => Adapter::COMMAND_INCREMENT_FLOAT
            )
        );
    }

    public function dec($labels = array())
    {
        $this->decBy(1, $labels);
    }

    public function decBy($value, $labels = array())
    {
        $this->incBy(-$value, $labels);
    }
}
