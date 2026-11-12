# Application image: Ubuntu 26.04 LTS + distribution PHP (security updates from Ubuntu until 2031).
# The PHP version is a build argument so it can be changed in one place (see task N01).
FROM ubuntu:26.04

ARG PHP_VERSION=8.5
ENV DEBIAN_FRONTEND=noninteractive \
    PHP_VERSION=${PHP_VERSION} \
    COMPOSER_ALLOW_SUPERUSER=1

# ext-imap is intentionally NOT installed: it was removed from PHP core in 8.4, depends on the
# unmaintained c-client library, and webklex/php-imap talks IMAP over native sockets.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates curl unzip git tzdata \
      php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
      php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 \
      php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
      php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath \
      php${PHP_VERSION}-bz2 php${PHP_VERSION}-curl \
      composer \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP-FPM: listen on TCP 9000 for the nginx container, log to stderr, keep env vars.
RUN sed -i -e 's|^listen = .*|listen = 9000|' \
           -e 's|^;clear_env = no|clear_env = no|' \
           /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf \
 && sed -i -e 's|^error_log = .*|error_log = /proc/self/fd/2|' /etc/php/${PHP_VERSION}/fpm/php-fpm.conf \
 && ln -s /usr/sbin/php-fpm${PHP_VERSION} /usr/local/sbin/php-fpm \
 && mkdir -p /run/php

COPY docker/php/local.ini /etc/php/${PHP_VERSION}/fpm/conf.d/99-laravel.ini
COPY docker/php/local.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-laravel.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

EXPOSE 9000
CMD ["php-fpm", "-F"]
