FROM php:5.6-cli

RUN apt-get update && apt-get install -y \
     curl \
     git \
     subversion \
     unzip \
     wget

RUN pecl install redis-2.2.8 && docker-php-ext-enable redis

# Allow Composer to be run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -f -sS https://getcomposer.org/installer | php -- --install-dir=bin --filename=composer

COPY composer.json composer.json
RUN COMPOSER_VENDOR_DIR=/root/composer/vendor/ composer install

VOLUME /prometheus_client_php

WORKDIR /prometheus_client_php

CMD [ "php", "./example.php" ]
