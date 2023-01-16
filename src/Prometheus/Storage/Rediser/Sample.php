<?php

namespace Prometheus\Storage\Rediser;

class Sample
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string[]
	 */
	private $labelNames;

	/**
	 * @var float[]|int[]
	 */
	private $labelValues;

	/**
	 * @var float|int
	 */
	private $value;

	/**
	 * @return SampleBuilder
	 */
	public static function newBuilder(): SampleBuilder
	{
		return new SampleBuilder();
	}

	/**
	 * @param string $name
	 * @param array $labelNames
	 * @param array $labelValues
	 * @param float|int $value
	 */
	public function __construct(
		string $name,
		array $labelNames,
		array $labelValues,
		$value
	)
	{
		$this->name = $name;
		$this->labelNames = $labelNames;
		$this->labelValues = $labelValues;
		$this->value = $value;
	}

	/**
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'name' => $this->name,
			'labelNames' => $this->labelNames,
			'labelValues' => $this->labelValues,
			'value' => $this->value,
		];
	}
}
