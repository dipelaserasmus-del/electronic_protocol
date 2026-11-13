#!/bin/sh
# Runs a command on the host. Needed when VS Code is installed as a Flatpak: its terminal and tasks run
# inside the Flatpak sandbox, where Docker is not available. Outside Flatpak it simply runs the command.
if [ -f /.flatpak-info ]; then
  exec flatpak-spawn --host --env=TERM="${TERM:-xterm-256color}" "$@"
fi
exec "$@"
