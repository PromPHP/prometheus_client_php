<?php

namespace Test\Benchmark;

use InvalidArgumentException;
use Test\Benchmark\TestCase as BenchmarkTestCase;

class TestCaseBuilder
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
	 * @var int|null
	 */
	private $numSamples = null;

	/**
	 * @param int $adapterType
	 * @return TestCaseBuilder
	 */
	public function withAdapterType(int $adapterType): TestCaseBuilder
	{
		$this->adapterType = $adapterType;
		return $this;
	}

	/**
	 * @param int $metricType
	 * @return TestCaseBuilder
	 */
	public function withMetricType(int $metricType): TestCaseBuilder
	{
		$this->metricType = $metricType;
		return $this;
	}

	/**
	 * @param int $reportType
	 * @return TestCaseResultBuilder
	 */
	public function withReportType(int $reportType): TestCaseBuilder
	{
		$this->reportType = $reportType;
		return $this;
	}

	/**
	 * @param int $numKeys
	 * @return $this
	 */
	public function withNumKeys(int $numKeys): TestCaseBuilder
	{
		$this->numKeys = $numKeys;
		return $this;
	}

	/**
	 * @param int $numSamples
	 * @return $this
	 */
	public function withNumSamples(int $numSamples): TestCaseBuilder
	{
		$this->numSamples = $numSamples;
		return $this;
	}

	/**
	 * @return BenchmarkTestCase
	 * @throws InvalidArgumentException
	 */
	public function build(): BenchmarkTestCase
	{
		$this->validate();
		return new BenchmarkTestCase(
			$this->adapterType,
			$this->metricType,
			$this->reportType ?? ReportType::CSV,
			$this->numKeys ?? BenchmarkTestCase::DEFAULT_NUM_KEYS,
			$this->numSamples ?? BenchmarkTestCase::DEFAULT_NUM_SAMPLES
		);
	}

	/**
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function validate(): void
	{
		if ($this->adapterType === null) {
			throw new InvalidArgumentException('Missing adapter type');
		}

		if ($this->metricType === null) {
			throw new InvalidArgumentException('Missing metric type');
		}
	}
}
