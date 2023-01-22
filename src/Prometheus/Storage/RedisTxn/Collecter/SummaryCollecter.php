<?php

namespace Prometheus\Storage\RedisTxn\Collecter;

use Prometheus\Storage\RedisTxn\Metric\Metric;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Summary;

class SummaryCollecter extends AbstractCollecter
{
    const SCRIPT = <<<LUA
-- Parse script input
local summaryRegistryKey = KEYS[1]
local metadataKey = KEYS[2]
local currentTime = tonumber(ARGV[1])

-- Process each registered summary metric
local result = {}
local summaryKeys = redis.call('smembers', summaryRegistryKey)
for i, summaryKey in ipairs(summaryKeys) do
    -- Get metric sample TTL
    local ttlFieldName = summaryKey .. ":ttl"
    redis.call('set', 'foo', ttlFieldName)
    local summaryTtl = redis.call("hget", metadataKey, ttlFieldName)
    if summaryTtl ~= nil then
        summaryTtl = tonumber(summaryTtl)
    end
    
    -- Remove TTL'd metric samples
    local startScore = "-inf"
    if summaryTtl ~= nil and currentTime ~= nil and summaryTtl > 0 and summaryTtl < currentTime then
        local startScore = currentTime - summaryTtl
	    redis.call("zremrangebyscore", summaryKey, "-inf", startScore)
    end
    
    -- Retrieve the set of remaining metric samples
    local numSamples = redis.call('zcard', summaryKey)
	local summaryMetadata = {}
	local summarySamples = {}
    if numSamples > 0 then
        -- Configure results
        summaryMetadata = redis.call("hget", metadataKey, summaryKey)
        summarySamples = redis.call("zrange", summaryKey, startScore, "+inf", "byscore")
    else            
        -- Remove the metric's associated metadata if there are no associated samples remaining
        redis.call('srem', summaryRegistryKey, summaryKey)    
        redis.call('hdel', metadataKey, summaryKey)
        redis.call('hdel', metadataKey, ttlFieldName)
    end 
	
    -- Add the processed metric to the set of results
	result[summaryKey] = {}
	result[summaryKey]["metadata"] = summaryMetadata
	result[summaryKey]["samples"] = summarySamples
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
        $registryKey = $this->getHelper()->getRegistryKey(Summary::TYPE);
        $metadataKey = $this->getHelper()->getMetadataKey(Summary::TYPE);
        $currentTime = time();
        $scriptArgs = [
            $registryKey,
            $metadataKey,
            $currentTime
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

        // Format metrics as MetricFamilySamples
        $metrics = [];
        $redisMetrics = json_decode($results, true);
        foreach ($redisMetrics as $redisMetric) {
            $metrics[] = Metric::newSummaryMetricBuilder()
                ->withMetadata($redisMetric['metadata'])
                ->withSamples($redisMetric['samples'])
                ->build();
        }
        return $metrics;
    }
}
