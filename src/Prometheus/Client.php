<?php


namespace Prometheus;


class Client
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_METRICS = 'PROMETHEUS_METRICS';
    /**
     * @var Gauge[]
     */
    private $metrics;

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
        $redis = new \Redis();
        $redis->connect('192.168.59.100');
        foreach ($this->metrics as $m) {
            foreach ($m->getSamples() as $sample) {
                $redis->sAdd(self::PROMETHEUS_METRICS, $sample['name'] . '_' . serialize($sample['labels']));
                $redis->hSet(self::PROMETHEUS_GAUGES . $sample['name'] . '_' . serialize($sample['labels']), 'value', $sample['value']);
                $redis->hSet(self::PROMETHEUS_GAUGES . $sample['name'] . '_' . serialize($sample['labels']), 'labels', serialize($sample['labels']));
                $redis->hSet(self::PROMETHEUS_GAUGES . $sample['name'] . '_' . serialize($sample['labels']), 'name', $sample['name']);
                $redis->hSet(self::PROMETHEUS_GAUGES . $sample['name'] . '_' . serialize($sample['labels']), 'help', $sample['help']);
            }
        };
    }

    public function toText()
    {
        $redis = new \Redis();
        $redis->connect('192.168.59.100');
        $metrics = $redis->sMembers(self::PROMETHEUS_METRICS);
        $result = '';
        foreach ($metrics as $m) {
            $value = $redis->hGet(self::PROMETHEUS_GAUGES . $m, 'value');
            $labels = unserialize($redis->hGet(self::PROMETHEUS_GAUGES . $m, 'labels'));
            $name = $redis->hGet(self::PROMETHEUS_GAUGES . $m, 'name');
            $help = $redis->hGet(self::PROMETHEUS_GAUGES . $m, 'help');
            $result .= "# HELP " . $name . " {$help}\n";
            $result .= "# TYPE " . $name . " gauge\n";
            $metrics = array();
            $escapedLabels = array();
            foreach ($labels as $label) {
                $escapedLabels[] = $label['name'] . '="' . $this->escapeLabelValue($label['value']) . '"';
            }
            if (!empty($escapedLabels)) {
                $metrics[] = $name . '{' . implode(',', $escapedLabels) . '} ' . $value;
            } else {
                $metrics[] = $name . ' ' . $value;
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
