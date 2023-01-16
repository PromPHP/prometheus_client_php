<?php

namespace Prometheus\Storage\RedisTxn;

use InvalidArgumentException;

/**
 * Fluent-builder for the {@see \Prometheus\Storage\RedisTxn\Metadata} structure.
 */
class MetadataBuilder
{
	/**
	 * @var string|null
	 */
	private $name;

	/**
	 * @var string|null
	 */
	private $help;

	/**
	 * @var string[]|null
	 */
	private $labelNames;

	/**
	 * @var mixed[]|null
	 */
	private $labelValues;

	/**
	 * @var int|null
	 */
	private $maxAgeSeconds;

	/**
	 * @var float[]|null
	 */
	private $quantiles;

	/**
	 * @param string $name
	 * @return MetadataBuilder
	 */
	public function withName(string $name): MetadataBuilder
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string|null $help
	 * @return MetadataBuilder
	 */
	public function withHelp(?string $help): MetadataBuilder
	{
		$this->help = $help;
		return $this;
	}

	/**
	 * @param string[]|null $labelNames
	 * @return MetadataBuilder
	 */
	public function withLabelNames(?array $labelNames): MetadataBuilder
	{
		$this->labelNames = $labelNames;
		return $this;
	}

	/**
	 * @param string|array|null $labelValues
	 * @return MetadataBuilder
	 */
	public function withLabelValues($labelValues): MetadataBuilder
	{
        if (is_array($labelValues)) {
            $this->labelValues = $labelValues;
        } else {
            // See Metadata::getLabelNamesEncoded() for the inverse operation on this data.
            $this->labelValues = json_decode(base64_decode($labelValues));
        }
        return $this;
	}

	/**
	 * @param int|null $maxAgeSeconds
	 * @return MetadataBuilder
	 */
	public function withMaxAgeSeconds(?int $maxAgeSeconds): MetadataBuilder
	{
		$this->maxAgeSeconds = $maxAgeSeconds;
		return $this;
	}

	/**
	 * @param float[]|null $quantiles
	 * @return MetadataBuilder
	 */
	public function withQuantiles(?array $quantiles): MetadataBuilder
	{
		$this->quantiles = $quantiles;
		return $this;
	}

	/**
	 * @return Metadata
	 */
	public function build(): Metadata
	{
		$this->validate();
		return new Metadata(
			$this->name,
			$this->help ?? '',
			$this->labelNames ?? [],
			$this->labelValues ?? [],
			$this->maxAgeSeconds ?? 0,
			$this->quantiles ?? []
		);
	}

	/**
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function validate(): void
	{
		if ($this->name === null) {
			throw new InvalidArgumentException('Metadata name field is required');
		}
	}
}
