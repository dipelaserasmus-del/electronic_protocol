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
cp "$ROOT/docker/dev/cloud-setup.sh" "$WORK/repo/docker/dev/"
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
run "mail"                          "force-recreate imap"                mail
run "down"                          "compose -p electronic-protocol-dev" down
run "e2e on Linux: host network"    "--network=host"                     e2e
run "e2e reloads the mock mailbox"  "force-recreate imap"                e2e
FAKE_UNAME=Darwin run "e2e on macOS: host.docker.internal" "APP_URL=http://host.docker.internal:8080" e2e
FAKE_UNAME=Darwin run "e2e on macOS: passes arguments" "-- --grep smoke" e2e --grep smoke
run "doctor"                        "version --format"                   doctor
run "real up"                       "compose -p electronic-protocol-real" real up
run "real load: no dump → message"  -                                    real help
mkdir -p "$WORK/repo/local-data/db"; echo "SELECT 1;" | gzip > "$WORK/repo/local-data/db/test.sql.gz"
run "real load (.sql.gz)"           "artisan migrate --force"            real load
run "real down"                     "compose -p electronic-protocol-real" real down

# no dump → must exit with a clear message (non-zero)
rm -f "$WORK/repo/local-data/db/"*
if (cd "$WORK/repo" && PATH="$WORK/bin:$PATH" FAKE_LOG="$LOG" "$BASH_BIN" ./dev.sh real load 2>&1) | grep -q "No dump found"; then
  echo "ok   real load without a dump says so"; else echo "FAIL real load without a dump"; fails=$((fails+1)); fi

echo "bash: $("$BASH_BIN" -c 'echo $BASH_VERSION') · $(uname -s 2>/dev/null)"
[ "$fails" -eq 0 ] || { echo "$fails failure(s)"; exit 1; }
echo "all dev.sh checks passed"
