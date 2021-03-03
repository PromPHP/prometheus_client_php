<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

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

$count = preg_match('/^[0-9]+$/', $_GET['c'])
    ? intval($_GET['c'])
    : floatval($_GET['c']);

$counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
$counter->incBy($count, ['blue']);

echo "OK\n";
