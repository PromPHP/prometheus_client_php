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
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function inc(array $labels = []): void
    {
        $this->incBy(1, $labels);
    }

    /**
     * @param int $count e.g. 2
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function incBy($count, array $labels = []): void
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateCounter(
            [
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'value' => $count,
                'command' => Adapter::COMMAND_INCREMENT_INTEGER,
            ]
        );
    }
}
