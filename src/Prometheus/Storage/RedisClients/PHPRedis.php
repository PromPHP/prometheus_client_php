<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

use Prometheus\Exception\StorageException;

class PHPRedis implements RedisClient
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var bool
     */
    private $connectionInitialized = false;

    /**
     * @param  mixed[]  $options
     */
    public function __construct(\Redis $redis, array $options)
    {
        $this->redis = $redis;
        $this->options = $options;
    }

    /**
     * @param  mixed[]  $options
     */
    public static function create(array $options): self
    {
        $redis = new \Redis;

        return new self($redis, $options);
    }

    public function getOption(int $option): mixed
    {
        return $this->redis->getOption($option);
    }

    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        $this->redis->eval($script, $args, $num_keys);
    }

    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        return $this->redis->set($key, $value, $options);
    }

    public function setNx(string $key, mixed $value): void
    {
        $this->redis->setNx($key, $value); /** @phpstan-ignore-line */
    }

    public function hSetNx(string $key, string $field, mixed $value): bool
    {
        return $this->redis->hSetNx($key, $field, $value);
    }

    public function sMembers(string $key): array
    {
        return $this->redis->sMembers($key);
    }

    public function hGetAll(string $key): array|false
    {
        return $this->redis->hGetAll($key);
    }

    public function keys(string $pattern)
    {
        return $this->redis->keys($pattern);
    }

    public function get(string $key): mixed
    {
        return $this->redis->get($key);
    }

    public function del(array|string $key, string ...$other_keys): void
    {
        try {
            $this->redis->del($key, ...$other_keys);
        } catch (\RedisException $e) {
            throw new RedisClientException($e->getMessage());
        }
    }

    /**
     * @throws StorageException
     */
    public function ensureOpenConnection(): void
    {
        if ($this->connectionInitialized === true) {
            return;
        }

        $this->connectToServer();
        $authParams = [];

        if (isset($this->options['user']) && $this->options['user'] !== '') {
            $authParams[] = $this->options['user'];
        }

        if (isset($this->options['password'])) {
            $authParams[] = $this->options['password'];
        }

        if ($authParams !== []) {
            $this->redis->auth($authParams);
        }

        if (isset($this->options['database'])) {
            $this->redis->select($this->options['database']);
        }

        $this->redis->setOption(RedisClient::OPT_READ_TIMEOUT, $this->options['read_timeout']);

        $this->connectionInitialized = true;
    }

    /**
     * @throws StorageException
     */
    private function connectToServer(): void
    {
        try {
            $connection_successful = false;
            if ($this->options['persistent_connections'] !== false) {
                $connection_successful = $this->redis->pconnect(
                    $this->options['host'],
                    (int) $this->options['port'],
                    (float) $this->options['timeout']
                );
            } else {
                $connection_successful = $this->redis->connect($this->options['host'], (int) $this->options['port'], (float) $this->options['timeout']);
            }
            if (! $connection_successful) {
                throw new StorageException(
                    sprintf("Can't connect to Redis server. %s", $this->redis->getLastError()),
                    0
                );
            }
        } catch (\RedisException $e) {
            throw new StorageException(
                sprintf("Can't connect to Redis server. %s", $e->getMessage()),
                $e->getCode(),
            );
        }
    }
}
