<?php


namespace Prometheus;


use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;

class CollectorRegistry
{
    /**
     * @var CollectorRegistry
     */
    private static $defaultRegistry;

    /**
     * @var Adapter
     */
    private $storageAdapter;
    /**
     * @var Gauge[]
     */
    private $gauges = array();
    /**
     * @var Counter[]
     */
    private $counters = array();
    /**
     * @var Histogram[]
     */
    private $histograms = array();

    public function __construct(Adapter $redisAdapter)
    {
        $this->storageAdapter = $redisAdapter;
    }

    /**
     * @return CollectorRegistry
     */
    public static function getDefault()
    {
        if (!self::$defaultRegistry) {
            return self::$defaultRegistry = new static(new Redis());
        }
        return self::$defaultRegistry;
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     */
    public function registerGauge($namespace, $name, $help, $labels = array())
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (isset($this->gauges[$metricIdentifier])) {
            throw new MetricsRegistrationException("Metric already registered");
        }
        $this->gauges[$metricIdentifier] = new Gauge(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->gauges[$metricIdentifier];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     */
    public function getGauge($namespace, $name, $labels = array())
    {
        return $this->gauges[self::metricIdentifier($namespace, $name)];
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function getMetricFamilySamples()
    {
        return $this->storageAdapter->collect();
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     */
    public function getCounter($namespace, $name, $labels = array())
    {
        return $this->counters[self::metricIdentifier($namespace, $name)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. requests
     * @param string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     */
    public function registerCounter($namespace, $name, $help, $labels = array())
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (isset($this->counters[$metricIdentifier])) {
            throw new MetricsRegistrationException("Metric already registered");
        }
        $this->counters[$metricIdentifier] = new Counter(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->counters[self::metricIdentifier($namespace, $name)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @param array $buckets e.g. [100, 200, 300]
     * @return Histogram
     */
    public function registerHistogram($namespace, $name, $help, $labels = array(), $buckets = null)
    {
        $metricIdentifier = self::metricIdentifier($namespace, $name);
        if (isset($this->histograms[$metricIdentifier])) {
            throw new MetricsRegistrationException("Metric already registered");
        }
        $this->histograms[$metricIdentifier] = new Histogram(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels,
            $buckets
        );
        return $this->histograms[$metricIdentifier];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Histogram
     */
    public function getHistogram($namespace, $name, $labels = array())
    {
        return $this->histograms[self::metricIdentifier($namespace, $name)];
    }

    private static function metricIdentifier($namespace, $name)
    {
        return $namespace . $name;
    }
}
