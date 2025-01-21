<?php

namespace Prometheus\Storage\RedisTxn\RedisScript;

use Redis;

class RedisScript
{
    /**
     * @var string
     */
    private $script;

    /**
     * @var array
     */
    private $args;

    /**
     * @var int
     */
    private $numKeys;

    /**
     * @return RedisScriptBuilder
     */
    public static function newBuilder(): RedisScriptBuilder
    {
        return new RedisScriptBuilder();
    }

    /**
     * @param string $script
     * @param array $args
     * @param int $numKeys
     */
    public function __construct(
        string $script,
        array $args,
        int $numKeys
    )
    {
        $this->script = $script;
        $this->args = $args;
        $this->numKeys = $numKeys;
    }

    /**
     * @return string
     */
    public function getScript(): string
    {
        return $this->script;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return int
     */
    public function getNumKeys(): int
    {
        return $this->numKeys;
    }

    /**
     * @param Redis $redis
     * @return mixed
     */
    public function eval(Redis $redis)
    {
        return $redis->eval(
            $this->getScript(),
            $this->getArgs(),
            $this->getNumKeys()
        );
    }
}