<?php


namespace Prometheus;


use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;

class CollectorRegistry
{
    /**
     * @var CollectorRegistry
     */
    private static $defaultRegistry;

    /**
     * @var string
     */
    private static $defaultRedisOptions;

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
     * @param array $options
     */
    public static function setDefaultRedisOptions(array $options)
    {
        self::$defaultRedisOptions = $options;
    }

    /**
     * @return CollectorRegistry
     */
    public static function getDefault()
    {
        if (!self::$defaultRegistry) {
            return self::$defaultRegistry = new static(new Redis(self::$defaultRedisOptions));
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
        $this->gauges[self::metricIdentifier($namespace, $name, $labels)] = new Gauge(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->gauges[self::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     */
    public function getGauge($namespace, $name, $labels = array())
    {
        return $this->gauges[self::metricIdentifier($namespace, $name, $labels)];
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
        return $this->counters[self::metricIdentifier($namespace, $name, $labels)];
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
        $this->counters[self::metricIdentifier($namespace, $name, $labels)] = new Counter(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->counters[self::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @param array $buckets e.g. [100, 200, 300]
     * @return Histogram
     */
    public function registerHistogram($namespace, $name, $help, $labels = array(), $buckets = array())
    {
        $this->histograms[self::metricIdentifier($namespace, $name, $labels)] = new Histogram(
            $this->storageAdapter,
            $namespace,
            $name,
            $help,
            $labels,
            $buckets
        );
        return $this->histograms[self::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Histogram
     */
    public function getHistogram($namespace, $name, $labels = array())
    {
        return $this->histograms[self::metricIdentifier($namespace, $name, $labels)];
    }

    private static function metricIdentifier($namespace, $name, $labels)
    {
        return $namespace . $name . implode('_', $labels);
    }
}
