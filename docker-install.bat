@echo off
REM Production-style installation with Docker (MySQL). For development use dev.sh (Linux/WSL).
if exist .env ren .env .env-old
copy .env-docker .env

docker compose up -d --build
timeout /t 20

docker compose exec app composer install --no-dev --no-interaction
docker compose exec app chown -R www-data:www-data storage bootstrap/cache public/tmp
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan config:cache
