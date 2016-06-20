<?php

namespace Prometheus;


class Metric
{
    public static function metricName($namespace, $name)
    {
        return ($namespace ? $namespace . '_' : '') . $name;
    }

    public static function metricIdentifier($namespace, $name, $labels)
    {
        if (empty($labels)) {
            return self::metricName($namespace, $name);
        }
        return self::metricName($namespace, $name) . '_' . implode('_', $labels);
    }
}
