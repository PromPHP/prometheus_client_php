<?php

namespace Prometheus;


class Metric
{
    public static function metricName($namespace, $name)
    {
        return ($namespace ? $namespace . '_' : '') . $name;
    }
}
