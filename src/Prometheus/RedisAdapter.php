<?php

namespace Prometheus;


class RedisAdapter
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_SAMPLE_KEYS = 'PROMETHEUS_METRICS';

    private $hostname;
    private $redis;

    public function __construct($hostname)
    {
        $this->hostname = $hostname;
        $this->redis = new \Redis();
    }

    public function storeSample($sample)
    {
        $this->openConnection();
        $sampleKey = sha1($sample['name'] . '_' . serialize($sample['labels']));
        $this->redis->sAdd(self::PROMETHEUS_SAMPLE_KEYS, $sampleKey);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'value', $sample['value']);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'labels', serialize($sample['labels']));
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'name', $sample['name']);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $sampleKey, 'help', $sample['help']);
    }

    public function fetchSamples()
    {
        $this->openConnection();
        $sampleKeys = $this->redis->sMembers(self::PROMETHEUS_SAMPLE_KEYS);
        $samples = array();
        foreach ($sampleKeys as $sampleKey) {
            $sample = array();
            $sample['value'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'value');
            $sample['labels'] = unserialize($this->redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'labels'));
            $sample['name'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'name');
            $sample['help'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $sampleKey, 'help');
            $samples[] = $sample;
        }
        return $samples;
    }
    
    public function deleteSampleKeys()
    {
        $this->openConnection();
        $this->redis->del(Client::PROMETHEUS_SAMPLE_KEYS);
    }

    private function openConnection()
    {
        $this->redis->connect('127.0.0.1');
    }
}
