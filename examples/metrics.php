<?php
require __DIR__ . '/../vendor/autoload.php';

use Prometheus\RedisAdapter;
use Prometheus\Registry;

$redisAdapter = new RedisAdapter('localhost');
$registry = new Registry($redisAdapter);
$result = $registry->toText();

header('Content-type: text/plain; version=0.0.4');
echo $result;
