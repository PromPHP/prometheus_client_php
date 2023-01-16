<?php

namespace Prometheus\Storage\RedisTxn;

/**
 * Stores a mix of metadata related to a Prometheus metric.
 *
 * Some metadata is served to a Prometheus scraper and some is reserved for internal use by the storage adpater.
 */
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
     * Prometheus metric name.
     *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
     * Prometheus metric help description.
     *
     * @internal Optional.
	 * @return string
	 */
	public function getHelp(): string
	{
		return $this->help;
	}

	/**
     * Prometheus metric label names.
     *
     * Note that each label introduces a degree of cardinality for a given metric.
     *
     * @internal Optional. It is permissible to have no label names.
	 * @return string[]
	 */
	public function getLabelNames(): array
	{
		return $this->labelNames;
	}

	/**
     * Prometheus metric label values.
     *
     * Note that each label value should correspond to a label name.
     *
     * @internal Optional.
	 * @return mixed[]
	 */
	public function getLabelValues(): array
	{
		return $this->labelValues;
	}

    /**
     * Prometheus metric label values encoded for storage in Redis.
     *
     * This property is used internally by the storage adapter and is not served to a Prometheus scraper. Instead,
     * the scraper receives the result from the {@see Metadata::getLabelValues()} accessor.
     *
     * @return string
     */
    public function getLabelValuesEncoded(): string
    {
        return base64_encode(json_encode($this->labelValues));
    }

	/**
     * Prometheus metric time-to-live (TTL) in seconds.
     *
     * This property is used internally by the storage adapter to enforce a TTL for metrics stored in Redis.
     *
	 * @return int
	 */
	public function getMaxAgeSeconds(): int
	{
		return $this->maxAgeSeconds;
	}

	/**
     * Prometheus metric metadata that describes the set of quantiles to report for a summary-type metric.
     *
	 * @return array
	 */
	public function getQuantiles(): array
	{
		return $this->quantiles;
	}

    /**
     * Represents this data structure as a JSON object.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'name' => $this->getName(),
            'help' => $this->getHelp(),
            'labelNames' => $this->getLabelNames(),
            'labelValues' => $this->getLabelValuesEncoded(),
            'maxAgeSeconds' => $this->getMaxAgeSeconds(),
            'quantiles' => $this->getQuantiles(),
        ]);
    }
}
