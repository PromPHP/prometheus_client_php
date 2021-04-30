<?php

require __DIR__ . '/../vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;
use Prometheus\Summary;

Redis::setDefaultOptions(['host' => $_SERVER['REDIS_HOST'] ?? '127.0.0.1']);
$connection = new \Redis();
$connection->connect($_SERVER['REDIS_HOST'] ?? '127.0.0.1');
$connection->setOption(\Redis::OPT_PREFIX, 'prefix:');
$adapter = Redis::fromExistingConnection($connection);
//$adapter = new Prometheus\Storage\Redis();
$adapter->wipeStorage();
$value = chr(62);

$label = 'foo';
$summary = new Summary($adapter, 'test', 'some_metric', 'help', [$label], 60, [0.5]);
$summary->observe(1, [$value]);

$metrics = $adapter->collect();
var_dump($metrics);
exit();



$adapter = $_GET['adapter'];

if ($adapter === 'redis') {
    Redis::setDefaultOptions(['host' => $_SERVER['REDIS_HOST'] ?? '127.0.0.1']);
    $adapter = new Prometheus\Storage\Redis();
} elseif ($adapter === 'apc') {
    $adapter = new Prometheus\Storage\APC();
} elseif ($adapter === 'in-memory') {
    $adapter = new Prometheus\Storage\InMemory();
}
$registry = new CollectorRegistry($adapter);
$renderer = new RenderTextFormat();
$result = $renderer->render($registry->getMetricFamilySamples());

header('Content-type: ' . RenderTextFormat::MIME_TYPE);
echo $result;
