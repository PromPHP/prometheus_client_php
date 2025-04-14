<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\Exception\StorageException;
use Prometheus\MetricFamilySamples;

interface Adapter
{
    const COMMAND_INCREMENT_INTEGER = 1;
    const COMMAND_INCREMENT_FLOAT = 2;
    const COMMAND_SET = 3;

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array;

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateSummary(array $data): void;

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateHistogram(array $data): void;

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateGauge(array $data): void;

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateCounter(array $data): void;

    /**
     * Removes all previously stored metrics from underlying storage
     *
     * @throws StorageException
     * @return void
     */
    public function wipeStorage(): void;

    /**
     * Removes a specific previously stored metric from underlying storage
     *
     * @param string $type
     * @param string $key
     * @throws StorageException
     * @return void
     */
    public function wipeKey(string $type, string $key): void;
}
