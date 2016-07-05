<?php
require __DIR__ . '/../vendor/autoload.php';

define('REDIS_HOST', isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1');

$redisAdapter = new Prometheus\Storage\Redis(array('host' => REDIS_HOST));
$redisAdapter->flushRedis();
