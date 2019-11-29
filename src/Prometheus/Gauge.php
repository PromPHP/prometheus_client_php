<?php

declare(strict_types=1);

namespace Prometheus;

use Prometheus\Storage\Adapter;

class Gauge extends Collector
{
    const TYPE = 'gauge';

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function set(float $value, array $labels = []): void
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateGauge(
            [
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'value' => $value,
                'command' => Adapter::COMMAND_SET,
            ]
        );
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @param array $labels
     */
    public function inc($labels = []): void
    {
        $this->incBy(1, $labels);
    }

    /**
     * @param $value
     * @param array $labels
     */
    public function incBy($value, array $labels = []): void
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateGauge(
            [
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'value' => $value,
                'command' => Adapter::COMMAND_INCREMENT_FLOAT,
            ]
        );
    }

    /**
     * @param array $labels
     */
    public function dec($labels = []): void
    {
        $this->decBy(1, $labels);
    }

    /**
     * @param $value
     * @param array $labels
     */
    public function decBy($value, $labels = []): void
    {
        $this->incBy(-$value, $labels);
    }
}
