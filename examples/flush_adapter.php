<?php

require __DIR__ . '/../vendor/autoload.php';

$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    define('REDIS_HOST', $_SERVER['REDIS_HOST'] ?? '127.0.0.1');

    $redisAdapter = new Prometheus\Storage\Redis(['host' => REDIS_HOST]);
    $redisAdapter->flushRedis();
} elseif ($adapter === 'apc') {
    $apcAdapter = new Prometheus\Storage\APC();
    $apcAdapter->flushAPC();
} elseif ($adapter === 'in-memory') {
    $inMemoryAdapter = new Prometheus\Storage\InMemory();
    $inMemoryAdapter->flushMemory();
}
