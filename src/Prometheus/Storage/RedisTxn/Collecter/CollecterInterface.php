<?php

namespace Prometheus\Storage\RedisTxn\Collecter;

use Prometheus\Storage\RedisTxn\Metric\Metric;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

interface CollecterInterface
{
    /**
     * @return RedisScriptHelper
     */
    function getHelper(): RedisScriptHelper;

    /**
     * @return Redis
     */
    function getRedis(): Redis;

    /**
     * @return RedisScript
     */
    function getRedisScript(): RedisScript;

    /**
     * @return Metric[]
     */
    function getMetrics(): array;

    /**
     * @return array
     */
    function getMetricFamilySamples(): array;
}