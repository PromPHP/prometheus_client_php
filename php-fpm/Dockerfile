FROM php:5.6-fpm

RUN pecl install redis-2.2.8 && docker-php-ext-enable redis
RUN pecl install apcu-4.0.11 && docker-php-ext-enable apcu

COPY www.conf /usr/local/etc/php-fpm.d/
COPY docker-php-ext-apcu-cli.ini /usr/local/etc/php/conf.d/
