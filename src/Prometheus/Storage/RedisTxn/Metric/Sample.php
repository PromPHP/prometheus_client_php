<?php

namespace Prometheus\Storage\RedisTxn\Metric;

/**
 * This structure represent a single observed value for a metric.
 *
 * It may seem to be duplicative of the {@see \Prometheus\Sample} structure but it better supports the fluent-builder
 * pattern and allows us to reduce dealing with implicit structures of PHP associative arrays like the one returned
 * by {@see Sample::toArray()}.
 *
 * This is merely a structure of perferential convenience, tightly-scoped as an internal detail
 * of the {@see \Prometheus\Storage\RedisTxn} adapter.
 */
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array|string[]
     */
    public function getLabelNames(): array
    {
        return $this->labelNames;
    }

    /**
     * @return array
     */
    public function getLabelValues(): array
    {
        return $this->labelValues;
    }

    /**
     * @return float|int
     */
    public function getValue()
    {
        return $this->value;
    }

	/**
     * Represents this structure as a PHP associative array.
     * 
     * This array generally conforms to the expectations of the {@see \Prometheus\MetricFamilySamples} structure.
     *
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
