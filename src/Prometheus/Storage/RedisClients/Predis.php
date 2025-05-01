<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

use Predis\Client;

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

    public function getOption(int $option): mixed
    {
        if (! isset(self::OPTIONS_MAP[$option])) {
            return null;
        }

        $mappedOption = self::OPTIONS_MAP[$option];

        return $this->options[$mappedOption] ?? null;
    }

    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        $this->client->eval($script, $num_keys, ...$args);
    }

    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $result = $this->client->set($key, $value, ...$this->flattenFlags($options));

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
        $this->client->setnx($key, $value) === 1;
    }

    public function hSetNx(string $key, string $field, mixed $value): bool
    {
        return $this->hSetNx($key, $field, $value);
    }

    public function sMembers(string $key): array
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

    public function del(array|string $key, string ...$other_keys): void
    {
        $this->client->del($key, ...$other_keys);
    }

    public function ensureOpenConnection(): void
    {
        if ($this->client === null) {
            $this->client = new Client($this->parameters, $this->options);
        }
    }
}
