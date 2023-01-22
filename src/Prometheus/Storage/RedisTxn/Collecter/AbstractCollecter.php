<?php

namespace Prometheus\Storage\RedisTxn\Collecter;

use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

abstract class AbstractCollecter implements CollecterInterface
{
    /**
     * @var RedisScriptHelper
     */
    private $helper;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->helper = new RedisScriptHelper();
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function getHelper(): RedisScriptHelper
    {
        return $this->helper;
    }

    /**
     * @inheritDoc
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * @inheritDoc
     */
    public function getMetricFamilySamples(): array
    {
        $metricFamilySamples = [];
        $metrics = $this->getMetrics();
        foreach ($metrics as $metric) {
            $metricFamilySamples[] = $metric->toMetricFamilySamples();
        }
        return $metricFamilySamples;
    }
}