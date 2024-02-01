<?php

namespace Prometheus\Storage\RedisTxn\Collecter;

use Prometheus\Gauge;
use Prometheus\Storage\RedisTxn\Metric\MetadataBuilder;
use Prometheus\Storage\RedisTxn\Metric\Metric;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;

class GaugeCollecter extends AbstractCollecter
{
    const SCRIPT = <<<LUA
-- Parse script input
local registryKey = KEYS[1]
local metadataKey = KEYS[2]

-- Process each registered metric
local result = {}
local metricKeys = redis.call('smembers', registryKey)
for i, metricKey in ipairs(metricKeys) do
    local doesExist = redis.call('exists', metricKey)
    if doesExist then
        -- Get metric metadata
        local metadata = redis.call('hget', metadataKey, metricKey)
        
        -- Get metric sample
        local sample = redis.call('get', metricKey)
        
        -- Add the processed metric to the set of results
        result[metricKey] = {}
        result[metricKey]["metadata"] = metadata
        result[metricKey]["samples"] = sample
    else
        -- Remove metadata for expired key
        redis.call('srem', registryKey, metricKey)
        redis.call('hdel', metadataKey, metricKey)
    end 
end

-- Return the set of collected metrics
return cjson.encode(result)
LUA;

    /**
     * @inheritDoc
     */
    public function getRedisScript(): RedisScript
    {
        // Create Redis script args
        $numKeys = 2;
        $registryKey = $this->getHelper()->getRegistryKey(Gauge::TYPE);
        $metadataKey = $this->getHelper()->getMetadataKey(Gauge::TYPE);
        $scriptArgs = [
            $registryKey,
            $metadataKey,
        ];

        // Create Redis script
        return RedisScript::newBuilder()
            ->withScript(self::SCRIPT)
            ->withArgs($scriptArgs)
            ->withNumKeys($numKeys)
            ->build();
    }

    /**
     * @inheritDoc
     */
    public function getMetrics(): array
    {
        // Retrieve metrics from Redis
        $results = $this->getRedisScript()->eval($this->getRedis());

        // Collate metrics by metric name
        $phpMetrics = [];
        $redisMetrics = json_decode($results, true);
        foreach ($redisMetrics as $redisMetric) {
            // Get metadata
            $phpMetadata = json_decode($redisMetric['metadata'], true);
            $metadata = MetadataBuilder::fromArray($phpMetadata)->build();

            // Create or update metric
            $metricName = $metadata->getName();
            $builder = $phpMetrics[$metricName] ?? Metric::newScalarMetricBuilder()->withMetadata($metadata);
            $builder->withSample($redisMetric['samples'], $metadata->getLabelValues());
            $phpMetrics[$metricName] = $builder;
        }

        // Build metrics
        $metrics = [];
        foreach ($phpMetrics as $_ => $metric) {
            $metrics[] = $metric->build();
        }
        return $metrics;
    }
}
