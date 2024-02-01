<?php

namespace Prometheus\Storage\RedisTxn\Updater;

use Prometheus\Storage\RedisTxn\RedisScript\RedisScript;
use Prometheus\Storage\RedisTxn\RedisScript\RedisScriptHelper;
use Redis;

interface UpdaterInterface
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
     * @param array $data
     * @return RedisScript
     */
    function getRedisScript(array $data): RedisScript;

    /**
     * @param array $data
     * @return mixed
     */
    function update(array $data);
}
