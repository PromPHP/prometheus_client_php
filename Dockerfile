FROM php:5.6-fpm

RUN pecl install redis-2.2.8 && docker-php-ext-enable redis
