<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\Registry;

Registry::setDefaultRedisOptions(array('host' => isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1'));
$registry = Registry::getDefaultRegistry();
$result = $registry->toText();

header('Content-type: text/plain; version=0.0.4');
echo $result;
