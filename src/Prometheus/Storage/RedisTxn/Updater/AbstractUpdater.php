<?php

namespace Prometheus\Storage\RedisTxn\Updater;

use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

abstract class AbstractUpdater implements UpdaterInterface
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
    public function update(array $data)
    {
        return $this->getRedisScript($data)->eval($this->getRedis());
    }
}