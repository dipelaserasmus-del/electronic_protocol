#!/bin/sh
# Production-style installation with Docker (MySQL). For development use ./dev.sh instead.
set -e
[ -f .env ] && mv .env .env-old
cp .env-docker .env

docker compose up -d --build
sleep 20
docker compose exec app composer install --no-dev --no-interaction
docker compose exec app chown -R www-data:www-data storage bootstrap/cache public/tmp
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan config:cache
