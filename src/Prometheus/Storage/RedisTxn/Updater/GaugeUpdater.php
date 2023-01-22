<?php

namespace Prometheus\Storage\RedisTxn\Updater;

use Prometheus\Storage\RedisTxn\Metric\MetadataBuilder;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

class GaugeUpdater implements UpdaterInterface
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
local command = ARGV[2]
local value = ARGV[3]
local ttl = tonumber(ARGV[4])

-- Update metric value
local didUpdate = false
if command == "set" then
    local result = redis.call(command, metricKey, value) 
    didUpdate = result["ok"] == "OK"
else -- {incrby, incrbyfloat}
    local result = redis.call(command, metricKey, value) 
    didUpdate = tostring(result) == value
end

-- Update metric metadata
if didUpdate == true then
    -- Set metric TTL
    if ttl > 0 then
        redis.call('expire', metricKey, ttl)
    else
       redis.call('persist', metricKey)
    end
    
    -- Register metric value
    redis.call('sadd', registryKey, metricKey)

    -- Register metric metadata
    redis.call('hset', metadataKey, metricKey, metadata)
end

-- Report script result
return didUpdate
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

    /**
     * @inheritDoc
     */
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

        // Prepare script input
        $command = $this->getHelper()->getRedisCommand($metadata->getCommand());
        $value = $data['value'];
        $ttl = $metadata->getMaxAgeSeconds() ?? $this->getHelper()->getDefautlTtl();
        $scriptArgs = [
            $registryKey,
            $metadataKey,
            $metricKey,
            $metadata->toJson(),
            $command,
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

    /**
     * @inheritDoc
     */
    public function update(array $data)
    {
        return $this->getRedisScript($data)->eval($this->redis);
    }
}
