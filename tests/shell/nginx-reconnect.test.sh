#!/bin/sh
# F06: nginx must keep working after the app container is recreated with a new IP address
# (e.g. after Docker Desktop restarts). Before F06, nginx resolved "app" only once at start-up
# and answered 502 Bad Gateway until nginx itself was restarted.
#
# Needs the running demo stack (./dev.sh up). Uses no data.
#   sh tests/shell/nginx-reconnect.test.sh
set -eu
cd "$(dirname "$0")/../.."

URL="${URL:-http://localhost:8080/login}"
compose() { docker compose -p electronic-protocol-dev --env-file "${ENV_FILE:-.env.development}" -f docker-compose.yml -f docker-compose.dev.yml "$@"; }
ip_of() { docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' ep-dev-app; }
# nginx waits up to 60 s for an address that no longer exists, so each check is capped at 5 s
code() { curl -s -o /dev/null --max-time 5 -w '%{http_code}' "$URL" || true; }

wait_for_200() {   # at most ~60 s in total
  end=$(( $(date +%s) + 60 ))
  while [ "$(date +%s)" -lt "$end" ]; do
    [ "$(code)" = 200 ] && return 0
    sleep 1
  done
  return 1
}

wait_for_200 || { echo "FAIL: $URL does not answer 200 before the test (is ./dev.sh up running?)"; exit 1; }

old_ip=$(ip_of)
net=$(docker inspect -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}' ep-dev-app)
# Recreate only the app container; nginx keeps running, exactly as when Docker restarts it.
# Docker may hand the freed address straight back, so placeholder containers take addresses
# until the app gets a different one.
placeholders=""
trap 'for p in $placeholders; do docker rm -f "$p" >/dev/null 2>&1 || true; done' EXIT
new_ip=$old_ip
attempt=0
while [ "$new_ip" = "$old_ip" ] && [ "$attempt" -lt 5 ]; do
  compose rm -s -f app >/dev/null 2>&1
  placeholders="$placeholders $(docker run -d --rm --network "$net" nginx:stable)"
  compose up -d --no-deps app >/dev/null 2>&1
  new_ip=$(ip_of)
  attempt=$((attempt + 1))
done
if [ "$new_ip" = "$old_ip" ]; then
  echo "FAIL: could not give the app container a new address (still $old_ip)"; exit 1
fi
echo "app container moved: $old_ip -> $new_ip (nginx not restarted)"

if wait_for_200; then
  echo "PASS: nginx reaches the recreated app ($URL -> 200)"
else
  echo "FAIL: nginx still answers $(code) after the app moved; it keeps the old address"; exit 1
fi
