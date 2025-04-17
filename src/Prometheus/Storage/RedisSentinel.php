<?php

namespace Prometheus\Storage;

use Prometheus\Exception\StorageException;

class RedisSentinel
{
    /**
     * @var \RedisSentinel
     */
    private $sentinel;

    /**
     * @var mixed[]
     */
    private $options = [];

    /**
     * @var mixed[]
     */
    private static $defaultOptions = [
            'enable' => false,   // if enabled uses sentinel to get the master before connecting to redis
            'host' => '127.0.0.1',  //  phpredis sentinel address of the redis
            'port' => 26379, //  phpredis sentinel port of the primary redis server, default 26379 if empty.
            'service' => 'myprimary', //, phpredis sentinel primary name, default myprimary
            'timeout' => 0, // phpredis sentinel connection timeout
            'persistent' => null, // phpredis sentinel persistence parameter
            'retry_interval' => 0, // phpredis sentinel retry interval
            'read_timeout' => 0,  // phpredis sentinel read timeout
            'reconnect' => 0, // retries after losing connection to redis asking for a new primary, if -1 will retry indefinetely
            'username' => '', // phpredis sentinel auth username
            'password' => '', // phpredis sentinel auth password
            'ssl' => null,
    ];
    
    /**
     * @param \RedisSentinel $redisSentinel
     * @return self
     * @throws StorageException
     */
    public static function fromExistingConnection(\RedisSentinel $redisSentinel) : self {
        $sentinel = new self();
        $sentinel->sentinel = $redisSentinel;
        $sentinel->getMaster();
        return $sentinel;
    }    

    /**
     * Redis constructor.
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        $this->options = [...self::$defaultOptions, ...$options];
        $this->sentinel = $this->connectToSentinel($this->options);
    }

    /**
     * {@inheritdoc}
     * @param mixed[] $config
     * @return mixed[]|bool
     * @throws StorageException|\RedisException
     */
    public function getMaster(): array|bool
    {
        $service = $this->options['service'];
        
        try {
            if(!$this->sentinel) {
                $this->sentinel = $this->connectToSentinel($this->options);
            }

            $master = $this->sentinel->master($service);
        } catch (\RedisException $e){
            throw new StorageException(
                sprintf("Can't connect to RedisSentinel server. %s", $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        if (! $this->isValidMaster($master)) {
            throw new StorageException(sprintf("No master found for service '%s'.", $service));
        }

        return $master;
    }

    /**
     * Check whether master is valid or not.
     * @param mixed[]|bool $master
     * @return bool
     */
    protected function isValidMaster(array|bool $master): bool
    {
        return is_array($master) && isset($master['ip']) && isset($master['port']);
    }

    /**
     * Connect to the configured Redis Sentinel instance.
     * @return \RedisSentinel
     * @throws StorageException
     */
    private function connectToSentinel(): \RedisSentinel
    {
        $host = $this->options['host'] ?? '';
        $port = $this->options['port'] ?? 26379;
        $timeout = $this->options['timeout'] ?? 0.2;
        $persistent = $this->options['persistent'] ?? null;
        $retryInterval = $this->options['retry_interval'] ?? 0;
        $readTimeout = $this->options['read_timeout'] ?? 0;
        $username = $this->options['username'] ?? '';
        $password = $this->options['password'] ?? '';
        $ssl = $this->options['ssl'] ?? null;

        if (strlen(trim($host)) === 0) {
            throw new StorageException('No host has been specified for the Redis Sentinel connection.');
        }

        $auth = null;
        if (strlen(trim($username)) !== 0 && strlen(trim($password)) !== 0) {
            $auth = [$username, $password];
        } elseif (strlen(trim($password)) !== 0) {
            $auth = $password;
        }

        if (version_compare((string)phpversion('redis'), '6.0', '>=')) {
            $options = [
                'host' => $host,
                'port' => $port,
                'connectTimeout' => $timeout,
                'persistent' => $persistent,
                'retryInterval' => $retryInterval,
                'readTimeout' => $readTimeout,
            ];

            if ($auth !== null) {
                $options['auth'] = $auth;
            }

            if (version_compare((string)phpversion('redis'), '6.1', '>=') && $ssl !== null) {
                $options['ssl'] = $ssl;
            }
            // @phpstan-ignore arguments.count, argument.type
            return new \RedisSentinel($options);
        }

        if ($auth !== null) {
            /**
             *  @phpstan-ignore arguments.count
             **/
            return new \RedisSentinel($host, $port, $timeout, $persistent, $retryInterval, $readTimeout, $auth);
        }
        return new \RedisSentinel($host, $port, $timeout, $persistent, $retryInterval, $readTimeout);
    }

    public function getSentinel() : \RedisSentinel {
        return $this->sentinel;
    }
}
