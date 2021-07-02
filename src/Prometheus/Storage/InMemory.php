<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\MetricFamilySamples;
use RuntimeException;

class InMemory implements Adapter
{

    /**
     * @var mixed[]
     */
    protected $counters = [];

    /**
     * @var mixed[]
     */
    protected $gauges = [];

    /**
     * @var mixed[]
     */
    protected $histograms = [];

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

    /**
     * @deprecated use replacement method wipeStorage from Adapter interface
     */
    public function flushMemory(): void
    {
        $this->wipeStorage();
    }

    /**
     * @inheritDoc
     */
    public function wipeStorage(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectHistograms(): array
    {
        $histograms = [];
        foreach ($this->histograms as $histogram) {
            $metaData = $histogram['meta'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
            ];


            $histogramBuckets = [];
            foreach ($histogram['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $encodedlabelNames = $parts[2];
                $encodedlabelValues = $parts[3];
                $bucket = $parts[4];
                $histogramBuckets[$encodedlabelNames . '|' . $encodedlabelValues][$bucket] = $value;
            }

            // Compute all buckets
            $labelsNamesAndValues = array_keys($histogramBuckets);
            sort($labelsNamesAndValues);
            foreach ($labelsNamesAndValues as $labelNamesAndValues) {
                $parts = explode('|', $labelNamesAndValues);
                $encodedLabelNames = $parts[0];
                $decodedLabelNames = $this->decodeLabels($encodedLabelNames);
                $decodedLabelValues = $this->decodeLabels($parts[1]);
                $data['buckets'] = $histogram['bucketsPerLabels'][$encodedLabelNames];
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
                    'value' => $histogramBuckets[$labelNamesAndValues]['sum'],
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }
        return $histograms;
    }

    /**
     * @param mixed[] $metrics
     * @return MetricFamilySamples[]
     */
    protected function internalCollect(array $metrics): array
    {
        $result = [];
        foreach ($metrics as $metric) {
            $metaData = $metric['meta'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'samples' => [],
            ];
            foreach ($metric['samples'] as $key => $value) {
                $parts = explode(':', $key);
                $labelValues = $parts[3];
                $labelNamesEncoded = $parts[2];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => $this->decodeLabels($labelNamesEncoded),
                    'labelValues' => $this->decodeLabels($labelValues),
                    'value' => $value,
                ];
            }
            $this->sortSamples($data['samples']);
            $result[] = new MetricFamilySamples($data);
        }
        return $result;
    }

    /**
     * @param mixed[] $data
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
                'bucketsPerLabels' => [],
            ];
        }
        $encodedLabelNames = $this->encodeLabels($data['labelNames']);
        if (array_key_exists($encodedLabelNames, $this->histograms[$metaKey]['bucketsPerLabels']) === false) {
            $this->histograms[$metaKey]['bucketsPerLabels'][$encodedLabelNames] = $data['buckets'];
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
     * @param mixed[] $data
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
     * @param mixed[] $data
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
     * @param mixed[]    $data
     * @param string|int $bucket
     *
     * @return string
     */
    protected function histogramBucketValueKey(array $data, $bucket): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabels($data['labelNames']),
            $this->encodeLabels($data['labelValues']),
            $bucket,
        ]);
    }

    /**
     * @param mixed[] $data
     *
     * @return string
     */
    protected function metaKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            'meta'
        ]);
    }

    /**
     * @param mixed[] $data
     *
     * @return string
     */
    protected function valueKey(array $data): string
    {
        return implode(':', [
            $data['type'],
            $data['name'],
            $this->encodeLabels($data['labelNames']),
            $this->encodeLabels($data['labelValues']),
            'value'
        ]);
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     */
    protected function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset(
            $metricsMetaData['value'],
            $metricsMetaData['command'],
            $metricsMetaData['labelValues'],
            $metricsMetaData['buckets']
        );
        return $metricsMetaData;
    }

    /**
     * @param mixed[] $samples
     */
    protected function sortSamples(array &$samples): void
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
    protected function encodeLabels(array $values): string
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
    protected function decodeLabels(string $values): array
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
