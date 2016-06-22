# A prometheus client library written in PHP

[![Build Status](https://travis-ci.org/Jimdo/prometheus_client_php.svg?branch=master)](https://travis-ci.org/Jimdo/prometheus_client_php)

This uses redis to do the client side aggregation.
We recommend to run a local redis instance next to your PHP workers.

## Usage

see [example.php](example.php)

## Development

### Dependencies

* PHP 5.6
* PHP redis extension
* [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
* Redis

Start a redis instance:
```
docker run -d --name redis -p 6379:6379 redis
```

Run the tests:
```
composer install

# when redis is not listening on localhost:
# export REDIS_HOST=192.168.59.100
./vendor/bin/phpunit
```
