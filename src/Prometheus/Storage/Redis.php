<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\Exception\StorageException;
use Prometheus\Storage\RedisClients\PHPRedis;

class Redis extends AbstractRedis
{
    /**
     * @var mixed[]
     */
    private static $defaultOptions = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_timeout' => '10',
        'persistent_connections' => false,
        'password' => null,
        'user' => null,
    ];

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * Redis constructor.
     *
     * @param  mixed[]  $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$defaultOptions, $options);
        $this->redis = PHPRedis::create($this->options);
    }

    /**
     * @throws StorageException
     */
    public static function fromExistingConnection(\Redis $redis): self
    {
        if ($redis->isConnected() === false) {
            throw new StorageException('Connection to Redis server not established');
        }

        $self = new self;
        $self->redis = new PHPRedis($redis, self::$defaultOptions);

        return $self;
    }

    /**
     * @param  mixed[]  $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }
}
