# Application image: Ubuntu 26.04 LTS + distribution PHP (security updates from Ubuntu until 2031).
# The PHP version is a build argument so it can be changed in one place (see task N01).
FROM ubuntu:26.04

ARG PHP_VERSION=8.5
ENV DEBIAN_FRONTEND=noninteractive \
    PHP_VERSION=${PHP_VERSION} \
    COMPOSER_ALLOW_SUPERUSER=1

# F09: an Ubuntu mirror that stops answering (security.ubuntu.com, 2026-11-23) made apt wait without end and the CI
# image builds hang for an hour: every request now gives up after 30 s and is tried again, up to 5 times.
RUN printf 'Acquire::Retries "5";\nAcquire::http::Timeout "30";\nAcquire::https::Timeout "30";\n' \
      > /etc/apt/apt.conf.d/80-retries

# ext-imap is intentionally NOT installed: it was removed from PHP core in 8.4, depends on the
# unmaintained c-client library, and webklex/php-imap talks IMAP over native sockets.
# mysql-client provides mysqldump for Ρυθμίσεις → Backup (B10).
# nodejs/npm (Ubuntu's own packages) build the frontend with Vite (N03); no Node needed on the host.
# F10: some security.ubuntu.com servers answered 404 for hours for a security update the index already listed
# (libtiff6, 2026-11-26), and every other build failed. If the install fails, the security updates are taken from the
# image's main archive instead (the first URIs of ubuntu.sources: archive.ubuntu.com, for amd64 and arm64 alike on
# 26.04), which carries the same «-security» suite.
RUN packages="ca-certificates curl unzip git tzdata \
      php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
      php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 \
      php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-zip \
      php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath \
      php${PHP_VERSION}-bz2 php${PHP_VERSION}-curl \
      composer \
      mysql-client \
      nodejs npm" \
 && sources=/etc/apt/sources.list.d/ubuntu.sources \
 && apt-get update \
 && if ! apt-get install -y --no-install-recommends $packages; then \
      main="$(sed -n 's/^URIs: *//p' "$sources" | head -n 1)"; \
      echo "F10: security.ubuntu.com failed; security updates from $main"; \
      sed -i "s|http://security.ubuntu.com/ubuntu/|$main|" "$sources" \
      && apt-get update && apt-get install -y --no-install-recommends $packages; \
    fi \
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
COPY docker/app/build-assets.sh /usr/local/bin/build-assets
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint
COPY --chown=www-data:www-data . /var/www/html
# frontend for rebuilt pages (public/build). When the source folder is mounted over /var/www/html (docker-compose.yml),
# the container builds it again at start if the sources changed (docker/app/entrypoint.sh).
RUN build-assets && rm -rf node_modules

EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]
