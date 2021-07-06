<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCUIterator;
use Prometheus\Exception\StorageException;
use Prometheus\MetricFamilySamples;
use RuntimeException;

class APC implements Adapter
{
    /** @var string Default prefix to use for APC keys. */
    const PROMETHEUS_PREFIX = 'prom';

    /** @var string Prefix to use for APC keys. */
    private $prometheusPrefix;

    /**
     * APC constructor.
     *
     * @param string $prometheusPrefix Prefix for APCu keys (defaults to {@see PROMETHEUS_PREFIX}).
     *
     * @throws StorageException
     */
    public function __construct(string $prometheusPrefix = self::PROMETHEUS_PREFIX)
    {
        if (!extension_loaded('apcu')) {
            throw new StorageException('APCu extension is not loaded');
        }
        if (!apcu_enabled()) {
            throw new StorageException('APCu is not enabled');
        }

        $this->prometheusPrefix = $prometheusPrefix;
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
            apcu_store($this->histogramBucketsPerLabelsKey($data), $data['buckets']);
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
        $matchAll = sprintf('/^%s:.+/', $this->prometheusPrefix);

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
        return implode(':', [$this->prometheusPrefix, $data['type'], $data['name'], 'meta']);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function histogramBucketsPerLabelsKey(array $data): string
    {
        return implode(':', [$this->prometheusPrefix, $data['type'], $data['name'], $this->encodeLabels($data['labelNames']), 'bucketsPerLabels']);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            $this->encodeLabels($data['labelNames']),
            $this->encodeLabels($data['labelValues']),
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
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            $this->encodeLabels($data['labelNames']),
            $this->encodeLabels($data['labelValues']),
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
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues'], $metricsMetaData['buckets']);
        return $metricsMetaData;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectCounters(): array
    {
        $counters = [];
        foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':counter:.*:meta/') as $counter) {
            $metaData = json_decode($counter['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'samples' => [],
            ];
            foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':counter:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelNamesEncoded = $parts[3];
                $labelValues = $parts[4];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => $this->decodeLabels($labelNamesEncoded),
                    'labelValues' => $this->decodeLabels($labelValues),
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
        foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':gauge:.*:meta/') as $gauge) {
            $metaData = json_decode($gauge['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'samples' => [],
            ];
            foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':gauge:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $labelNamesEncoded = $parts[3];
                $labelValues = $parts[4];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => $this->decodeLabels($labelNamesEncoded),
                    'labelValues' => $this->decodeLabels($labelValues),
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
        foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':histogram:.*:meta/') as $histogram) {
            $metaData = json_decode($histogram['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
            ];

            $histogramBuckets = [];
            foreach (new APCUIterator('/^' . $this->prometheusPrefix . ':histogram:' . $metaData['name'] . ':.*:value/') as $value) {
                $parts = explode(':', $value['key']);
                $encodedlabelNames = $parts[3];
                $encodedlabelValues = $parts[4];
                $bucket = $parts[5];
                $histogramBuckets[$encodedlabelNames . '|' . $encodedlabelValues][$bucket] = $value['value'];
            }

            // Compute all buckets
            $labelsNamesAndValues = array_keys($histogramBuckets);
            sort($labelsNamesAndValues);
            foreach ($labelsNamesAndValues as $labelNamesAndValues) {
                $parts = explode('|', $labelNamesAndValues);
                $decodedLabelNames = $this->decodeLabels($parts[0]);
                $decodedLabelValues = $this->decodeLabels($parts[1]);
                $data['labelNames'] = $decodedLabelNames;
                $data['buckets'] = apcu_fetch($this->histogramBucketsPerLabelsKey($data));
                unset($data['labelNames']);
                // Add the Inf bucket so we can compute it later on
                $data['buckets'][] = '+Inf';

                $acc = 0;
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (isset($histogramBuckets[$labelNamesAndValues][$bucket])) {
                        $acc += $histogramBuckets[$labelNamesAndValues][$bucket];
                    }
                    $data['samples'][] = [
                        'name' => $metaData['name'] . '_bucket',
                        'labelNames' => array_merge($decodedLabelNames, ['le']),
                        'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                        'value' => $acc,
                    ];
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => $decodedLabelNames,
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => $decodedLabelNames,
                    'labelValues' => $decodedLabelValues,
                    'value' => $this->fromBinaryRepresentationAsInteger($histogramBuckets[$labelNamesAndValues]['sum']),
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
    private function encodeLabels(array $values): string
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
    private function decodeLabels(string $values): array
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
