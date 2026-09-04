#!/bin/sh
set -eu

storage_root=${ATHERCAR_STORAGE_ROOT:-}
case "$storage_root" in
    /var/www/private-storage|/var/lib/ather-career/storage) ;;
    *)
        echo "Refusing to initialize an unexpected private-media storage root." >&2
        exit 1
        ;;
esac

mkdir -p "$storage_root"
resolved_storage_root=$(readlink -f "$storage_root")
if [ "$resolved_storage_root" != "$storage_root" ] || [ -L "$storage_root" ]; then
    echo "Refusing to initialize an unresolved private-media storage root." >&2
    exit 1
fi

# The mounted storage root is wholly application-controlled. Do not follow
# symlinks or cross filesystem boundaries while repairing a reused volume.
find "$storage_root" -xdev -type d -exec chown www-data:www-data {} + -exec chmod 0700 {} +
find "$storage_root" -xdev -type f -exec chown www-data:www-data {} + -exec chmod 0600 {} +

if [ "${PORTFOLIO_PRODUCTION_SECURITY_CHECK:-0}" = "1" ]; then
    php /var/www/app/scripts/check-production-security.php
fi

exec docker-php-entrypoint "$@"
