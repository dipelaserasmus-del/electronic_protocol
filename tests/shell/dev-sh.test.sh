#!/bin/sh
# Runs ./dev.sh with a fake `docker` (and `curl`) on PATH and checks the commands it would run.
# Purpose: dev.sh must work with macOS's bash 3.2 and BSD tools as well as on Linux (L02). CI runs this on
# ubuntu-latest and macos-latest. Usage: sh tests/shell/dev-sh.test.sh [bash binary]   (default: /bin/bash)
set -eu
BASH_BIN=${1:-/bin/bash}
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
WORK=$(mktemp -d); trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/repo/docker/dev" "$WORK/bin"
cp "$ROOT/dev.sh" "$ROOT/.env.development.example" "$ROOT/.env.real.example" "$WORK/repo/"
cp "$ROOT/docker/dev/cloud-setup.sh" "$ROOT/docker/dev/test-mailbox.env.example" "$WORK/repo/docker/dev/"
LOG="$WORK/docker.log"

cat > "$WORK/bin/docker" <<'FAKE'
#!/bin/sh
echo "docker $*" >> "$FAKE_LOG"
case "$*" in
  *"version --format"*) echo 99.0 ;;
  *"compose version"*) echo 9.9 ;;
  *"Schema::hasTable"*) echo 1 ;;
  *"imap://imap"*) echo "* 9 EXISTS" ;;
  *"--default-character-set=utf8mb4"*) cat > /dev/null ;;   # the import reads the whole dump, like mysql
esac
exit 0
FAKE
printf '#!/bin/sh\necho 200\n' > "$WORK/bin/curl"
printf '#!/bin/sh\necho "${FAKE_UNAME:-Linux}"\n' > "$WORK/bin/uname"
chmod +x "$WORK/bin/docker" "$WORK/bin/curl" "$WORK/bin/uname"

fails=0
run() {   # run <description> <expected substring in docker log | -> <dev.sh args...>
  desc=$1; want=$2; shift 2
  : > "$LOG"
  if ! out=$(cd "$WORK/repo" && env -u CI -u PLAYWRIGHT_BROWSERS_PATH -u E2E_HOST_NODE PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" \
             "$BASH_BIN" ./dev.sh "$@" 2>&1); then
    echo "FAIL $desc: exit code ≠ 0"; echo "$out" | sed 's/^/     /'; fails=$((fails+1)); return
  fi
  if [ "$want" != "-" ] && ! grep -qF -- "$want" "$LOG"; then
    echo "FAIL $desc: expected docker call containing: $want"; sed 's/^/     /' "$LOG"; fails=$((fails+1)); return
  fi
  echo "ok   $desc"
}

run "help"                          -                                   help
run "test without arguments"        "exec -T app php vendor/bin/phpunit" test
run "test with filter"              "phpunit --filter Foo"               test --filter Foo
run "artisan"                       "php artisan migrate:status"         artisan migrate:status
run "up (existing database)"        "artisan migrate --force"            up
# storage is opened up again after migrate/seed (files the seeder creates as root must be writable by the web server)
last() { grep -n -- "$1" "$LOG" | tail -1 | cut -d: -f1; }
if [ "$(last 'chmod -R a+rwX storage')" -gt "$(last 'artisan migrate')" ]; then echo "ok   up: storage writable after seeding"
else echo "FAIL up: chmod of storage must come after migrate/seed"; fails=$((fails+1)); fi
run "mail"                          "force-recreate imap"                mail
run "down"                          "compose -p electronic-protocol-dev" down
run "e2e on Linux: host network"    "--network=host"                     e2e
run "e2e reloads the mock mailbox"  "force-recreate imap"                e2e
FAKE_UNAME=Darwin run "e2e on macOS: host.docker.internal" "APP_URL=http://host.docker.internal:8080" e2e
FAKE_UNAME=Darwin run "e2e on macOS: passes arguments" "-- --grep smoke" e2e --grep smoke
run "doctor"                        "version --format"                   doctor
run "real up"                       "compose -p electronic-protocol-real" real up
# L03: the optional test mailbox file is created empty (no account) and kept once filled in
if [ -f "$WORK/repo/local-data/test-mailbox.env" ] && ! grep -qE '^TEST_IMAP_HOST=.+' "$WORK/repo/local-data/test-mailbox.env"; then
  echo "ok   real up creates an empty local-data/test-mailbox.env"; else echo "FAIL real up: local-data/test-mailbox.env"; fails=$((fails+1)); fi
printf 'TEST_IMAP_HOST=imap.example.org\nTEST_IMAP_USERNAME=demo@example.org\n' > "$WORK/repo/local-data/test-mailbox.env"
if (cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real up 2>&1) | grep -q "Test mailbox: set" \
   && grep -q '^TEST_IMAP_HOST=imap.example.org' "$WORK/repo/local-data/test-mailbox.env"; then
  echo "ok   real up keeps a filled-in test mailbox and says so"; else echo "FAIL real up with a test mailbox"; fails=$((fails+1)); fi
# an older file without the SMTP lines gets them appended once, its own lines kept
printf 'TEST_IMAP_HOST=imap.example.org\n' > "$WORK/repo/local-data/test-mailbox.env"
(cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real up >/dev/null 2>&1)
(cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real up >/dev/null 2>&1)
if [ "$(grep -c '^TEST_SMTP_HOST=$' "$WORK/repo/local-data/test-mailbox.env")" = 1 ] && grep -q '^TEST_IMAP_HOST=imap.example.org' "$WORK/repo/local-data/test-mailbox.env"; then
  echo "ok   real up adds the SMTP lines to an older test mailbox file once"; else echo "FAIL real up: SMTP lines"; fails=$((fails+1)); fi
run "real check-names (F08)"       "php tools/check-attachment-names.php storage/app" real check-names
run "real load: no dump → message"  -                                    real help
mkdir -p "$WORK/repo/local-data/db"; echo "SELECT 1;" | gzip > "$WORK/repo/local-data/db/test.sql.gz"
run "real load (.sql.gz)"           "artisan migrate --force"            real load
run "real load adopts old backups"  "artisan migrate:adopt-legacy"       real load
run "real load measures attachments (D03)" "artisan attachments:fill-metadata" real load
# D10: without local-data/server-files the app's files stay as they are, and nothing moves them (no glossary merge)
: > "$LOG"; (cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real load >/dev/null 2>&1)
if ! grep -q "rm -rf storage/app/arxeio" "$LOG" && ! grep -q "attachments:import" "$LOG" && ! grep -q "folders:glossary" "$LOG"; then
  echo "ok   real load without server-files keeps the app's files"; else echo "FAIL real load without server-files"; fails=$((fails+1)); fi
# D10: with it, the app's files are rebuilt from it by the database, after migrate, before measuring and the glossary
mkdir -p "$WORK/repo/local-data/server-files/arxeio"
run "real load copies the server's files (D10)" "artisan attachments:import local-data/server-files --apply" real load
run "real load applies the folder glossary (D10)" "artisan folders:glossary --apply --brief" real load
if [ "$(last 'artisan migrate --force')" -lt "$(last 'rm -rf storage/app/arxeio storage/app/trash')" ] \
   && [ "$(last 'rm -rf storage/app/arxeio')" -lt "$(last 'attachments:import')" ] \
   && [ "$(last 'attachments:import')" -lt "$(last 'attachments:fill-metadata')" ] \
   && [ "$(last 'attachments:fill-metadata')" -lt "$(last 'folders:glossary')" ]; then
  echo "ok   real load: migrate → files → measure → glossary"; else echo "FAIL real load order"; sed 's/^/     /' "$LOG"; fails=$((fails+1)); fi
# D10: a server-files folder without arxeio/ stops before anything is deleted
rmdir "$WORK/repo/local-data/server-files/arxeio"; : > "$LOG"
if ! (cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real load >/dev/null 2>&1) && ! grep -q "rm -rf" "$LOG"; then
  echo "ok   real load: server-files without arxeio stops, deletes nothing"; else echo "FAIL real load: server-files without arxeio"; fails=$((fails+1)); fi
rm -rf "$WORK/repo/local-data/server-files"
rm -f "$WORK/repo/local-data/db/"*; (cd "$WORK" && echo "SELECT 1;" > dump.sql && zip -q repo/local-data/db/export.zip dump.sql)
run "real load (.zip from phpMyAdmin)" "artisan migrate --force"          real load
run "real down"                     "compose -p electronic-protocol-real" real down

# no dump → must exit with a clear message (non-zero)
rm -f "$WORK/repo/local-data/db/"*
if (cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real load 2>&1) | grep -q "No dump found"; then
  echo "ok   real load without a dump says so"; else echo "FAIL real load without a dump"; fails=$((fails+1)); fi

echo "bash: $("$BASH_BIN" -c 'echo $BASH_VERSION') · $(uname -s 2>/dev/null)"
[ "$fails" -eq 0 ] || { echo "$fails failure(s)"; exit 1; }
echo "all dev.sh checks passed"
