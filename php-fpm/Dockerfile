FROM php:8.1-fpm

RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install apcu && docker-php-ext-enable apcu

COPY www.conf /usr/local/etc/php-fpm.d/
COPY docker-php-ext-apcu-cli.ini /usr/local/etc/php/conf.d/
