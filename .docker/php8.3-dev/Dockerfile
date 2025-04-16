FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip

# bcmath
RUN docker-php-ext-install bcmath
RUN docker-php-ext-enable bcmath

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_MEMORY_LIMIT -1

RUN mkdir /.composer_cache
ENV COMPOSER_CACHE_DIR /.composer_cache

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# php extensions
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
