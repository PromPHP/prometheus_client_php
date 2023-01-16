<?php

namespace Prometheus\Storage\Rediser;

use Prometheus\Math;
use Prometheus\Summary as PrometheusSummary;

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
