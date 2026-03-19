<?php

declare(strict_types=1);

namespace Prometheus\Storage\RedisClients;

interface RedisClient
{
    public const OPT_PREFIX = 'prefix';

    public function getOption(string $option): mixed;

    /**
     * @param  mixed[]  $args
     */
    public function eval(string $script, array $args = [], int $num_keys = 0): void;

    public function set(string $key, mixed $value, mixed $options = null): bool;

    public function setNx(string $key, mixed $value): void;

    /**
     * @return string[]
     */
    public function sMembers(string $key): array;

    /**
     * @return array<string, string>|false
     */
    public function hGetAll(string $key): array|false;

    /**
     * @return string[]
     */
    public function keys(string $pattern);

    public function get(string $key): mixed;

    /**
     * @param  string|string[]  $key
     */
    public function del(array|string $key, string ...$other_keys): void;

    public function ensureOpenConnection(): void;
}
