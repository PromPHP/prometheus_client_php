# A prometheus client library written in PHP

This uses redis to do the client side aggregation.
We recommend to run a local redis instance next to your PHP workers.

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

./vendor/bin/phpunit
```