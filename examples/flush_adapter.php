<?php
require __DIR__ . '/../vendor/autoload.php';

use Prometheus\Storage\RedisCluster;


$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    define('REDIS_HOST', isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1');

    $redisAdapter = new Prometheus\Storage\Redis(array('host' => REDIS_HOST));
    $redisAdapter->flushRedis();
} elseif ($adapter === 'apc') {
    $apcAdapter = new Prometheus\Storage\APC();
    $apcAdapter->flushAPC();
} elseif ($adapter === 'in-memory') {
    $inMemoryAdapter = new Prometheus\Storage\InMemory();
    $inMemoryAdapter->flushMemory();
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
    $redisClusterAdapter = new Prometheus\Storage\RedisCluster();
    $redisClusterAdapter->flushRedisCluster();
}
