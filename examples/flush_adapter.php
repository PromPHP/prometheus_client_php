<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    define('REDIS_HOST', $_SERVER['REDIS_HOST'] ?? '127.0.0.1');

    $redisAdapter = new Redis(['host' => REDIS_HOST]);
    $redisAdapter->flushRedis();
} elseif ($adapter === 'apc') {
    $apcAdapter = new APC();
    $apcAdapter->flushAPC();
} elseif ($adapter === 'in-memory') {
    $inMemoryAdapter = new InMemory();
    $inMemoryAdapter->flushMemory();
}
