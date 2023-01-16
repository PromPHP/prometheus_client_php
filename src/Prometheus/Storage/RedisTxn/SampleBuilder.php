<?php

namespace Prometheus\Storage\RedisTxn;

use InvalidArgumentException;

/**
 * Fluent-builder for the {@see \Prometheus\Storage\RedisTxn\Sample} structure.
 */
class SampleBuilder
{
	/**
	 * @var string|null
	 */
	private $name;

	/**
	 * @var string[]|null
	 */
	private $labelNames;

	/**
	 * @var float[]|int[]|null
	 */
	private $labelValues;

	/**
	 * @var float|int|null
	 */
	private $value;

	/**
	 * @param string $name
	 * @return SampleBuilder
	 */
	public function withName(string $name): SampleBuilder
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string[] $labelNames
	 * @return SampleBuilder
	 */
	public function withLabelNames(array $labelNames): SampleBuilder
	{
		$this->labelNames = $labelNames;
		return $this;
	}

	/**
	 * @param float[]|int[] $labelValues
	 * @return SampleBuilder
	 */
	public function withLabelValues(array $labelValues): SampleBuilder
	{
		$this->labelValues = $labelValues;
		return $this;
	}

	/**
	 * @param float|int $value
	 * @return SampleBuilder
	 */
	public function withValue($value): SampleBuilder
	{
		$this->value = $value;
		return $this;
	}

	/**
	 * @return Sample
	 */
	public function build(): Sample
	{
		$this->validate();
		return new Sample(
			$this->name,
			$this->labelNames ?? [],
			$this->labelValues ?? [],
			$this->value
		);
	}

	/**
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function validate(): void
	{
		if ($this->name === null) {
			throw new InvalidArgumentException('Sample name field is required');
		}

		if ($this->value === null) {
			throw new InvalidArgumentException('Sample name field is required');
		}
	}
}
