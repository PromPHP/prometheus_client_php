<?php

namespace Prometheus;


class RedisAdapter
{
    const PROMETHEUS_GAUGES = 'PROMETHEUS_GAUGES_';
    const PROMETHEUS_GAUGE_KEYS = 'PROMETHEUS_GAUGE_KEYS';
    const PROMETHEUS_COUNTERS = 'PROMETHEUS_COUNTERS_';
    const PROMETHEUS_COUNTER_KEYS = 'PROMETHEUS_COUNTER_KEYS';

    private $hostname;
    private $redis;

    public function __construct($hostname)
    {
        $this->hostname = $hostname;
        $this->redis = new \Redis();
    }

    public function storeGauge($gauge)
    {
        $this->openConnection();
        $key = sha1($gauge['name'] . '_' . serialize($gauge['labels']));
        $this->redis->sAdd(self::PROMETHEUS_GAUGE_KEYS, $key);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'value', $gauge['value']);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'labels', serialize($gauge['labels']));
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'name', $gauge['name']);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'help', $gauge['help']);
        $this->redis->hSet(self::PROMETHEUS_GAUGES . $key, 'type', $gauge['type']);
    }

    public function fetchGauges()
    {
        $this->openConnection();
        $keys = $this->redis->sMembers(self::PROMETHEUS_GAUGE_KEYS);
        $gauges = array();
        foreach ($keys as $key) {
            $sample = array();
            $sample['value'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $key, 'value');
            $sample['labels'] = unserialize($this->redis->hGet(self::PROMETHEUS_GAUGES . $key, 'labels'));
            $sample['name'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $key, 'name');
            $sample['help'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $key, 'help');
            $sample['type'] = $this->redis->hGet(self::PROMETHEUS_GAUGES . $key, 'type');
            $gauges[] = $sample;
        }
        return $gauges;
    }

    public function deleteMetrics()
    {
        $this->openConnection();

        $keys = $this->redis->sMembers(self::PROMETHEUS_GAUGE_KEYS);
        foreach($keys as $key) {
            $this->redis->delete(self::PROMETHEUS_GAUGES . $key);
        }
        $this->redis->del(self::PROMETHEUS_GAUGE_KEYS);

        $keys = $this->redis->sMembers(self::PROMETHEUS_COUNTER_KEYS);
        foreach($keys as $key) {
            $this->redis->delete(self::PROMETHEUS_COUNTERS . $key);
        }
        $this->redis->del(self::PROMETHEUS_COUNTER_KEYS);
    }

    private function openConnection()
    {
        $this->redis->connect($this->hostname);
    }

    public function storeCounter($counter)
    {
        $this->openConnection();
        $key = sha1($counter['name'] . '_' . serialize($counter['labels']));
        $this->redis->sAdd(self::PROMETHEUS_COUNTER_KEYS, $key);
        $this->redis->hIncrBy(self::PROMETHEUS_COUNTERS . $key, 'value', $counter['value']);
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'labels', serialize($counter['labels']));
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'name', $counter['name']);
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'help', $counter['help']);
        $this->redis->hSet(self::PROMETHEUS_COUNTERS . $key, 'type', $counter['type']);
    }

    public function fetchCounters()
    {
        $this->openConnection();
        $keys = $this->redis->sMembers(self::PROMETHEUS_COUNTER_KEYS);
        $gauges = array();
        foreach ($keys as $key) {
            $sample = array();
            $sample['value'] = $this->redis->hGet(self::PROMETHEUS_COUNTERS . $key, 'value');
            $sample['labels'] = unserialize($this->redis->hGet(self::PROMETHEUS_COUNTERS . $key, 'labels'));
            $sample['name'] = $this->redis->hGet(self::PROMETHEUS_COUNTERS . $key, 'name');
            $sample['help'] = $this->redis->hGet(self::PROMETHEUS_COUNTERS . $key, 'help');
            $sample['type'] = $this->redis->hGet(self::PROMETHEUS_COUNTERS . $key, 'type');
            $gauges[] = $sample;
        }
        return $gauges;
    }
}
