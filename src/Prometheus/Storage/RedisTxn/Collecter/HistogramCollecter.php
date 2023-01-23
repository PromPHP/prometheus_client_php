<?php

namespace Prometheus\Storage\RedisTxn\Collecter;

use Prometheus\Storage\RedisTxn\Metric\MetadataBuilder;
use Prometheus\Storage\RedisTxn\Metric\Metric;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Histogram;

class HistogramCollecter extends AbstractCollecter
{
    const SCRIPT = <<<LUA
local function hgetall(hash_key)
    local flat_map = redis.call('HGETALL', hash_key)
    local result = {}
    for i = 1, #flat_map, 2 do
        result[flat_map[i]] = flat_map[i + 1]
    end
    return result
end

-- Parse script input
local summaryRegistryKey = KEYS[1]
local metadataKey = KEYS[2]

-- Process each registered metric
local result = {}
local metricKeys = redis.call('smembers', summaryRegistryKey)
for i, metricKey in ipairs(metricKeys) do
    -- Determine if the registered metric key still exists
    local doesExist = redis.call('exists', metricKey)
    if doesExist then
        local metadata = redis.call('hget', metadataKey, metricKey)
        local samples = hgetall(metricKey)    
        
        -- Add the processed metric to the set of results
        result[metricKey] = {}
        result[metricKey]["metadata"] = metadata
        result[metricKey]["samples"] = samples
    else
        -- Remove metadata for expired key
        redis.call('srem', registryKey, metricKey)
        redis.call('hdel', metadataKey, metricKey)
    end
end

-- Return the set of summary metrics
return cjson.encode(result)
LUA;

    /**
     * @inheritDoc
     */
    public function getRedisScript(): RedisScript
    {
        // Create Redis script args
        $numKeys = 2;
        $registryKey = $this->getHelper()->getRegistryKey(Histogram::TYPE);
        $metadataKey = $this->getHelper()->getMetadataKey(Histogram::TYPE);
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

        // Collate histogram observations by metric name
        $builders = [];
        $redisMetrics = json_decode($results, true);
        foreach ($redisMetrics as $redisMetric) {
            $phpMetadata = json_decode($redisMetric['metadata'], true);
            $metadata = MetadataBuilder::fromArray($phpMetadata)->build();
            $builder = $builders[$metadata->getName()] ?? Metric::newHistogramMetricBuilder()->withMetadata($metadata);
            $builder->withSamples($redisMetric['samples'], $metadata->getLabelValues());
            $builders[$metadata->getName()] = $builder;
        }

        // Build collated histograms into Metric structures
        $metrics = [];
        foreach ($builders as $builder) {
            $metrics[] = $builder->build();
        }
        return $metrics;
    }
}
