<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

use InvalidArgumentException;
use Predis\Client;
use Prometheus\Exception\StorageException;

class Predis implements RedisClient
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param  mixed[]  $parameters
     * @param  mixed[]  $options
     * @throws StorageException
     */
    public static function create(array $parameters, array $options): self
    {
        try {
            return new self(new Client($parameters, $options));
        } catch (InvalidArgumentException $e) {
            throw new StorageException('Invalid Redis client configuration: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function fromExistingConnection(Client $client): self
    {
        return new self($client);
    }

    public function getPrefix(): ?string
    {
        $value = $this->client->getOptions()->prefix;

        return $value instanceof \Predis\Command\Processor\KeyPrefixProcessor
            ? $value->getPrefix()
            : null;
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
        $this->client->setnx($key, $value);
    }

    public function sMembers(string $key): array
    {
        return $this->client->smembers($key);
    }

    public function hGetAll(string $key): array|false
    {
        return $this->client->hgetall($key);
    }

    public function keys(string $pattern): array
    {
        return $this->client->keys($pattern);
    }

    public function get(string $key): string|false
    {
        return $this->client->get($key) ?? false;
    }

    public function del(array|string $key, string ...$other_keys): void
    {
        $this->client->del($key, ...$other_keys);
    }

    /**
     * @throws StorageException
     */
    public function ensureOpenConnection(): void
    {
        if (!$this->client->isConnected()) {
            try {
                $this->client->connect();
            } catch (\Predis\Connection\ConnectionException $e) {
                throw new StorageException('Cannot establish Redis Connection:' . $e->getMessage(), 0, $e);
            }
        }
    }
}
