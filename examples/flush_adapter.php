<?php

require __DIR__ . '/../vendor/autoload.php';

$adapterName = $_GET['adapter'];

$adapter = null;

if ($adapterName === 'redis') {
    define('REDIS_HOST', $_SERVER['REDIS_HOST'] ?? '127.0.0.1');

    $adapter = new Prometheus\Storage\Redis(['host' => REDIS_HOST]);
} elseif ($adapterName === 'apc') {
    $adapter = new Prometheus\Storage\APC();
} elseif ($adapterName === 'apcng') {
    $adapter = new Prometheus\Storage\APCng();
} elseif ($adapterName === 'in-memory') {
    $adapter = new Prometheus\Storage\InMemory();
}

$adapter->wipeStorage();
