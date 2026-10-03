#!/bin/sh
set -eu

# Compatibility entry point for the Stage-4C recovery contract. It restores
# only into an approved new disposable database namespace and an empty media
# target; it never stops services or replaces a live installation.
exec php "$(dirname "$0")/stage4c-recovery.php" restore "$@"
