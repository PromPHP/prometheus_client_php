<?php
require __DIR__ . '/../vendor/autoload.php';

use Prometheus\RedisAdapter;
use Prometheus\Registry;

define('REDIS_HOST', isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1');

$redisAdapter = new RedisAdapter(REDIS_HOST);
$registry = new Registry($redisAdapter);
$result = $registry->toText();

header('Content-type: text/plain; version=0.0.4');
echo $result;
