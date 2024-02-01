<?php

namespace Prometheus\Storage\RedisTxn\Metric;

use InvalidArgumentException;

/**
 * Fluent-builder for the {@see \Prometheus\Storage\RedisTxn\Metric\Metric} data structure.
 */
class HistogramMetricBuilder
{
    /**
     * @var Metadata|null
     */
    private $metadata = null;

    /**
     * @var array|null
     */
    private $samples = [];

    /**
     * @param Metadata $metadata
     * @return HistogramMetricBuilder
     */
    public function withMetadata(Metadata $metadata): HistogramMetricBuilder
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @param array $samples
     * @param array $labelValues
     * @return HistogramMetricBuilder
     */
    public function withSamples(array $samples, array $labelValues): HistogramMetricBuilder
    {
        $jsonLabelValues = json_encode($labelValues);
        $this->samples[$jsonLabelValues] = $this->processSamples($samples, $labelValues);
        return $this;
    }

    /**
     * @return Metric
     */
    public function build(): Metric
    {
        // Validate
        $this->validate();

        // Natural sort samples by label values
        ksort($this->samples, SORT_NATURAL);

        // Flatten observation samples into a single collection
        $samples = [];
        foreach ($this->samples as $observation) {
            foreach ($observation as $observationSample) {
                $samples[] = $observationSample->toArray();
            }
        }

        // Return metric
        return new Metric($this->metadata, $samples);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->metadata === null) {
            throw new InvalidArgumentException('Summary metadata field is required.');
        }

        if ($this->samples === null) {
            throw new InvalidArgumentException('Summary samples field is required.');
        }
    }

    /**
     * @param array $sourceSamples
     * @param array $labelValues
     * @return Sample[]
     */
    private function processSamples(array $sourceSamples, array $labelValues): array
    {
        // Return value
        $samples = [];

        // Calculate bucket samples
        $bucketSamples = 0.0;
        foreach ($this->metadata->getBuckets() as $bucket) {
            $bucketSamples += floatval($sourceSamples[$bucket] ?? 0.0);
            $name = $this->metadata->getName() . "_bucket";
            $samples[] = Sample::newBuilder()
                ->withName($name)
                ->withLabelNames(["le"])
                ->withLabelValues(array_merge($labelValues, [$bucket]))
                ->withValue($bucketSamples)
                ->build();
        }

        // Calculate bucket count
        $name = $this->metadata->getName() . "_count";
        $samples[] = Sample::newBuilder()
            ->withName($name)
            ->withLabelNames([])
            ->withLabelValues($labelValues)
            ->withValue($sourceSamples['count'] ?? 0)
            ->build();

        // Calculate bucket sum
        $name = $this->metadata->getName() . "_sum";
        $samples[] = Sample::newBuilder()
            ->withName($name)
            ->withLabelNames([])
            ->withLabelValues($labelValues)
            ->withValue($sourceSamples['sum'] ?? 0)
            ->build();

        // Return processed samples
        return $samples;
    }
}