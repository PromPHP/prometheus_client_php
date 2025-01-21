<?php

namespace Prometheus\Storage\RedisTxn\RedisScript;

use InvalidArgumentException;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\RedisTxn\Metric\Metadata;

class RedisScriptHelper
{
    /**
     * @var string
     */
    const PREFIX = 'PROMETHEUS_';

    const PROMETHEUS_METRIC_KEYS_SUFFIX = '_METRIC_KEYS';

    const PROMETHEUS_METRIC_META_SUFFIX = '_METRIC_META';

    const DEFAULT_TTL_SECONDS = 600;

    /**
     * @param string $metricType
     * @return string
     */
    public function getRegistryKey(string $metricType): string
    {
        $keyPrefix = self::PREFIX . $metricType . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        return implode(':', [$keyPrefix, 'keys']);
    }

    /**
     * @param string $metricType
     * @return string
     */
    public function getMetadataKey(string $metricType): string
    {
        return self::PREFIX . $metricType . self::PROMETHEUS_METRIC_META_SUFFIX;
    }

    /**
     * @param Metadata $metadata
     * @return string
     */
    public function getMetricKey(Metadata $metadata): string
    {
        $type = $metadata->getType();
        $name = $metadata->getName();
        $labelValues = $metadata->getLabelValuesEncoded();
        $keyPrefix = self::PREFIX . $type . self::PROMETHEUS_METRIC_KEYS_SUFFIX;
        return implode(':', [$keyPrefix, $name, $labelValues]);
    }

    /**
     * @param int $cmd
     * @return string
     */
    public function getRedisCommand(int $cmd): string
    {
        switch ($cmd) {
            case Adapter::COMMAND_INCREMENT_INTEGER:
                return 'incrby';
            case Adapter::COMMAND_INCREMENT_FLOAT:
                return 'incrbyfloat';
            case Adapter::COMMAND_SET:
                return 'set';
            default:
                throw new InvalidArgumentException("Unknown command");
        }
    }

    /**
     * @return int
     */
    public function getDefautlTtl(): int
    {
        return self::DEFAULT_TTL_SECONDS;
    }
}