#!/bin/sh
# App container start: build the frontend if needed (skipped when ASSETS_AUTO_BUILD=0: the dev and real-data
# stacks build it as the host user through ./dev.sh, so the files stay the user's own), then run php-fpm.
set -e
if [ "${ASSETS_AUTO_BUILD:-1}" = "1" ]; then
  /usr/local/bin/build-assets || echo "WARNING: the frontend could not be built; pages on the new layout will fail." >&2
fi
exec "$@"
