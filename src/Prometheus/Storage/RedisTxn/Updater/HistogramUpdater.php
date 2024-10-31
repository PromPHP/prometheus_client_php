<?php

namespace Prometheus\Storage\RedisTxn\Updater;

use Prometheus\Storage\RedisTxn\Metric\MetadataBuilder;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;

class HistogramUpdater extends AbstractUpdater
{
    /**
     * @var string
     */
    const SCRIPT = <<<LUA
-- Parse script input
local registryKey = KEYS[1]
local metadataKey = KEYS[2]
local metricKey = KEYS[3]
local metadata = ARGV[1]
local bucket = ARGV[2]
local value = ARGV[3]
local ttl = tonumber(ARGV[4])

-- Update metric sum
local result = redis.call("hincrbyfloat", metricKey, 'sum', value) 
local didUpdate = result > value
-- if didUpdate ~= true then
--     return false
-- end

-- Update metric count
local result = redis.call("hincrby", metricKey, 'count', 1) 

-- Update bucket count
result = redis.call("hincrby", metricKey, bucket, 1)
didUpdate = result >= 1
-- if didUpdate ~= true then
--     return false
-- end

-- Set metric TTL
-- if ttl > 0 then
--     redis.call('expire', metricKey, ttl)
-- else
--    redis.call('persist', metricKey)
-- end

-- Register metric key
redis.call('sadd', registryKey, metricKey)

-- Register metric metadata
redis.call('hset', metadataKey, metricKey, metadata)

-- Report script result
return true
LUA;

    /**
     * @inheritDoc
     */
    public function getRedisScript(array $data): RedisScript
    {
        // Prepare metadata
        $metadata = MetadataBuilder::fromArray($data)->build();

        // Create Redis keys
        $metricKey = $this->getHelper()->getMetricKey($metadata);
        $registryKey = $this->getHelper()->getRegistryKey($metadata->getType());
        $metadataKey = $this->getHelper()->getMetadataKey($metadata->getType());

        // Determine minimum eligible bucket
        $value = floatval($data['value']);
        $targetBucket = '+Inf';
        foreach ($metadata->getBuckets() as $bucket) {
            if ($value <= $bucket) {
                $targetBucket = $bucket;
                break;
            }
        }

        // Prepare script input
        $ttl = $metadata->getMaxAgeSeconds() ?? $this->getHelper()->getDefautlTtl();
        $scriptArgs = [
            $registryKey,
            $metadataKey,
            $metricKey,
            $metadata->toJson(),
            $targetBucket,
            $value,
            $ttl
        ];

        // Return script
        return RedisScript::newBuilder()
            ->withScript(self::SCRIPT)
            ->withArgs($scriptArgs)
            ->withNumKeys(3)
            ->build();
    }
}
