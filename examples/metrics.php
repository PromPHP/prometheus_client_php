<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;

CollectorRegistry::setDefaultRedisOptions(array('host' => isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1'));
$registry = CollectorRegistry::getDefaultRegistry();
$result = $registry->toText();

header('Content-type: text/plain; version=0.0.4');
echo $result;
