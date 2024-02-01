<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Predis\Configuration\Option\Prefix;
use Prometheus\Exception\StorageException;
use Predis\Client;

/**
 * @property Client $redis
 */
final class Predis extends Redis
{
    /**
     * @var mixed[]
     */
    private static array $defaultOptions = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'scheme' => 'tcp',
        'timeout' => 0.1,
        'read_timeout' => '10',
        'persistent' => 0,
        'password' => null,
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::$defaultOptions, $options);

        parent::__construct($options);

        $this->redis = new Client($this->options);
    }

    public static function fromClient(Client $redis): self
    {
        if ($redis->isConnected() === false) {
            throw new StorageException('Connection to Redis server not established');
        }

        $self = new self();
        $self->redis = $redis;

        return $self;
    }

    protected function ensureOpenConnection(): void
    {
        if ($this->redis->isConnected() === false) {
            $this->redis->connect();
        }
    }

    public static function fromExistingConnection(\Redis $redis): Redis
    {
        throw new \RuntimeException('This method is not supported by predis adapter');
    }

    protected function getGlobalPrefix(): ?string
    {
        if ($this->redis->getOptions()->prefix === null) {
            return null;
        }

        if ($this->redis->getOptions()->prefix instanceof Prefix) {
            return $this->redis->getOptions()->prefix->getPrefix();
        }

        return null;
    }

    /**
     * @param mixed[] $args
     * @param int $keysCount
     * @return mixed[]
     */
    protected function evalParams(array $args, int $keysCount): array
    {
        return  [$keysCount, ...$args];
    }


    protected function prefix(string $key): string
    {
        // the predis is doing key prefixing on its own
        return '';
    }

    protected function setParams(array $input): array
    {
        $values = array_values($input);
        $params = [];

        if (isset($input['EX'])) {
            $params[] = 'EX';
            $params[] = $input['EX'];
        }

        if (isset($input['PX'])) {
            $params[] = 'PX';
            $params[] = $input['PX'];
        }

        $params[] = $values[0];

        return $params;
    }
}
