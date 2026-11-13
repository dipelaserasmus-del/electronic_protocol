#!/usr/bin/env bash
# Local development / preview helper.
#
#   ./dev.sh up            build + start the stack, install deps, migrate + seed on first run
#   ./dev.sh cloud-up      same as up, inside a Claude Code cloud sandbox (see docker/dev/cloud-setup.sh)
#   ./dev.sh mail          (re)generate test emails and reload the mock IMAP mailbox
#   ./dev.sh test [args]   run the PHPUnit test suite inside the app container (MySQL "testing" DB)
#   ./dev.sh reset         wipe the dev database and re-seed it
#   ./dev.sh artisan ...   run an artisan command      ./dev.sh shell   open a shell in the app container
#   ./dev.sh logs          follow logs                 ./dev.sh down    stop the stack
#
# App: http://localhost:8080 (demo users admin / anathetis / writer / reader, password: password)
# Outgoing mail (Mailpit): http://localhost:8025
# Uses .env.development (created from .env.development.example); your normal .env is not touched.
set -euo pipefail
cd "$(dirname "$0")"

ENV_FILE=.env.development
[ -f "$ENV_FILE" ] || cp .env.development.example "$ENV_FILE"

# own project name => own containers, network and database volume (never the normal installation's)
compose() { docker compose -p electronic-protocol-dev --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.dev.yml "$@"; }
in_app() { compose exec -T app "$@"; }

load_mail() {
  in_app php docker/dev/make-emails.php tests/fixtures/emails/synthetic docker/dev/mail-preload
  compose up -d --force-recreate imap >/dev/null
  echo "Mock mailbox: protocol@school.test / secret (IMAP imap:3143)"
}

case "${1:-help}" in
  up)
    mkdir -p docker/dev/mail-preload
    compose up -d --build
    in_app composer install --no-interaction --no-progress
    in_app chown -R www-data:www-data storage bootstrap/cache public/tmp
    grep -q '^APP_KEY=base64' "$ENV_FILE" || in_app php artisan key:generate --force
    if [ "$(in_app php artisan tinker --execute='echo \Schema::hasTable("roles") ? 1 : 0;' 2>/dev/null | tail -1 | tr -d "[:space:]")" != "1" ]; then
      in_app php artisan migrate --force --seed
      in_app php artisan db:seed --force --class=DevSeeder
    else
      in_app php artisan migrate --force
    fi
    load_mail
    echo "Ready: http://localhost:$(grep '^APP_PORT=' "$ENV_FILE" | cut -d= -f2)"
    ;;
  cloud-up)
    # Claude Code cloud sandbox: start Docker if needed, start the stack WITHOUT rebuilding
    # (images come from docker/dev/cloud-setup.sh), then migrate/seed on first run
    docker info >/dev/null 2>&1 || { (dockerd > /tmp/dockerd.log 2>&1 &); for _ in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 1; done; }
    bash docker/dev/cloud-setup.sh >/dev/null
    mkdir -p docker/dev/mail-preload
    compose up -d
    in_app php artisan package:discover >/dev/null
    in_app chown -R www-data:www-data storage bootstrap/cache public/tmp
    grep -q '^APP_KEY=base64' "$ENV_FILE" || in_app php artisan key:generate --force
    for _ in $(seq 1 30); do in_app php artisan migrate:status >/dev/null 2>&1 && break; sleep 2; done
    if [ "$(in_app php artisan tinker --execute='echo \Schema::hasTable("roles") ? 1 : 0;' 2>/dev/null | tail -1 | tr -d "[:space:]")" != "1" ]; then
      in_app php artisan migrate --force --seed
      in_app php artisan db:seed --force --class=DevSeeder
    else
      in_app php artisan migrate --force
    fi
    load_mail
    echo "Ready: http://localhost:$(grep '^APP_PORT=' "$ENV_FILE" | cut -d= -f2)"
    ;;
  mail) load_mail ;;
  test) shift
    # always start from an empty test database, like CI does (only the "testing" database is wiped)
    in_app php artisan db:wipe --env=testing --force >/dev/null
    in_app php vendor/bin/phpunit "$@" ;;
  reset) in_app php artisan migrate:fresh --force --seed && in_app php artisan db:seed --force --class=DevSeeder ;;
  artisan) shift; in_app php artisan "$@" ;;
  shell) compose exec app bash ;;
  logs) compose logs -f ;;
  down) compose down ;;
  *) sed -n '2,12p' "$0" ;;
esac
