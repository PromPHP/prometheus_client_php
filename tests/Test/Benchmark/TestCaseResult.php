<?php

namespace Test\Benchmark;

class TestCaseResult
{
	/**
	 * @var int
	 */
	private $adapterType;

	/**
	 * @var int
	 */
	private $metricType;

	/**
	 * @var int
	 */
	private $reportType;

	/**
	 * @var int
	 */
	private $numKeys;

	/**
	 * @var array
	 */
	private $writeResults;

	/**
	 * @var array
	 */
	private $renderResults;

	/**
	 * @return TestCaseResultBuilder
	 */
	public static function newBuilder(): TestCaseResultBuilder
	{
		return new TestCaseResultBuilder();
	}

	/**
	 * @param int $adapterType
	 * @param int $metricType
	 * @param int $reportType
	 * @param int $numKeys
	 * @param array $writeResults
	 * @param array $renderResults
	 */
	public function __construct(
		int $adapterType,
		int $metricType,
		int $reportType,
		int $numKeys,
		array $writeResults,
		array $renderResults
	)
	{
		$this->adapterType = $adapterType;
		$this->metricType = $metricType;
		$this->reportType = $reportType;
		$this->numKeys = $numKeys;
		$this->writeResults = $writeResults;
		$this->renderResults = $renderResults;
	}

	/**
	 * @return string
	 */
	public function report(): string
	{
        assert(count($this->writeResults) === count($this->renderResults));

		sort($this->writeResults);
		sort($this->renderResults);

		return ($this->reportType === ReportType::CSV)
			? $this->toCsv()
			: $this->toJson();
	}

	private function toCsv(): string
	{
		return implode(',', [
			AdapterType::toString($this->adapterType),
			MetricType::toString($this->metricType),
			$this->numKeys,
			count($this->writeResults),
			$this->quantile($this->writeResults, 0.50),
			$this->quantile($this->writeResults, 0.75),
			$this->quantile($this->writeResults, 0.95),
			$this->quantile($this->writeResults, 0.99),
			min($this->writeResults),
			max($this->writeResults),
			array_sum($this->writeResults) / count($this->writeResults),
			$this->quantile($this->renderResults, 0.50),
			$this->quantile($this->renderResults, 0.75),
			$this->quantile($this->renderResults, 0.95),
			$this->quantile($this->renderResults, 0.99),
			min($this->renderResults),
			max($this->renderResults),
			array_sum($this->renderResults) / count($this->renderResults),
		]);
	}

	/**
	 * @return string
	 */
	private function toJson(): string
	{
		return json_encode([
			'adapter' => AdapterType::toString($this->adapterType),
			'metric' => MetricType::toString($this->metricType),
			'num-keys' => $this->numKeys,
			'num-samples' => count($this->writeResults),
			'tests' => [
				'write' => [
					'50' => $this->quantile($this->writeResults, 0.50),
					'75' => $this->quantile($this->writeResults, 0.75),
					'95' => $this->quantile($this->writeResults, 0.95),
					'99' => $this->quantile($this->writeResults, 0.99),
					'min' => min($this->writeResults),
					'max' => max($this->writeResults),
					'avg' => array_sum($this->writeResults) / count($this->writeResults),
				],
				'render' => [
					'50' => $this->quantile($this->renderResults, 0.50),
					'75' => $this->quantile($this->renderResults, 0.75),
					'95' => $this->quantile($this->renderResults, 0.95),
					'99' => $this->quantile($this->renderResults, 0.99),
					'min' => min($this->renderResults),
					'max' => max($this->renderResults),
					'avg' => array_sum($this->renderResults) / count($this->renderResults),
				],
			],
		]);
	}

	/**
	 * @param array $data
	 * @param float $quantile
	 * @return float
	 */
	private function quantile(array $data, float $quantile): float
	{
		$count = count($data);
		if ($count === 0) {
			return 0;
		}

		$j = floor($count * $quantile);
		$r = $count * $quantile - $j;
		if (0.0 === $r) {
			return $data[$j - 1];
		}
		return $data[$j];
	}
}
