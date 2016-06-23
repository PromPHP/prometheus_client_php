<?php
require __DIR__ . '/../vendor/autoload.php';

define('REDIS_HOST', isset($_SERVER['REDIS_HOST']) ? $_SERVER['REDIS_HOST'] : '127.0.0.1');

error_log('c='. $_GET['c']);

$redisAdapter = new \Prometheus\RedisAdapter(REDIS_HOST);
$registry = new \Prometheus\Registry($redisAdapter);
$counter = $registry->registerGauge('test', 'some_gauge', 'it sets', ['type']);
$counter->set(234, ['blue']);
$counter->set(123, ['red']);

$counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
$counter->increaseBy(1, ['blue']);
$counter->increaseBy(2, ['red']);

$histogram = $registry->registerHistogram('test', 'some_histogram', 'it observes', ['type'], [1, 2, 3.5, 4]);
$histogram->observe(2.5, ['blue']);

$registry->flush();

echo "OK\n";
