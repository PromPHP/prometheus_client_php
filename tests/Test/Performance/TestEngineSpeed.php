<?php

declare(strict_types=1);

namespace Test\Performance;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/* No unit test methods exposed here. This class is a utility class instantiated by PerformanceTest */
class TestEngineSpeed
{

    /** @var \Prometheus\Storage\Adapter */
    private $driver;

    /** @var int */
    private $num_metrics;

   /**
    * @param string $driver    Storage driver name
    * @param int $num_metrics  Number of prometheus metrics to store (of each type)
    */
    public function __construct(string $driver, int $num_metrics)
    {
        $this->num_metrics = $num_metrics;
        $this->driver = new $driver();
    }

   /**
    * Create new (or increment existing) metrics
    */
    public function doCreates(): void
    {
        $registry = new CollectorRegistry($this->driver);
        for ($i = 0; $i < $this->num_metrics; $i++) {
            $registry->getOrRegisterCounter("namespace", "counter{$i}", "counter help")->inc();
            $registry->getOrRegisterGauge("namespace", "gauge{$i}", "gauge help")->inc();
            $registry->getOrRegisterHistogram("namespace", "histogram{$i}", "histogram help")->observe(1);
            $registry->getOrRegisterSummary("namespace", "summary{$i}", "summary help")->observe(1);
        }
    }

   /**
    * Remove all metrics
    */
    public function doWipeStorage(): void
    {
        $this->driver->wipeStorage();
    }

   /**
    * Collect a report of all recorded metrics
    */
    public function doCollect(): void
    {
        $registry = new CollectorRegistry($this->driver);
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
    }
}
