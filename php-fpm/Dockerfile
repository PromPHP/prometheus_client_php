FROM php:8.2-fpm

RUN apt-get -y install git libzip-dev zip unzip php-zip
RUN pecl install redis && docker-php-ext-enable redis \
  && pecl install apcu && docker-php-ext-enable apcu \
  && pecl install zip && docker-php-ext-enable zip
RUN cd /var/www/html \
    && curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php composer-setup.php \
    && rm composer-setup.php \
    && composer.phar install

COPY www.conf /usr/local/etc/php-fpm.d/
COPY docker-php-ext-apcu-cli.ini /usr/local/etc/php/conf.d/
