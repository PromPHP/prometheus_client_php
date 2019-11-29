<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\MetricFamilySamples;

interface Adapter
{
    const COMMAND_INCREMENT_INTEGER = 1;
    const COMMAND_INCREMENT_FLOAT = 2;
    const COMMAND_SET = 3;

    /**
     * @return MetricFamilySamples[]
     */
    public function collect();

    /**
     * @param array $data
     * @return void
     */
    public function updateHistogram(array $data): void;

    /**
     * @param array $data
     * @return void
     */
    public function updateGauge(array $data): void;

    /**
     * @param array $data
     * @return void
     */
    public function updateCounter(array $data): void;
}
