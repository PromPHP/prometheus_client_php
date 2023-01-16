<?php

namespace Prometheus\Storage\Rediser;

class Metadata
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $help;

	/**
	 * @var string[]
	 */
	private $labelNames;

	/**
	 * @var mixed[]
	 */
	private $labelValues;

	/**
	 * @var int
	 */
	private $maxAgeSeconds;

	/**
	 * @var float[]
	 */
	private $quantiles;

	/**
	 * @return MetadataBuilder
	 */
	public static function newBuilder(): MetadataBuilder
	{
		return new MetadataBuilder();
	}

	/**
	 * @param string $name
	 * @param string $help
	 * @param array $labelNames
	 * @param array $labelValues
	 * @param int $maxAgeSeconds
	 * @param array $quantiles
	 */
	public function __construct(
		string $name,
		string $help,
		array $labelNames,
		array $labelValues,
		int $maxAgeSeconds,
		array $quantiles
	)
	{
		$this->name = $name;
		$this->help = $help;
		$this->labelNames = $labelNames;
		$this->labelValues = $labelValues;
		$this->maxAgeSeconds = $maxAgeSeconds;
		$this->quantiles = $quantiles;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getHelp(): string
	{
		return $this->help;
	}

	/**
	 * @return string[]
	 */
	public function getLabelNames(): array
	{
		return $this->labelNames;
	}

	/**
	 * @return mixed[]
	 */
	public function getLabelValues(): array
	{
		return $this->labelValues;
	}

	/**
	 * @return int
	 */
	public function getMaxAgeSeconds(): int
	{
		return $this->maxAgeSeconds;
	}

	/**
	 * @return array
	 */
	public function getQuantiles(): array
	{
		return $this->quantiles;
	}
}
