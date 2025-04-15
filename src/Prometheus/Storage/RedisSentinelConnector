<?php

namespace Prometheus\Storage;
use Prometheus\Exception\StorageException;
use RedisSentinel;

class RedisSentinelConnector
{

    /**
     * {@inheritdoc}
     *
     * @throws RedisException
     * @throws StorageException
     */
    public function getMaster(array $config): array
    {
        $service = $config['service'];

        $sentinel = $this->connectToSentinel($config);

        $master = $sentinel->master($service);

        if (! $this->isValidMaster($master)) {
            throw new StorageException(sprintf("No master found for service '%s'.", $service));
        }

        return $master;
    }

    /**
     * Check whether master is valid or not.
     */
    protected function isValidMaster(mixed $master): bool
    {
        return is_array($master) && isset($master['ip']) && isset($master['port']);
    }

    /**
     * Connect to the configured Redis Sentinel instance.
     *
     * @throws StorageException
     */
    private function connectToSentinel(array $config): RedisSentinel
    {
        $host = $config['host'] ?? '';
        $port = $config['port'] ?? 26379;
        $timeout = $config['timeout'] ?? 0.2;
        $persistent = $config['persistent'] ?? null;
        $retryInterval = $config['retry_interval'] ?? 0;
        $readTimeout = $config['read_timeout'] ?? 0;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $ssl = $config['ssl'] ?? null;

        if (strlen(trim($host)) === 0) {
            throw new StorageException('No host has been specified for the Redis Sentinel connection.');
        }

        $auth = null;
        if (strlen(trim($username)) !== 0 && strlen(trim($password)) !== 0) {
            $auth = [$username, $password];
        } elseif (strlen(trim($password)) !== 0) {
            $auth = $password;
        }

        if (version_compare(phpversion('redis'), '6.0', '>=')) {
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

            if (version_compare(phpversion('redis'), '6.1', '>=') && $ssl !== null) {
                $options['ssl'] = $ssl;
            }

            return new RedisSentinel($options);
        }

        if ($auth !== null) {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            return new RedisSentinel($host, $port, $timeout, $persistent, $retryInterval, $readTimeout, $auth);
        }

        return new RedisSentinel($host, $port, $timeout, $persistent, $retryInterval, $readTimeout);
    }
}
