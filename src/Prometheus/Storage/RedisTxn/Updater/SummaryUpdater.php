<?php

namespace Prometheus\Storage\RedisTxn\Updater;

use Prometheus\Storage\RedisTxn\Metric\MetadataBuilder;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

class SummaryUpdater implements UpdaterInterface
{
    /**
     * @var string
     */
    const SCRIPT = <<<LUA
-- Parse script input
local summaryRegistryKey = KEYS[1]
local metaHashKey = KEYS[2]
local summaryKey = KEYS[3]
local summaryMetadata = ARGV[1]
local summaryValue = ARGV[2]
local currentTime = ARGV[3]
local ttlFieldValue = ARGV[4]

-- NOTE: We must construct this key on the Redis server to account for cases where 
-- a global key prefix has been configured on a Redis client. If we construct this
-- key in the application, we will inadvertently omit any configured global key prefix.
local ttlFieldName = summaryKey .. ":ttl"

-- Persist the observed metric value
redis.call('sadd', summaryRegistryKey, summaryKey)
redis.call('zadd', summaryKey, currentTime, summaryValue)
redis.call('hset', metaHashKey, summaryKey, summaryMetadata)
redis.call('hset', metaHashKey, ttlFieldName, ttlFieldValue)
LUA;

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

    public function getHelper(): RedisScriptHelper
    {
        $this->helper = $this->helper ?? new RedisScriptHelper();
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
    public function getRedisScript(array $data): RedisScript
    {
        // Prepare metadata
        $metadata = MetadataBuilder::fromArray($data)->build();

        // Create Redis keys
        $registryKey = $this->getHelper()->getRegistryKey($metadata->getType());
        $metadataKey = $this->getHelper()->getMetadataKey($metadata->getType());
        $metricKey = $this->getHelper()->getMetricKey($metadata);

        // Get summary sample
        //
        // NOTE: When we persist a summary metric sample into Redis, we write it into a Redis sorted set.
        // We append the current time in microseconds as a suffix on the observed value to make each observed value
        // durable and unique in the sorted set in accordance with best-practice guidelines described in the article,
        // "Redis Best Practices: Sorted Set Time Series" [1].
        //
        // See MetricBuilder::processSamples() for the complementary part of this operation.
        //
        // [1] https://redis.com/redis-best-practices/time-series/sorted-set-time-series/
        $value = implode(':', [$data['value'], microtime(true)]);

        // Prepare script input
        $currentTime = time();
        $ttl = $metadata->getMaxAgeSeconds();
        $scriptArgs = [
            $registryKey,
            $metadataKey,
            $metricKey,
            $metadata->toJson(),
            $value,
            $currentTime,
            $ttl,
        ];

        // Return script
        return RedisScript::newBuilder()
            ->withScript(self::SCRIPT)
            ->withArgs($scriptArgs)
            ->withNumKeys(3)
            ->build();
    }

    /**
     * @inheritDoc
     */
    public function update(array $data)
    {
        return $this->getRedisScript($data)->eval($this->redis);
    }
}
