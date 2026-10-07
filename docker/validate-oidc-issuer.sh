#!/bin/sh
set -eu

# This value is interpolated into the CSP source list by Apache and Caddy.
# Reject missing values and CSP metacharacters before either server starts.
if ! printf '%s\n' "${EXPECTED_OIDC_ISSUER:-}" | LC_ALL=C grep -Eq '^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/$'; then
    echo 'Refusing to start with an invalid EXPECTED_OIDC_ISSUER.' >&2
    exit 1
fi

if [ "$#" -gt 0 ]; then
    exec "$@"
fi
