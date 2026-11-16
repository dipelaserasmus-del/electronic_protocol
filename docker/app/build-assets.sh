#!/bin/sh
# Builds the frontend of the rebuilt pages (public/build) when its sources changed since the last build (N03).
# Used by the image build and by the app container at start, so a production install or a "git pull" + restart
# needs no extra step. The fingerprint covers everything Vite reads: package-lock.json, vite.config.js, resources/modern.
set -e
cd /var/www/html
[ -f package.json ] || exit 0
hash=$( { cat package-lock.json vite.config.js; find resources/modern -type f | sort | xargs cat; } | sha256sum | cut -d' ' -f1)
if [ -f public/build/manifest.json ] && [ "$(cat public/build/.source-hash 2>/dev/null)" = "$hash" ]; then
  exit 0
fi
echo "Building the frontend (public/build)..."
npm ci --no-audit --no-fund --loglevel=error >/dev/null
npm run build --silent >/dev/null
echo "$hash" > public/build/.source-hash
