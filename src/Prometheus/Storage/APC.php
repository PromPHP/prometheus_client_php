<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCUIterator;
use Prometheus\Exception\StorageException;
use Prometheus\MetricFamilySamples;
use RuntimeException;

class APC implements Adapter
{
    const PROMETHEUS_PREFIX = 'prom';

    /**
     * APC constructor.
     *
     * @throws StorageException
     */
    public function __construct()
    {
        if (!extension_loaded('apcu')) {
            throw new StorageException('APCu extension is not loaded');
        }
        if (!apcu_enabled()) {
            throw new StorageException('APCu is not enabled');
        }
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        return $metrics;
    }

    /**
     * @param mixed[] $data
     */
    public function updateHistogram(array $data): void
    {
        // Initialize the sum
        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        $new = apcu_add($sumKey, $this->toBinaryRepresentationAsInteger(0));

        // If sum does not exist, assume a new histogram and store the metadata
        if ($new) {
            apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
        }

        // Atomically increment the sum
        // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
        $done = false;
        while (!$done) {
            $old = apcu_fetch($sumKey);
            if ($old !== false) {
                $done = apcu_cas($sumKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
            }
        }

        // Figure out in which bucket the observation belongs
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }

        // Initialize and increment the bucket
        apcu_add($this->histogramBucketValueKey($data, $bucketToIncrease), 0);
        apcu_inc($this->histogramBucketValueKey($data, $bucketToIncrease));
    }

    /**
     * @param mixed[] $data
     */
    public function updateGauge(array $data): void
    {
        $valueKey = $this->valueKey($data);
        if ($data['command'] === Adapter::COMMAND_SET) {
            apcu_store($valueKey, $this->toBinaryRepresentationAsInteger($data['value']));
            apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
        } else {
            $new = apcu_add($valueKey, $this->toBinaryRepresentationAsInteger(0));
            if ($new) {
                apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
            }
            // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
            $done = false;
            while (!$done) {
                $old = apcu_fetch($valueKey);
                if ($old !== false) {
                    $done = apcu_cas($valueKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
                }
            }
        }
    }

    /**
     * @param mixed[] $data
     */
    public function updateCounter(array $data): void
    {
        $valueKey = $this->valueKey($data);
        // Check if value key already exists
        if (apcu_exists($this->valueKey($data)) === false) {
            apcu_add($this->valueKey($data), 0);
            apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
        }

        // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
        $done = false;
        while (!$done) {
            $old = apcu_fetch($valueKey);
            if ($old !== false) {
                $done = apcu_cas($valueKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
            }
        }
    }

    /**
     * @deprecated use replacement method wipeStorage from Adapter interface
     *
     * @return void
     */
    public function flushAPC(): void
    {
        $this->wipeStorage();
    }

    /**
     * Removes all previously stored data from apcu
     *
     * @return void
     */
    public function wipeStorage(): void
    {
        //                   /      / | PCRE expresion boundary
        //                    ^       | match from first character only
        //                     %s:    | common prefix substitute with colon suffix
        //                        .+  | at least one additional character
        $matchAll = sprintf('/^%s:.+/', self::PROMETHEUS_PREFIX);

        foreach (new APCUIterator($matchAll) as $key => $value) {
            apcu_delete($key);
        }
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function metaKey(array $data): string
    {
        return implode(':', [self::PROMETHEUS_PREFIX, $data['type'], $data['name'], 'meta']);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            self::PROMETHEUS_PREFIX,
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    /**
     * @param mixed[] $data
     * @param string|int $bucket
     * @return string
     */
    private function histogramBucketValueKey(array $data, $bucket): string
    {
        return implode(':', [
            self::PROMETHEUS_PREFIX,
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
            'value',
        ]);
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    private function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues']);
        return $metricsMetaData;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectCounters(): array
    {
        $counters = [];
        foreach (new APCUIterator('/^prom:counter:.*:meta/') as $counter) {
            $metaData = json_decode($counter['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach (new APCUIterator('/^prom:counter:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $this->fromBinaryRepresentationAsInteger($value['value']),
                ];
            }
            $this->sortSamples($data['samples']);
            $counters[] = new MetricFamilySamples($data);
        }
        return $counters;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectGauges(): array
    {
        $gauges = [];
        foreach (new APCUIterator('/^prom:gauge:.*:meta/') as $gauge) {
            $metaData = json_decode($gauge['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach (new APCUIterator('/^prom:gauge:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $this->fromBinaryRepresentationAsInteger($value['value']),
                ];
            }

            $this->sortSamples($data['samples']);
            $gauges[] = new MetricFamilySamples($data);
        }
        return $gauges;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectHistograms(): array
    {
        $histograms = [];
        foreach (new APCUIterator('/^prom:histogram:.*:meta/') as $histogram) {
            $metaData = json_decode($histogram['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'buckets' => $metaData['buckets'],
            ];

            // Add the Inf bucket so we can compute it later on
            $data['buckets'][] = '+Inf';

            $histogramBuckets = [];
            foreach (new APCUIterator('/^prom:histogram:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $bucket = $parts[4];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value['value'];
            }

            // Compute all buckets
            $labels = array_keys($histogramBuckets);
            sort($labels);
            foreach ($labels as $labelValues) {
                $acc = 0;
                $decodedLabelValues = $this->decodeLabelValues($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (!isset($histogramBuckets[$labelValues][$bucket])) {
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $histogramBuckets[$labelValues][$bucket];
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_' . 'bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $this->fromBinaryRepresentationAsInteger($histogramBuckets[$labelValues]['sum']),
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }
        return $histograms;
    }

    /**
     * @param mixed $val
     * @return int
     * @throws RuntimeException
     */
    private function toBinaryRepresentationAsInteger($val): int
    {
        $packedDouble = pack('d', $val);
        if ((bool)$packedDouble !== false) {
            $unpackedData = unpack("Q", $packedDouble);
            if (is_array($unpackedData)) {
                return $unpackedData[1];
            }
        }
        throw new RuntimeException("Formatting from binary representation to integer did not work");
    }

    /**
     * @param mixed $val
     * @return float
     * @throws RuntimeException
     */
    private function fromBinaryRepresentationAsInteger($val): float
    {
        $packedBinary = pack('Q', $val);
        if ((bool)$packedBinary !== false) {
            $unpackedData = unpack("d", $packedBinary);
            if (is_array($unpackedData)) {
                return $unpackedData[1];
            }
        }
        throw new RuntimeException("Formatting from integer to binary representation did not work");
    }

    /**
     * @param mixed[] $samples
     */
    private function sortSamples(array &$samples): void
    {
        usort($samples, function ($a, $b): int {
            return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
        });
    }

    /**
     * @param mixed[] $values
     * @return string
     * @throws RuntimeException
     */
    private function encodeLabelValues(array $values): string
    {
        $json = json_encode($values);
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }
        return base64_encode($json);
    }

    /**
     * @param string $values
     * @return mixed[]
     * @throws RuntimeException
     */
    private function decodeLabelValues(string $values): array
    {
        $json = base64_decode($values, true);
        if (false === $json) {
            throw new RuntimeException('Cannot base64 decode label values');
        }
        $decodedValues = json_decode($json, true);
        if (false === $decodedValues) {
            throw new RuntimeException(json_last_error_msg());
        }
        return $decodedValues;
    }
}
