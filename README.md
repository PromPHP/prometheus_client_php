# A prometheus client library written in PHP

[![Build Status](https://travis-ci.org/Jimdo/prometheus_client_php.svg?branch=master)](https://travis-ci.org/Jimdo/prometheus_client_php)

This uses redis to do the client side aggregation.
We recommend to run a local redis instance next to your PHP workers.

## Why redis?

Usually PHP worker processes don't share any state.

We decided to use redis because:
 * It is easy to deploy as a sidecar to the PHP worker processes (see [docker-compose.yml](docker-compose.yml)).
 * It provides us with easy to use concurrency mechanisms we need for the metric aggregation (e.g. `incrByFloat`).

We think this could be implemented with APCu as well and we will not exclude to do so in the future.
Of course we would also appreciate a pull-request.


## Usage

A simple counter:
```php
\Prometheus\CollectorRegistry::getDefault()
    ->registerCounter('', 'some_quick_counter', 'just a quick measurement')
    ->inc();
```

Write some enhanced metrics:
```php
$registry = \Prometheus\CollectorRegistry::getDefault();

$counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
$counter->incBy(3, ['blue']);

$gauge = $registry->registerGauge('test', 'some_gauge', 'it sets', ['type']);
$gauge->set(2.5, ['blue']);

$histogram = $registry->registerHistogram('test', 'some_histogram', 'it observes', ['type'], [0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]);
$histogram->observe(3.5, ['blue']);
```

Expose the metrics:
```php
$registry = \Prometheus\CollectorRegistry::getDefault();
$registry = CollectorRegistry::getDefault();

$renderer = new RenderTextFormat();
$result = $renderer->render($registry->getMetricFamilySamples());

header('Content-type: ' . RenderTextFormat::MIME_TYPE);
echo $result;
```

Change the redis options (the example shows the defaults):
```php
\Prometheus\Storage\Redis::setDefaultOptions(
    [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1, // in seconds
        'read_timeout' => 10, // in seconds
        'persistent_connections' => false
    ]
);
```

Also look at the [examples](examples).

## Development

### Dependencies

* PHP 5.3/5.6 (at least these versions are tested at the moment)
* PHP redis extension
* [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
* Redis

Start a redis instance:
```
docker-compose up redis
```

Run the tests:
```
composer install

# when redis is not listening on localhost:
# export REDIS_HOST=192.168.59.100
./vendor/bin/phpunit
```

## Black box testing

Just start the nginx, fpm & redis setup with docker-compose:
```
composer require guzzlehttp/guzzle=~6.0
docker-compose up
vendor/bin/phpunit tests/Test/BlackBoxTest.php
```
