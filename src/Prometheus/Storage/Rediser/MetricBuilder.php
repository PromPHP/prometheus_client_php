<?php

namespace Prometheus\Storage\Rediser;

use InvalidArgumentException;
use Prometheus\Math;

class MetricBuilder
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
	 * @return MetricBuilder
	 */
	public function withMetadata(string $jsonMetadata): MetricBuilder
	{
		$metadata = json_decode($jsonMetadata, true);
		$this->metadata = Metadata::newBuilder()
			->withName($metadata['name'])
			->withHelp($metadata['help'] ?? null)
			->withLabelNames($metadata['labelNames'] ?? null)
            ->withLabelValues($metadata['labelValues'] ?? null)
			->withMaxAgeSeconds($metadata['maxAgeSeconds'] ?? null)
			->withQuantiles($metadata['quantiles'] ?? null)
			->build();
		return $this;
	}

	/**
	 * @param array $samples
	 * @return MetricBuilder
	 */
	public function withSamples(array $samples): MetricBuilder
	{
		$this->samples = $this->processSamples($samples);
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
     * @param array $sourceSamples
     * @return array
     */
    private function processSamples(array $sourceSamples): array
    {
        // Return value
        $samples = [];

        // Coerce sample values to numeric type
        $typedSamples = array_map(function ($sample) {
            return doubleval($sample);
//            if (is_double($sample)) {
//                return doubleval($sample);
//            }
//            if (is_float($sample)) {
//                return floatval($sample);
//            }
//            return intval($sample);
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
