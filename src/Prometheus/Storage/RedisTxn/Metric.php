<?php

namespace Prometheus\Storage\RedisTxn;

use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use Prometheus\Summary as PrometheusSummary;

/**
 * This structure represents all the data associated with a single, unique metric that this library
 * should present to a Prometheus scraper.
 *
 * This data generally comprises a set of "metadata" related to the definition of the metric and a
 * set of "samples" which are the observed values for the metric over some trailing window of time.
 */
class Metric
{
	/**
	 * @var Metadata
	 */
	private $metadata;

	/**
	 * @var double[]|float[]|int[]
	 */
	private $samples;

	/**
	 * @return MetricBuilder
	 */
	public static function newBuilder(): MetricBuilder
	{
		return new MetricBuilder();
	}

	/**
	 * @param Metadata $metadata
	 * @param array $samples
	 */
	public function __construct(Metadata $metadata, array $samples)
	{
		$this->metadata = $metadata;
		$this->samples = $samples;
	}

	/**
     * Represents this data structure as a PHP associative array.
     *
     * This array generally conforms to the expectations of the {@see \Prometheus\MetricFamilySamples} structure.
     *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'name' => $this->metadata->getName(),
			'help' => $this->metadata->getHelp(),
			'type' => PrometheusSummary::TYPE,
			'labelNames' => $this->metadata->getLabelNames(),
			'maxAgeSeconds' => $this->metadata->getMaxAgeSeconds(),
			'quantiles' => $this->metadata->getQuantiles(),
			'samples' => $this->samples,
		];
	}
}
