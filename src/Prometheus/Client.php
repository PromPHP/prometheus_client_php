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
                $this->storeSample($sample, $redis);
            }
        };
    }

    public function toText()
    {
        $redis = new \Redis();
        $redis->connect('192.168.59.100');
        $samples = $this->fetchSamples($redis);
        $result = '';
        $metrics = array();
        foreach ($samples as $sample) {
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

    private function storeSample(array $sample, \Redis $redis)
    {
        $sampleKey = sha1($sample['name'] . '_' . serialize($sample['labels']));
        $redis->sAdd(self::PROMETHEUS_SAMPLE_KEYS, $sampleKey);
        $redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'value', $sample['value']);
        $redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'labels', serialize($sample['labels']));
        $redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'name', $sample['name']);
        $redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'help', $sample['help']);
    }

    private function fetchSamples(\Redis $redis)
    {
        $sampleKeys = $redis->sMembers(self::PROMETHEUS_SAMPLE_KEYS);
        $samples = array();
        foreach ($sampleKeys as $sampleKey) {
            $sample = array();
            $sample['value'] = $redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'value');
            $sample['labels'] = unserialize($redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'labels'));
            $sample['name'] = $redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'name');
            $sample['help'] = $redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'help');
            $samples[] = $sample;
        }
        return $samples;
    }
}
