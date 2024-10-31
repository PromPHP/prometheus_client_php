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
    private $updateResults;

    /**
     * @var array
     */
    private $collectResults;

    /**
     * @return TestCaseResultBuilder
     */
    public static function newBuilder(): TestCaseResultBuilder
    {
        return new TestCaseResultBuilder();
    }

    /**
     * @return string[]
     */
    public static function getCsvHeaders(): array
    {
        return [
            'adapter',
            'metric',
            'num-keys',
            'num-samples',
            'update-p50',
            'update-p75',
            'update-p95',
            'update-p99',
            'update-min',
            'update-max',
            'update-avg',
            'collect-p50',
            'collect-p75',
            'collect-p95',
            'collect-p99',
            'collect-min',
            'collect-max',
            'collect-avg',
        ];
    }

    /**
     * @param int $adapterType
     * @param int $metricType
     * @param int $reportType
     * @param int $numKeys
     * @param array $updateResults
     * @param array $collectResults
     */
    public function __construct(
        int   $adapterType,
        int   $metricType,
        int   $reportType,
        int   $numKeys,
        array $updateResults,
        array $collectResults
    )
    {
        $this->adapterType = $adapterType;
        $this->metricType = $metricType;
        $this->reportType = $reportType;
        $this->numKeys = $numKeys;
        $this->updateResults = $updateResults;
        $this->collectResults = $collectResults;
    }

    /**
     * @return string
     */
    public function report(): string
    {
        assert(count($this->updateResults) === count($this->collectResults));

        sort($this->updateResults);
        sort($this->collectResults);

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
            count($this->updateResults),
            $this->quantile($this->updateResults, 0.50),
            $this->quantile($this->updateResults, 0.75),
            $this->quantile($this->updateResults, 0.95),
            $this->quantile($this->updateResults, 0.99),
            min($this->updateResults),
            max($this->updateResults),
            array_sum($this->updateResults) / count($this->updateResults),
            $this->quantile($this->collectResults, 0.50),
            $this->quantile($this->collectResults, 0.75),
            $this->quantile($this->collectResults, 0.95),
            $this->quantile($this->collectResults, 0.99),
            min($this->collectResults),
            max($this->collectResults),
            array_sum($this->collectResults) / count($this->collectResults),
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
            'num-samples' => count($this->updateResults),
            'tests' => [
                'write' => [
                    '50' => $this->quantile($this->updateResults, 0.50),
                    '75' => $this->quantile($this->updateResults, 0.75),
                    '95' => $this->quantile($this->updateResults, 0.95),
                    '99' => $this->quantile($this->updateResults, 0.99),
                    'min' => min($this->updateResults),
                    'max' => max($this->updateResults),
                    'avg' => array_sum($this->updateResults) / count($this->updateResults),
                ],
                'render' => [
                    '50' => $this->quantile($this->collectResults, 0.50),
                    '75' => $this->quantile($this->collectResults, 0.75),
                    '95' => $this->quantile($this->collectResults, 0.95),
                    '99' => $this->quantile($this->collectResults, 0.99),
                    'min' => min($this->collectResults),
                    'max' => max($this->collectResults),
                    'avg' => array_sum($this->collectResults) / count($this->collectResults),
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
