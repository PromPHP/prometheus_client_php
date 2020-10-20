FROM php:7.4-fpm

RUN pecl install redis-5.3.1 && docker-php-ext-enable redis
RUN pecl install apcu-5.1.19 && docker-php-ext-enable apcu

COPY www.conf /usr/local/etc/php-fpm.d/
COPY docker-php-ext-apcu-cli.ini /usr/local/etc/php/conf.d/
