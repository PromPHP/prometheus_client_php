# A prometheus client library written in PHP

[![Build Status](https://travis-ci.org/Jimdo/prometheus_client_php.svg?branch=master)](https://travis-ci.org/Jimdo/prometheus_client_php)

This uses redis to do the client side aggregation.
We recommend to run a local redis instance next to your PHP workers.

## Usage

Write some metrics:
```php
$registry = \Prometheus\Registry::getDefaultRegistry();

$counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
$counter->incBy(3, ['blue']);

$gauge = $registry->registerGauge('test', 'some_gauge', 'it sets', ['type']);
$gauge->set(2.5, ['blue']);

$histogram = $registry->registerHistogram('test', 'some_histogram', 'it observes', ['type'], [0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]);
$histogram->observe(3.5, ['blue']);
```

Expose the metrics:
```php
$registry = \Prometheus\Registry::getDefaultRegistry();
$result = $registry->toText();

header('Content-type: text/plain; version=0.0.4');
```

Change the redis options (the example shows the defaults):
```php
Registry::setDefaultRedisOptions(
    [
        'host' => '127.0.0.1',
        'port' => 6379,
        'connect_timeout' => 0.1 // in seconds
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
