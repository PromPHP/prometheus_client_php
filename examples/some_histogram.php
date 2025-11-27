<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Prometheus\Storage\RedisCluster;

error_log('c=' . $_GET['c']);

$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    Redis::setDefaultOptions(array('host' => isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1'));
    $adapter = new Prometheus\Storage\Redis();
} elseif ($adapter === 'apc') {
    $adapter = new Prometheus\Storage\APC();
} elseif ($adapter === 'in-memory') {
    $adapter = new Prometheus\Storage\InMemory();
} elseif ($adapter === 'redis-cluster') {
    $instanceID = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
    RedisCluster::setDefaultOptions(array(
        'redis_list' => ['tcp://127.0.0.1:7001', 'tcp://127.0.0.1:7002', 'tcp://127.0.0.1:7003'],
        'cluster' => 'redis',
        'password' => null,
        'timeout' => 0.1,
        'read_timeout' => 10,
        'persistent' => false
    ));
    RedisCluster::setPrefix('TEST_PROMETHEUS:' . $instanceID);
    RedisCluster::setHashTag('TEST_PROMETHEUS');
    $adapter = new Prometheus\Storage\RedisCluster();
}
$registry = new CollectorRegistry($adapter);

$histogram = $registry->registerHistogram('test', 'some_histogram', 'it observes', ['type'], [0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]);
$histogram->observe($_GET['c'], ['blue']);

echo "OK\n";
