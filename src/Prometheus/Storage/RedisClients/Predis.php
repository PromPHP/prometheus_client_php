<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

use InvalidArgumentException;
use Predis\Client;
use Prometheus\Exception\StorageException;

class Predis implements RedisClient
{
    private const OPTIONS_MAP = [
        RedisClient::OPT_PREFIX => 'prefix',
    ];

    /**
     * @var ?Client
     */
    private $client;

    /**
     * @var mixed[]
     */
    private $parameters = [];

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @param  mixed[]  $parameters
     * @param  mixed[]  $options
     */
    public function __construct(array $parameters, array $options, ?Client $redis = null)
    {
        $this->client = $redis;

        $this->parameters = $parameters;
        $this->options = $options;
    }

    /**
     * @param  mixed[]  $parameters
     * @param  mixed[]  $options
     */
    public static function create(array $parameters, array $options): self
    {
        return new self($parameters, $options);
    }

    public function getOption(string $option): mixed
    {
        if (! isset(self::OPTIONS_MAP[$option])) {
            return null;
        }

        $mappedOption = self::OPTIONS_MAP[$option];

        return $this->options[$mappedOption] ?? null;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            throw new StorageException('Redis connection not initialized. Call ensureOpenConnection() first.');
        }

        return $this->client;
    }

    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        $this->getClient()->eval($script, $num_keys, ...$args);
    }

    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $result = $this->getClient()->set($key, $value, ...$this->flattenFlags($options));

        return (string) $result === 'OK';
    }

    /**
     * @param  array<int|string, mixed>  $flags
     * @return mixed[]
     */
    private function flattenFlags(array $flags): array
    {
        $result = [];
        foreach ($flags as $key => $value) {
            if (is_int($key)) {
                $result[] = $value;
            } else {
                $result[] = $key;
                $result[] = $value;
            }
        }

        return $result;
    }

    public function setNx(string $key, mixed $value): void
    {
        $this->getClient()->setnx($key, $value);
    }

    public function sMembers(string $key): array
    {
        return $this->getClient()->smembers($key);
    }

    public function hGetAll(string $key): array|false
    {
        return $this->getClient()->hgetall($key);
    }

    public function keys(string $pattern): array
    {
        return $this->getClient()->keys($pattern);
    }

    public function get(string $key): string|false
    {
        return $this->getClient()->get($key) ?? false;
    }

    public function del(array|string $key, string ...$other_keys): void
    {
        $this->getClient()->del($key, ...$other_keys);
    }

    /**
     * @throws StorageException
     */
    public function ensureOpenConnection(): void
    {
        if ($this->client === null) {
            try {
                $this->client = new Client($this->parameters, $this->options);
            } catch (InvalidArgumentException $e) {
                throw new StorageException('Cannot establish Redis Connection:' . $e->getMessage());
            }
        }
    }
}
