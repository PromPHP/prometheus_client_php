<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Predis\Client;
use Prometheus\Exception\StorageException;
use Prometheus\Storage\RedisClients\Predis as PredisClient;

class Predis extends AbstractRedis
{
    /**
     * @var mixed[]
     */
    private static $defaultParameters = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_write_timeout' => 10,
        'persistent' => false,
        'password' => null,
        'username' => null,
    ];

    /**
     * @var mixed[]
     */
    private static $defaultOptions = [
        'prefix' => '',
        'throw_errors' => true,
    ];

    /**
     * @var mixed[]
     */
    private $parameters = [];

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * Redis constructor.
     *
     * @param  mixed[]  $parameters
     * @param  mixed[]  $options
     */
    public function __construct(array $parameters = [], array $options = [])
    {
        $this->parameters = array_merge(self::$defaultParameters, $parameters);
        $this->options = array_merge(self::$defaultOptions, $options);
        $this->redis = PredisClient::create($this->parameters, $this->options);
    }

    /**
     * @throws StorageException
     */
    public static function fromExistingConnection(Client $client): self
    {
        $options = $client->getOptions();
        $allOptions = [
            'aggregate' => $options->aggregate,
            'cluster' => $options->cluster,
            'connections' => $options->connections,
            'exceptions' => $options->exceptions,
            'prefix' => $options->prefix,
            'commands' => $options->commands,
            'replication' => $options->replication,
        ];

        $self = new self;
        $self->redis = new PredisClient($client, $allOptions);

        return $self;
    }

    /**
     * @param  mixed[]  $parameters
     */
    public static function setDefaultParameters(array $parameters): void
    {
        self::$defaultParameters = array_merge(self::$defaultParameters, $parameters);
    }

    /**
     * @param  mixed[]  $options
     */
    public static function setDefaultOptions(array $options): void
    {
        self::$defaultOptions = array_merge(self::$defaultOptions, $options);
    }
}
