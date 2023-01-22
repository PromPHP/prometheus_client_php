<?php

namespace Prometheus\Storage\RedisTxn;

use InvalidArgumentException;

class ScalarMetricBuilder
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
     * @param Metadata $metadata
     * @return ScalarMetricBuilder
     */
    public function withMetadata(Metadata $metadata): ScalarMetricBuilder
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @param string $sample
     * @param array $labelValues
     * @return ScalarMetricBuilder
     */
    public function withSample(string $sample, array $labelValues): ScalarMetricBuilder
    {
        $sample = $this->coerceSampleType($sample);
        $jsonLabelValues = json_encode($labelValues);
        $this->samples[$jsonLabelValues] = $this->toSample($sample, $labelValues);
        return $this;
    }

    /**
     * @return Metric
     */
    public function build(): Metric
    {
        $this->validate();
        ksort($this->samples);
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
     * @param float|int $sourceSample
     * @param array $labelValues
     * @return array
     */
    private function toSample($sourceSample, array $labelValues): array
    {
        return Sample::newBuilder()
            ->withName($this->metadata->getName())
            ->withLabelNames([])
            ->withLabelValues($labelValues)
            ->withValue($sourceSample)
            ->build()
            ->toArray();
    }

    /**
     * @param string $sample
     * @return float|int
     */
    private function coerceSampleType(string $sample)
    {
        return (floatval($sample) && floatval($sample) != intval($sample))
            ? floatval($sample)
            : intval($sample);
    }
}