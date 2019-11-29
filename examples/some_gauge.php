<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;


error_log('c=' . $_GET['c']);

$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    Redis::setDefaultOptions(['host' => $_SERVER['REDIS_HOST'] ?? '127.0.0.1']);
    $adapter = new Prometheus\Storage\Redis();
} elseif ($adapter === 'apc') {
    $adapter = new Prometheus\Storage\APC();
} elseif ($adapter === 'in-memory') {
    $adapter = new Prometheus\Storage\InMemory();
}
$registry = new CollectorRegistry($adapter);

$gauge = $registry->registerGauge('test', 'some_gauge', 'it sets', ['type']);
$gauge->set($_GET['c'], ['blue']);

echo "OK\n";
