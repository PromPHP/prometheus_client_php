<?php
require __DIR__ . '/../vendor/autoload.php';

$redisAdapter = new \Prometheus\RedisAdapter('localhost');
$redisAdapter->flushRedis();
