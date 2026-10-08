#!/bin/sh
set -eu

guard=${1:?guard path is required}
valid='https://issuer.example.test/'
lf='
'
cr=$(printf '\r')

expect_accept() {
    if ! EXPECTED_OIDC_ISSUER=$2 /bin/sh "$guard" /bin/true >/dev/null 2>&1; then
        echo "Guard rejected $1." >&2
        exit 1
    fi
}

expect_reject() {
    if EXPECTED_OIDC_ISSUER=$2 /bin/sh "$guard" /bin/true >/dev/null 2>&1; then
        echo "Guard accepted $1." >&2
        exit 1
    fi
}

expect_accept 'valid HTTPS issuer' "$valid"
expect_accept 'valid HTTPS issuer with port' 'https://127.0.0.1:9443/'
if (unset EXPECTED_OIDC_ISSUER; /bin/sh "$guard" /bin/true >/dev/null 2>&1); then
    echo 'Guard accepted a missing issuer.' >&2
    exit 1
fi
expect_reject 'HTTP issuer' 'http://issuer.example.test/'
expect_reject 'inline CSP injection' 'https://issuer.example.test/; form-action *'
expect_reject 'LF suffix' "${valid}${lf}"
expect_reject 'CRLF suffix' "${valid}${cr}${lf}"
expect_reject 'valid line followed by injection' "${valid}${lf}; form-action *"
echo 'OIDC issuer guard behavior passed.'
