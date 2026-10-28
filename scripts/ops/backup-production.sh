#!/bin/sh
set -eu

# Compatibility entry point for the Stage-4C recovery contract. The PHP tool
# requires every target explicitly and fails closed in production mode unless
# encryption or a declared encrypted-storage layer is provided.
exec php "$(dirname "$0")/stage4c-recovery.php" backup "$@"
