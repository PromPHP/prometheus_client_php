<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

use Predis\Client;
use Predis\Configuration\Option\Prefix;

class Predis implements RedisClient
{
    private const OPTIONS_MAP = [
        RedisClient::OPT_PREFIX => Prefix::class,
    ];

    private $client;

    private $prefix = '';

    public function __construct(Client $redis)
    {
        $this->client = $redis;
    }

    public static function create(array $options): self
    {
        $this->prefix = $options['prefix'] ?? '';
        $redisClient = new Client($options, ['prefix' => $options['prefix'] ?? '']);

        return new self($redisClient);
    }

    public function getOption(int $option): mixed
    {
        if (! isset(self::OPTIONS_MAP[$option])) {
            return null;
        }

        $mappedOption = self::OPTIONS_MAP[$option];

        return $this->client->getOptions()->$mappedOption;
    }

    public function eval(string $script, array $args = [], int $num_keys = 0): mixed
    {
        return $this->client->eval($script, $num_keys, ...$args);
    }

    public function set(string $key, mixed $value, mixed $options = null): string|bool
    {
        $result = $this->client->set($key, $value, ...$this->flattenFlags($options));

        return (string) $result;
    }

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

    public function setNx(string $key, mixed $value): bool
    {
        return $this->client->setnx($key, $value) === 1;
    }

    public function hSetNx(string $key, string $field, mixed $value): bool
    {
        return $this->hsetnx($key, $field, $value);
    }

    public function sMembers(string $key): array|false
    {
        return $this->client->smembers($key);
    }

    public function hGetAll(string $key): array|false
    {
        return $this->client->hgetall($key);
    }

    public function keys(string $pattern)
    {
        return $this->client->keys($pattern);
    }

    public function get(string $key): mixed
    {
        return $this->client->get($key);
    }

    public function del(array|string $key, string ...$other_keys): int|false
    {
        return $this->client->del($key, ...$other_keys);
    }

    public function getPrefix(): string
    {
        $key = RedisClient::OPT_PREFIX;

        return $this->prefix;
    }

    public function ensureOpenConnection(): void
    {
        // Predis doesn't require to trigger connection
    }
}
