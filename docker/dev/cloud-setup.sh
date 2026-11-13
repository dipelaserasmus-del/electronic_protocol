#!/usr/bin/env bash
# One-time preparation of a Claude Code *cloud* sandbox (not needed on your own computer).
# Works around the sandbox's network limits: Docker Hub rate limit, no Debian/Alpine mirrors,
# no GitHub downloads from inside containers. Idempotent; safe to run again.
#
# Used by the cloud environment's setup script, or manually:  bash docker/dev/cloud-setup.sh
# Afterwards start the dev stack with:                         ./dev.sh cloud-up
set -euo pipefail
cd "$(dirname "$0")/../.."

started_here=0
if ! docker info >/dev/null 2>&1; then
  (dockerd > /tmp/dockerd.log 2>&1 &)
  for _ in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 1; done
  started_here=1
fi

# Images from the Google mirror of Docker Hub, re-tagged with the names the compose files use
for img in library/mysql:8.4 library/nginx:stable library/ubuntu:26.04 greenmail/standalone:2.1.3 axllent/mailpit:latest; do
  docker image inspect "${img#library/}" >/dev/null 2>&1 && continue
  docker pull -q "mirror.gcr.io/$img"
  docker tag "mirror.gcr.io/$img" "${img#library/}"
done

# App image: apt needs host networking in the sandbox
docker image inspect electronic-protocol-app >/dev/null 2>&1 || docker build -q --network host -t electronic-protocol-app .

# PHP dependencies on the host (the host's PHP may be older; the container runs PHP 8.5)
[ -f vendor/autoload.php ] || COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress --no-scripts --ignore-platform-req=php

[ -f .env.development ] || cp .env.development.example .env.development

# leave no background daemon behind when run as a setup script
if [ "$started_here" = 1 ] && [ "${KEEP_DOCKERD:-0}" != 1 ]; then pkill dockerd || true; fi
echo "cloud-setup: done"
