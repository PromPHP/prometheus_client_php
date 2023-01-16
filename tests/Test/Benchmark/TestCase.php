<?php

namespace Test\Benchmark;

use Exception;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;
use Prometheus\Storage\RedisTxn;
use Prometheus\Storage\RedisNg;

class TestCase
{
	public const DEFAULT_METRIC_NAMESPACE = 'pcp';
    public const DEFAULT_METRIC_HELP = '';
	public const DEFAULT_NUM_KEYS = 10000;
	public const DEFAULT_NUM_SAMPLES = 10;
	public const REDIS_DB = 0;
	public const REDIS_PORT = 6379;
	public const REDIS_HOST = 'redis';

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
	 * @var int
	 */
	private $numSamples;

	/**
	 * @var Adapter|null
	 */
	private $adapter = null;

	/**
	 * @var RegistryInterface|null
	 */
	private $registry = null;

	/**
	 * @return TestCaseBuilder
	 */
	public static function newBuilder(): TestCaseBuilder
	{
		return new TestCaseBuilder();
	}

	/**
	 * @param int $adapterType
	 * @param int $metricType
	 * @param int $reportType
	 * @param int $numKeys
	 * @param int $numSamples
	 */
	public function __construct(
		int $adapterType,
		int $metricType,
		int $reportType,
		int $numKeys,
		int $numSamples
	)
	{
		$this->adapterType = $adapterType;
		$this->metricType = $metricType;
		$this->reportType = $reportType;
		$this->numKeys = $numKeys;
		$this->numSamples = $numSamples;
		$this->getRegistry();
	}

    /**
     * @return int
     */
    public function getAdapterType(): int
    {
        return $this->adapterType;
    }

    /**
     * @return int
     */
    public function getMetricType(): int
    {
        return $this->metricType;
    }

    /**
     * @return int
     */
    public function getNumKeys(): int
    {
        return $this->numKeys;
    }

    /**
     * @return int
     */
    public function getNumSamples(): int
    {
        return $this->numSamples;
    }

	/**
	 * @return TestCaseResult
	 */
	public function execute(): TestCaseResult
	{
		// Setup test
		$this->executeSeed();

		// Create result builder
		$builder = TestCaseResult::newBuilder()
			->withAdapterType($this->adapterType)
			->withMetricType($this->metricType)
			->withNumKeys($this->numKeys)
			->withReportType($this->reportType);

		// Run render tests
        for ($i = 0; $i < $this->numSamples; $i++) {
			$result = $this->executeRender();
			$builder->withRenderResult($result);
		}

        // Run write tests
        for ($i = 0; $i < $this->numSamples; $i++) {
            $result = $this->executeWrite();
            $builder->withWriteResult($result);
        }

		// Build result
		return $builder->build();
	}

	/**
	 * @return Adapter
	 */
	private function getAdapter(): Adapter
	{
        if ($this->adapter === null) {
            switch ($this->adapterType) {
                case AdapterType::REDIS:
                    $config = $this->getRedisConfig();
                    $this->adapter = new Redis($config);
                    break;
                case AdapterType::REDISNG:
                    $config = $this->getRedisConfig();
                    $this->adapter = new RedisNg($config);
                    break;
                case AdapterType::REDISTXN:
                    $config = $this->getRedisConfig();
                    $this->adapter = new RedisTxn($config);
                    break;
                default:
                    break;
            }
        }
		return $this->adapter;
	}

	/**
	 * @return RegistryInterface
	 */
	private function getRegistry(): RegistryInterface
	{
		if ($this->registry === null) {
            $this->registry = new CollectorRegistry($this->getAdapter(), false);
		}
		return $this->registry;
	}

    /**
     * @return array
     */
    private function getRedisConfig(): array
    {
        return [
            'host' => $_SERVER['REDIS_HOST'] ?? self::REDIS_HOST,
            'port' => self::REDIS_PORT,
            'database' => self::REDIS_DB,
        ];
    }

	/**
	 * @return void
	 */
	private function executeSeed(): void
	{
		$this->getAdapter()->wipeStorage();
		for ($i = 0; $i < $this->numKeys; $i++) {
			$this->emitMetric();
		}
	}

	/**
	 * @return float
	 * @throws Exception
	 */
	private function executeWrite(): float
	{
		// Write test key
		$start = microtime(true);
		$this->emitMetric();
		return microtime(true) - $start;
	}

	/**
	 * @return float
	 */
	private function executeRender(): float
	{
		$start = microtime(true);
		$this->render();
		return microtime(true) - $start;
	}

	/**
	 * @return string
     * @throws MetricsRegistrationException
     * @throws Exception
	 */
	private function emitMetric(): string
	{
		$key = '';
		$registry = $this->getRegistry();
		switch ($this->metricType) {
			case MetricType::COUNTER:
				$key = uniqid('counter_', false);
				$registry->getOrRegisterCounter(self::DEFAULT_METRIC_NAMESPACE, $key, self::DEFAULT_METRIC_HELP)->inc();
				break;
			case MetricType::GAUGE:
				$key = uniqid('gauge_', false);
				$registry->getOrRegisterGauge(self::DEFAULT_METRIC_NAMESPACE, $key, self::DEFAULT_METRIC_HELP)->inc();;
				break;
			case MetricType::HISTOGRAM:
				$key = uniqid('histogram_', false);
                $value = random_int(1, PHP_INT_MAX);
                $registry->getOrRegisterHistogram(self::DEFAULT_METRIC_NAMESPACE, $key, self::DEFAULT_METRIC_HELP)->observe($value);
				break;
			case MetricType::SUMMARY:
				$key = uniqid('timer_', false);
                $value = random_int(1, PHP_INT_MAX);
                $registry->getOrRegisterSummary(self::DEFAULT_METRIC_NAMESPACE, $key, self::DEFAULT_METRIC_HELP)->observe($value);
				break;
		}
		return $key;
	}

	/**
	 * @return string
	 */
	private function render(): string
	{
		$renderer = new RenderTextFormat();
		return $renderer->render($this->getRegistry()->getMetricFamilySamples());
	}
}
