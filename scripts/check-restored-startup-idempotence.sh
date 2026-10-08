#!/usr/bin/env bash
set -euo pipefail

db_container=ather-career-ci-db-1
restore_db="ather_career_test_$(openssl rand -hex 12)"
[[ "$restore_db" =~ ^ather_career_test_[a-f0-9]{24}$ ]]
[[ "$MYSQL_USER" =~ ^[a-z_][a-z0-9_]{0,31}$ ]]

cleanup() {
    docker exec "$db_container" mysql --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
        -e "DROP DATABASE IF EXISTS \`$restore_db\`;" >/dev/null 2>&1 || true
    docker exec "$db_container" rm -f /tmp/phase2b-startup-source.sql \
        /tmp/phase2b-startup-before.sql /tmp/phase2b-startup-after.sql >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker exec "$db_container" sh -ec '
    mysqldump --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
        --single-transaction --skip-lock-tables --routines --events --triggers \
        --set-charset --default-character-set=utf8mb4 --hex-blob --no-tablespaces \
        --skip-comments --skip-dump-date "$1" > /tmp/phase2b-startup-source.sql
' sh "$MYSQL_DATABASE"
docker exec "$db_container" mysql --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
    -e "CREATE DATABASE \`$restore_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci; GRANT ALL PRIVILEGES ON \`$restore_db\`.* TO '$MYSQL_USER'@'%';"
docker exec "$db_container" sh -ec \
    'mysql --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf "$1" < /tmp/phase2b-startup-source.sql' \
    sh "$restore_db"

ledger_before=$(docker exec "$db_container" mysql --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
    -N -B "$restore_db" -e "SELECT GROUP_CONCAT(version ORDER BY version SEPARATOR ',') FROM schema_migrations;")
expected_ledger='001,002,003,004,005,006,007,008,009,010,011,012,013,014'
test "$ledger_before" = "$expected_ledger"

dump_restored() {
    docker exec "$db_container" sh -ec '
        mysqldump --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
            --single-transaction --skip-lock-tables --routines --events --triggers \
            --set-charset --default-character-set=utf8mb4 --hex-blob --no-tablespaces \
            --skip-comments --skip-dump-date "$1" > "$2"
    ' sh "$restore_db" "$1"
}
dump_restored /tmp/phase2b-startup-before.sql

docker run --rm --network ather-career-ci_default \
    -e DB_HOST=db -e DB_PORT=3306 -e DB_NAME="$restore_db" -e DB_USER -e DB_PASSWORD \
    -e APP_ENV=production -e PUBLIC_BASE_URL -e SESSION_COOKIE_SECURE \
    -e EXPECTED_OIDC_ISSUER -e OIDC_CLIENT_ID -e OIDC_CLIENT_SECRET -e OIDC_REDIRECT_URI \
    -e EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY \
    -e ATHERCAR_STORAGE_ROOT=/var/lib/ather-career/storage \
    -e PORTFOLIO_PRODUCTION_SECURITY_CHECK=0 \
    "$PORTFOLIO_PRODUCTION_IMAGE" true

dump_restored /tmp/phase2b-startup-after.sql
ledger_after=$(docker exec "$db_container" mysql --defaults-extra-file=/tmp/ather-career-ci-mysql.cnf \
    -N -B "$restore_db" -e "SELECT GROUP_CONCAT(version ORDER BY version SEPARATOR ',') FROM schema_migrations;")
test "$ledger_after" = "$ledger_before"
before_sha=$(docker exec "$db_container" cat /tmp/phase2b-startup-before.sql | sha256sum | cut -d' ' -f1)
after_sha=$(docker exec "$db_container" cat /tmp/phase2b-startup-after.sql | sha256sum | cut -d' ' -f1)
test "$before_sha" = "$after_sha"
echo 'Restored database startup preserved all dump contents and migrations 001-014.'
