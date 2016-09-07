# A prometheus client library written in PHP

[![Build Status](https://travis-ci.org/Jimdo/prometheus_client_php.svg?branch=master)](https://travis-ci.org/Jimdo/prometheus_client_php)
[![Code Climate](https://codeclimate.com/github/Jimdo/prometheus_client_php.png)](https://codeclimate.com/github/Jimdo/prometheus_client_php)


This library uses Redis or APCu to do the client side aggregation.
If using Redis, we recommend to run a local Redis instance next to your PHP workers.

## How does it work?

Usually PHP worker processes don't share any state.
You can pick from two adapters.
One uses Redis the other APC.
While the former needs a separate binary running, the latter just needs the [APC](https://pecl.php.net/package/APCU) extension to be installed.

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

Change the Redis options (the example shows the defaults):
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

* PHP 5.6
* PHP Redis extension
* PHP APCu extension
* [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
* Redis

Start a Redis instance:
```
docker-compose up Redis
```

Run the tests:
```
composer install

# when Redis is not listening on localhost:
# export REDIS_HOST=192.168.59.100
./vendor/bin/phpunit
```

## Black box testing

Just start the nginx, fpm & Redis setup with docker-compose:
```
composer require guzzlehttp/guzzle=~6.0
docker-compose up
```
Pick the adapter you want to test.

```
ADAPTER=redis vendor/bin/phpunit tests/Test/BlackBoxTest.php
ADAPTER=apc vendor/bin/phpunit tests/Test/BlackBoxTest.php
```
