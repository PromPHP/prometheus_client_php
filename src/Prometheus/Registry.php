<?php


namespace Prometheus;


class Registry
{
    private $redisAdapter;
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

    public function __construct(RedisAdapter $redisAdapter)
    {
        $this->redisAdapter = $redisAdapter;
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. The duration something took in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     */
    public function registerGauge($namespace, $name, $help, $labels)
    {
        $this->gauges[Metric::metricIdentifier($namespace, $name, $labels)] = new Gauge(
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->gauges[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Gauge
     */
    public function getGauge($namespace, $name, $labels)
    {
        return $this->gauges[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    public function flush()
    {
        foreach ($this->gauges as $g) {
            $this->redisAdapter->storeGauge($g);
        };
        foreach ($this->counters as $c) {
            $this->redisAdapter->storeCounter($c);
        };
        foreach ($this->histograms as $h) {
            $this->redisAdapter->storeHistogram($h);
        };
    }

    public function toText()
    {
        $lines = array();
        foreach ($this->redisAdapter->fetchGauges() as $gauge) {
            $lines[] = "# HELP " . $gauge['name'] . " {$gauge['help']}";
            $lines[] = "# TYPE " . $gauge['name'] . " {$gauge['type']}";
            foreach ($gauge['samples'] as $sample) {
                $lines[] = $this->renderSample($sample);
            }
        }
        foreach ($this->redisAdapter->fetchCounters() as $counter) {
            $lines[] = "# HELP " . $counter['name'] . " {$counter['help']}";
            $lines[] = "# TYPE " . $counter['name'] . " {$counter['type']}";
            foreach ($counter['samples'] as $sample) {
                $lines[] = $this->renderSample($sample);
            }
        }
        foreach ($this->redisAdapter->fetchHistograms() as $histogram) {
            $lines[] = "# HELP " . $histogram['name'] . " {$histogram['help']}";
            $lines[] = "# TYPE " . $histogram['name'] . " {$histogram['type']}";
            foreach ($histogram['samples'] as $sample) {
                $lines[] = $this->renderSample($sample);
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private function escapeLabelValue($v)
    {
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace("\"", "\\\"", $v);
        return $v;
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     */
    public function getCounter($namespace, $name, $labels)
    {
        return $this->counters[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. requests
     * @param string $help e.g. The number of requests made.
     * @param array $labels e.g. ['controller', 'action']
     * @return Counter
     */
    public function registerCounter($namespace, $name, $help, $labels)
    {
        $this->counters[Metric::metricIdentifier($namespace, $name, $labels)] = new Counter(
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->counters[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace e.g. cms
     * @param string $name e.g. duration_seconds
     * @param string $help e.g. A histogram of the duration in seconds.
     * @param array $labels e.g. ['controller', 'action']
     * @param array $buckets e.g. [100, 200, 300]
     * @return Histogram
     */
    public function registerHistogram($namespace, $name, $help, $labels, $buckets)
    {
        $this->histograms[Metric::metricIdentifier($namespace, $name, $labels)] = new Histogram(
            $namespace,
            $name,
            $help,
            $labels,
            $buckets
        );
        return $this->histograms[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param array $labels e.g. ['controller', 'action']
     * @return Histogram
     */
    public function getHistogram($namespace, $name, $labels)
    {
        return $this->histograms[Metric::metricIdentifier($namespace, $name, $labels)];
    }

    /**
     * @param array $sample e.g. ['labels' => ['foo' => 'bar'], 'name' => 'some_metric', 'value' => 30]
     * @return string
     */
    private function renderSample(array $sample)
    {
        $escapedLabels = array();
        if (!empty($sample['labels'])) {
            foreach ($sample['labels'] as $labelName => $labelValue) {
                $escapedLabels[] = $labelName . '="' . $this->escapeLabelValue($labelValue) . '"';
            }
            return $sample['name'] . '{' . implode(',', $escapedLabels) . '} ' . $sample['value'];
        }
        return $sample['name'] . ' ' . $sample['value'];
    }
}
