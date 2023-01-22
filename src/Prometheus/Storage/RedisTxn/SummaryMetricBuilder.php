<?php

namespace Prometheus\Storage\RedisTxn;

use InvalidArgumentException;
use Prometheus\Math;

/**
 * Fluent-builder for the {@see \Prometheus\Storage\RedisTxn\Metric} data structure.
 */
class SummaryMetricBuilder
{
    /**
     * @var Metadata|null
     */
    private $metadata = null;

    /**
     * @var array|null
     */
    private $samples = null;

    /**
     * @param string $jsonMetadata JSON-encoded array of metadata fields.
     * @return SummaryMetricBuilder
     */
    public function withMetadata(string $jsonMetadata): SummaryMetricBuilder
    {
        $metadata = json_decode($jsonMetadata, true);
        $this->metadata = MetadataBuilder::fromArray($metadata)->build();
        return $this;
    }

    /**
     * @param array $samples
     * @return SummaryMetricBuilder
     */
    public function withSamples(array $samples): SummaryMetricBuilder
    {
        $this->samples = $this->processSummarySamples($samples);
        return $this;
    }

    /**
     * @return Metric
     */
    public function build(): Metric
    {
        $this->validate();
        return new Metric($this->metadata, $this->samples);
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
     * Calculates the configured quantiles, count, and sum for a summary metric given a set of observed values.
     *
     * @param array $sourceSamples
     * @return array
     */
    private function processSummarySamples(array $sourceSamples): array
    {
        // Return value
        $samples = [];

        // Coerce sample values to numeric type and strip off their unique suffixes
        //
        // NOTE: When we persist a summary metric sample into Redis, we write it into a Redis sorted set.
        // We append the current time in microseconds as a suffix on the observed value to make each observed value
        // durable and unique in the sorted set in accordance with best-practice guidelines described in the article,
        // "Redis Best Practices: Sorted Set Time Series" [1].
        //
        // See RedisTxn::updateSummary() for the complementary part of this operation.
        //
        // [1] https://redis.com/redis-best-practices/time-series/sorted-set-time-series/
        $typedSamples = array_map(function ($sample) {
            $tokens = explode(':', $sample);
            $sample = $tokens[0];
            return doubleval($sample);
        }, $sourceSamples);

        // Sort samples to calculate quantiles
        sort($typedSamples);

        // Calculate quantiles
        $math = new Math();
        foreach ($this->metadata->getQuantiles() as $quantile) {
            $value = $math->quantile($typedSamples, $quantile);
            $labelValues = array_merge($this->metadata->getLabelValues(), [$quantile]);
            $samples[] = Sample::newBuilder()
                ->withName($this->metadata->getName())
                ->withLabelNames(['quantile'])
                ->withLabelValues($labelValues)
                ->withValue($value)
                ->build()
                ->toArray();
        }

        // Calculate count
        $samples[] = Sample::newBuilder()
            ->withName($this->metadata->getName() . '_count')
            ->withLabelNames([])
            ->withLabelValues($this->metadata->getLabelValues())
            ->withValue(count($typedSamples))
            ->build()
            ->toArray();

        // Calculate sum
        $samples[] = Sample::newBuilder()
            ->withName($this->metadata->getName() . '_sum')
            ->withLabelNames([])
            ->withLabelValues($this->metadata->getLabelValues())
            ->withValue(array_sum($typedSamples))
            ->build()
            ->toArray();

        // Return processed samples
        return $samples;
    }
}
