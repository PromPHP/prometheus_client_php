<?php


namespace Prometheus;


class Client
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
        $this->gauges[Metric::metricName($namespace, $name)] = new Gauge(
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->gauges[Metric::metricName($namespace, $name)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @return Gauge
     */
    public function getGauge($namespace, $name)
    {
        return $this->gauges[Metric::metricName($namespace, $name)];
    }

    public function flush()
    {
        foreach ($this->gauges as $m) {
            foreach ($m->getSamples() as $sample) {
                $this->redisAdapter->storeGauge($sample);
            }
        };
        foreach ($this->counters as $m) {
            foreach ($m->getSamples() as $sample) {
                $this->redisAdapter->storeCounter($sample);
            }
        };
    }

    public function toText()
    {
        $result = '';
        $metrics = array();
        foreach ($this->redisAdapter->fetchGauges() as $sample) {
            $result .= "# HELP " . $sample['name'] . " {$sample['help']}\n";
            $result .= "# TYPE " . $sample['name'] . " {$sample['type']}\n";
            $escapedLabels = array();
            if (!empty($sample['labels'])) {
                foreach ($sample['labels'] as $label) {
                    $escapedLabels[] = $label['name'] . '="' . $this->escapeLabelValue($label['value']) . '"';
                }
                $metrics[] = $sample['name'] . '{' . implode(',', $escapedLabels) . '} ' . $sample['value'];
            } else {
                $metrics[] = $sample['name'] . ' ' . $sample['value'];
            }
        }
        foreach ($this->redisAdapter->fetchCounters() as $sample) {
            $result .= "# HELP " . $sample['name'] . " {$sample['help']}\n";
            $result .= "# TYPE " . $sample['name'] . " {$sample['type']}\n";
            $escapedLabels = array();
            if (!empty($sample['labels'])) {
                foreach ($sample['labels'] as $label) {
                    $escapedLabels[] = $label['name'] . '="' . $this->escapeLabelValue($label['value']) . '"';
                }
                $metrics[] = $sample['name'] . '{' . implode(',', $escapedLabels) . '} ' . $sample['value'];
            } else {
                $metrics[] = $sample['name'] . ' ' . $sample['value'];
            }
        }
        foreach ($this->redisAdapter->fetchHistograms() as $sample) {
            $result .= "# HELP " . $sample['name'] . " {$sample['help']}\n";
            $result .= "# TYPE " . $sample['name'] . " {$sample['type']}\n";
            $escapedLabels = array();
            if (!empty($sample['labels'])) {
                foreach ($sample['labels'] as $label) {
                    $escapedLabels[] = $label['name'] . '="' . $this->escapeLabelValue($label['value']) . '"';
                }
                $metrics[] = $sample['name'] . '{' . implode(',', $escapedLabels) . '} ' . $sample['value'];
            } else {
                $metrics[] = $sample['name'] . ' ' . $sample['value'];
            }
        }
        return $result . implode("\n", $metrics) . "\n";
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
     * @return Counter
     */
    public function getCounter($namespace, $name)
    {
        return $this->counters[Metric::metricName($namespace, $name)];
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
        $this->counters[Metric::metricName($namespace, $name)] = new Counter(
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->counters[Metric::metricName($namespace, $name)];
    }

    public function registerHistogram($namespace, $name, $help, $labels, $buckets)
    {
        $this->histograms[Metric::metricName($namespace, $name)] = new Histogram(
            $namespace,
            $name,
            $help,
            $labels,
            $buckets
        );
        return $this->histograms[Metric::metricName($namespace, $name)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @return Counter
     */
    public function getHistogram($namespace, $name)
    {
        return $this->histograms[Metric::metricName($namespace, $name)];
    }


}
