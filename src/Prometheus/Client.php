<?php


namespace Prometheus;


class Client
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_SAMPLE_KEYS = 'PROMETHEUS_METRICS';
    /**
     * @var Gauge[]
     */
    private $metrics;
    private $redisAdapter;

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
        $this->metrics[Gauge::metricName($namespace, $name)] = new Gauge(
            $namespace,
            $name,
            $help,
            $labels
        );
        return $this->metrics[Gauge::metricName($namespace, $name)];
    }

    /**
     * @param string $namespace
     * @param string $name
     * @return Gauge
     */
    public function getGauge($namespace, $name)
    {
        return $this->metrics[Gauge::metricName($namespace, $name)];
    }

    public function flush()
    {
        foreach ($this->metrics as $m) {
            foreach ($m->getSamples() as $sample) {
                $this->redisAdapter->storeSample($sample);
            }
        };
    }

    public function toText()
    {
        $result = '';
        $metrics = array();
        foreach ($this->redisAdapter->fetchSamples() as $sample) {
            $result .= "# HELP " . $sample['name'] . " {$sample['help']}\n";
            $result .= "# TYPE " . $sample['name'] . " gauge\n";
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
}
