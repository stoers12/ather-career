#!/bin/sh
set -eu

# Startup for an already-initialized Owner stack (selected explicitly by the
# local release overlay). It validates configuration and refuses to start on a
# failure, but never migrates, bootstraps owners or repairs reused storage:
# those are separately approved operations (docs/RUNTIME_TOPOLOGY.md §9).

/bin/sh /usr/local/bin/validate-oidc-issuer.sh

storage_root=${ATHERCAR_STORAGE_ROOT:-}
case "$storage_root" in
    /var/www/private-storage|/var/lib/ather-career/storage) ;;
    *)
        echo "Refusing to start with an unexpected private-media storage root." >&2
        exit 1
        ;;
esac
if [ ! -d "$storage_root" ] || [ -L "$storage_root" ] || [ "$(readlink -f "$storage_root")" != "$storage_root" ]; then
    echo "Refusing to start with a missing or unresolved private-media storage root." >&2
    exit 1
fi

if [ "${PORTFOLIO_PRODUCTION_SECURITY_CHECK:-0}" = "1" ]; then
    php /var/www/app/scripts/check-production-security.php --local-owner-stack
fi

exec docker-php-entrypoint "$@"
