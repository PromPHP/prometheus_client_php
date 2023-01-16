<?php

namespace Test\Benchmark;

class TestCaseResultBuilder
{
	/**
	 * @var int|null
	 */
	private $adapterType = null;

	/**
	 * @var int|null
	 */
	private $metricType = null;

	/**
	 * @var int|null
	 */
	private $reportType = null;

	/**
	 * @var int|null
	 */
	private $numKeys = null;

	/**
	 * @var array
	 */
	private $writeResults = [];

	/**
	 * @var array
	 */
	private $renderResults = [];

	/**
	 * @param int $adapterType
	 * @return TestCaseResultBuilder
	 */
	public function withAdapterType(int $adapterType): TestCaseResultBuilder
	{
		$this->adapterType = $adapterType;
		return $this;
	}

	/**
	 * @param int $metricType
	 * @return TestCaseResultBuilder
	 */
	public function withMetricType(int $metricType): TestCaseResultBuilder
	{
		$this->metricType = $metricType;
		return $this;
	}

	/**
	 * @param int $reportType
	 * @return TestCaseResultBuilder
	 */
	public function withReportType(int $reportType): TestCaseResultBuilder
	{
		$this->reportType = $reportType;
		return $this;
	}

	/**
	 * @param int $numKeys
	 * @return TestCaseResultBuilder
	 */
	public function withNumKeys(int $numKeys): TestCaseResultBuilder
	{
		$this->numKeys = $numKeys;
		return $this;
	}

	/**
	 * @param float $result
	 * @return TestCaseResultBuilder
	 */
	public function withWriteResult(float $result): TestCaseResultBuilder
	{
		$this->writeResults[] = $result;
		return $this;
	}

	/**
	 * @param float $result
	 * @return TestCaseResultBuilder
	 */
	public function withRenderResult(float $result): TestCaseResultBuilder
	{
		$this->renderResults[] = $result;
		return $this;
	}

	/**
	 * @return TestCaseResult
	 */
	public function build(): TestCaseResult
	{
		return new TestCaseResult(
			$this->adapterType,
			$this->metricType,
			$this->reportType,
			$this->numKeys,
			$this->writeResults,
			$this->renderResults
		);
	}
}
