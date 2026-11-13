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
#   ./dev.sh real ...      local copy of production data (load|up|down|wipe) — see docs/LOCAL-REAL-DATA.md
#
# App: http://localhost:8080 (demo users admin / anathetis / writer / reader, password: password)
# Outgoing mail (Mailpit): http://localhost:8025
# Uses .env.development (created from .env.development.example); your normal .env is not touched.
set -euo pipefail
cd "$(dirname "$0")"

ENV_FILE=.env.development
[ -f "$ENV_FILE" ] || cp .env.development.example "$ENV_FILE"

# own project name => own containers, network and database volume (never the normal installation's)
# Folders the web server writes to are made writable for everyone (a+rwX) instead of being handed to
# the container's www-data user: files then stay manageable by you on the host (VS Code, git, rm).
compose() { docker compose -p electronic-protocol-dev --env-file "$ENV_FILE" -f docker-compose.yml -f docker-compose.dev.yml "$@"; }
in_app() { compose exec -T app "$@"; }

load_mail() {
  in_app php docker/dev/make-emails.php tests/fixtures/emails/synthetic docker/dev/mail-preload
  compose up -d --force-recreate imap >/dev/null
  echo "Mock mailbox: protocol@school.test / secret (IMAP imap:3143)"
}

# ---- local copy of production data (owner's computer only; see docs/LOCAL-REAL-DATA.md) ----
REAL_ENV=.env.real
compose_real() { docker compose -p electronic-protocol-real --env-file "$REAL_ENV" -f docker-compose.yml -f docker-compose.real.yml "$@"; }
in_real() { compose_real exec -T app "$@"; }

real_cmd() {
  [ -f "$REAL_ENV" ] || cp .env.real.example "$REAL_ENV"
  mkdir -p local-data/db local-data/storage/app local-data/mail-preload local-data/tmp
  case "${1:-help}" in
    up)
      # optional private e-mails for the local test mailbox
      mkdir -p "local-data/mail-preload/protocol@school.test/INBOX"
      find "local-data/mail-preload/protocol@school.test/INBOX" -name '*.eml' -delete
      cp local-data/emails/*.eml "local-data/mail-preload/protocol@school.test/INBOX/" 2>/dev/null || true
      compose_real up -d
      compose_real up -d --force-recreate imap >/dev/null 2>&1   # reload the test mailbox
      # fresh checkout: install PHP dependencies (as your user) before running artisan
      [ -f vendor/autoload.php ] || compose_real exec -T -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer app composer install --no-interaction --no-progress
      in_real chmod -R a+rwX storage bootstrap/cache public/tmp
      grep -q '^APP_KEY=base64' "$REAL_ENV" || in_real php artisan key:generate --force
      echo "Local copy: http://localhost:$(grep '^APP_PORT=' "$REAL_ENV" | cut -d= -f2)  ·  caught e-mails: http://localhost:8026"
      ;;
    load)
      # newest dump in local-data/db (or the file given), .sql or .sql.gz
      local dump="${2:-$(ls -t local-data/db/*.sql local-data/db/*.sql.gz 2>/dev/null | head -1)}"
      [ -n "$dump" ] && [ -f "$dump" ] || { echo "No dump found. Put the production backup (.sql or .sql.gz) in local-data/db/"; exit 1; }
      real_cmd up >/dev/null
      local db; db=$(grep '^DB_DATABASE=' "$REAL_ENV" | cut -d= -f2)
      local rootpw; rootpw=$(grep '^DB_ROOT_PASSWORD=' "$REAL_ENV" | cut -d= -f2 || true); rootpw=${rootpw:-myStrongRootP@ss}
      for _ in $(seq 1 60); do compose_real exec -T db mysqladmin ping -uroot -p"$rootpw" --silent >/dev/null 2>&1 && break; sleep 2; done
      echo "Importing $(basename "$dump") into database '$db' (replaces its current content)..."
      compose_real exec -T db mysql -uroot -p"$rootpw" -e "DROP DATABASE IF EXISTS \`$db\`; CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$db\`.* TO 'laravel'@'%';" 2>/dev/null
      # strip CREATE DATABASE / USE lines so a dump made with --databases still goes into this database
      local importlog; importlog=$(mktemp)
      if ! { case "$dump" in *.gz) gunzip -c "$dump" ;; *) cat "$dump" ;; esac; } \
          | sed -E '/^(CREATE DATABASE|USE) /d' \
          | compose_real exec -T db mysql -uroot -p"$rootpw" --default-character-set=utf8mb4 "$db" 2>"$importlog"; then
        grep -v "Using a password" "$importlog" >&2 || true
        rm -f "$importlog"
        echo "IMPORT FAILED — the local copy is incomplete. Nothing else was done. Fix the backup file and run again." >&2
        exit 1
      fi
      rm -f "$importlog"
      # bring the imported schema up to the current code (only new migrations run; data is kept)
      in_real php artisan migrate --force
      # safety: the e-mail page of the local copy uses the local test mailbox, never the real account
      in_real php artisan tinker --execute='\App\Config::setConfigValueOf("defaultImapEmail", "default");' >/dev/null
      in_real chmod -R a+rwX storage/app
      # summary: counts only, no data is printed
      in_real php artisan tinker --execute='
        $files = 0; foreach (\App\Attachment::whereNotNull("savedPath")->pluck("savedPath") as $p) { if (\Storage::exists($p)) $files++; }
        echo "Loaded: ", \App\Protocol::count(), " protocols, ", \App\User::count(), " users, ", \App\Attachment::count(), " attachments (", $files, " files found in local-data/storage/app)\n";'
      ;;
    down) compose_real down ;;
    wipe) compose_real down -v; echo "Local copy removed (database volume deleted). Files in local-data/ are untouched." ;;
    logs) compose_real logs -f ;;
    artisan) shift; in_real php artisan "$@" ;;
    *) echo "usage: ./dev.sh real up|load [dump]|down|wipe|logs|artisan ..."; ;;
  esac
}

case "${1:-help}" in
  real) shift; real_cmd "$@" ;;
  up)
    mkdir -p docker/dev/mail-preload
    compose up -d --build
    # as your own user, so vendor/ stays yours (not root's) on the host
    compose exec -T -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer app composer install --no-interaction --no-progress
    in_app chmod -R a+rwX storage bootstrap/cache public/tmp
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
    in_app chmod -R a+rwX storage bootstrap/cache public/tmp
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
  *) sed -n '2,14p' "$0" ;;
esac
