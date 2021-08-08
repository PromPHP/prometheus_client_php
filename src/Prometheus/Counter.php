<?php

declare(strict_types=1);

namespace Prometheus;

use Prometheus\Storage\Adapter;

class Counter extends Collector
{
    const TYPE = 'counter';

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @param string[] $labels e.g. ['status', 'opcode']
     */
    public function inc(array $labels = [], ?int $timestamp = null): void
    {
        $this->incBy(1, $labels, $timestamp);
    }

    /**
     * @param int|float $count e.g. 2
     * @param mixed[] $labels e.g. ['status', 'opcode']
     */
    public function incBy($count, array $labels = [], ?int $timestamp = null): void
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateCounter(
            [
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'timestamp'   => $timestamp,
                'value' => $count,
                'command' => is_float($count) ? Adapter::COMMAND_INCREMENT_FLOAT : Adapter::COMMAND_INCREMENT_INTEGER,
            ]
        );
    }
}
