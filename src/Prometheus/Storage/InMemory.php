<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\MetricFamilySamples;
use RuntimeException;

class InMemory implements Adapter
{

    private $counters = [];
    private $gauges = [];
    private $histograms = [];

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        $metrics = $this->internalCollect($this->counters);
        $metrics = array_merge($metrics, $this->internalCollect($this->gauges));
        $metrics = array_merge($metrics, $this->collectHistograms());
        return $metrics;
    }

    public function flushMemory(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * @return array
     */
    private function collectHistograms(): array
    {
        $histograms = [];
        foreach ($this->histograms as $histogram) {
            $metaData = $histogram['meta'];
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
            foreach ($histogram['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $labelValues = $parts[2];
                $bucket = $parts[3];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value;
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
                    'value' => $histogramBuckets[$labelValues]['sum'],
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }
        return $histograms;
    }

    /**
     * @param array $metrics
     * @return array
     */
    private function internalCollect(array $metrics): array
    {
        $result = [];
        foreach ($metrics as $metric) {
            $metaData = $metric['meta'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
            ];
            foreach ($metric['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $labelValues = $parts[2];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $value,
                ];
            }
            $this->sortSamples($data['samples']);
            $result[] = new MetricFamilySamples($data);
        }
        return $result;
    }

    /**
     * @param array $data
     * @return void
     */
    public function updateHistogram(array $data): void
    {
        // Initialize the sum
        $metaKey = $this->metaKey($data);
        if (array_key_exists($metaKey, $this->histograms) === false) {
            $this->histograms[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        if (array_key_exists($sumKey, $this->histograms[$metaKey]['samples']) === false) {
            $this->histograms[$metaKey]['samples'][$sumKey] = 0;
        }

        $this->histograms[$metaKey]['samples'][$sumKey] += $data['value'];


        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }

        $bucketKey = $this->histogramBucketValueKey($data, $bucketToIncrease);
        if (array_key_exists($bucketKey, $this->histograms[$metaKey]['samples']) === false) {
            $this->histograms[$metaKey]['samples'][$bucketKey] = 0;
        }
        $this->histograms[$metaKey]['samples'][$bucketKey] += 1;
    }

    /**
     * @param array $data
     */
    public function updateGauge(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $valueKey = $this->valueKey($data);
        if (array_key_exists($metaKey, $this->gauges) === false) {
            $this->gauges[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        if (array_key_exists($valueKey, $this->gauges[$metaKey]['samples']) === false) {
            $this->gauges[$metaKey]['samples'][$valueKey] = 0;
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->gauges[$metaKey]['samples'][$valueKey] = $data['value'];
        } else {
            $this->gauges[$metaKey]['samples'][$valueKey] += $data['value'];
        }
    }

    /**
     * @param array $data
     */
    public function updateCounter(array $data): void
    {
        $metaKey = $this->metaKey($data);
        $valueKey = $this->valueKey($data);
        if (array_key_exists($metaKey, $this->counters) === false) {
            $this->counters[$metaKey] = [
                'meta' => $this->metaData($data),
                'samples' => [],
            ];
        }
        if (array_key_exists($valueKey, $this->counters[$metaKey]['samples']) === false) {
            $this->counters[$metaKey]['samples'][$valueKey] = 0;
        }
        if ($data['command'] === Adapter::COMMAND_SET) {
            $this->counters[$metaKey]['samples'][$valueKey] = 0;
        } else {
            $this->counters[$metaKey]['samples'][$valueKey] += $data['value'];
        }
    }

    /**
     * @param array $data
     * @param string $bucket
     * @return string
     */
    private function histogramBucketValueKey(array $data, $bucket): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
        ]);
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function metaKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            'meta'
        ]);
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value'
        ]);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value']);
        unset($metricsMetaData['command']);
        unset($metricsMetaData['labelValues']);
        return $metricsMetaData;
    }

    /**
     * @param array $samples
     */
    private function sortSamples(array &$samples): void
    {
        usort($samples, function ($a, $b) {
            return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
        });
    }

    /**
     * @param array $values
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
     * @return array
     * @throws RuntimeException
     */
    private function decodeLabelValues($values): array
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
