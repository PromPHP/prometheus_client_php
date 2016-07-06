<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;


error_log('c='. $_GET['c']);

CollectorRegistry::setDefaultRedisOptions(array('host' => isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1'));
$registry = CollectorRegistry::getDefault();

$gauge = $registry->registerGauge('test', 'some_gauge', 'it sets', ['type']);
$gauge->set($_GET['c'], ['blue']);

echo "OK\n";
