#!/bin/sh
set -eu
umask 077

project_name="portfolio_course_production"
compose_file="docker-compose.production.yml"
backup_dir=""
confirmed=false

usage() {
    echo "Usage: $0 --backup-dir DIR --confirm-restore [--project NAME] [--compose-file FILE]" >&2
    exit 2
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --backup-dir) backup_dir=${2:?missing backup directory}; shift 2 ;;
        --project) project_name=${2:?missing project name}; shift 2 ;;
        --compose-file) compose_file=${2:?missing compose file}; shift 2 ;;
        --confirm-restore) confirmed=true; shift ;;
        *) usage ;;
    esac
done

[ "$confirmed" = true ] && [ -n "$backup_dir" ] || usage
database_dump="$backup_dir/database.sql"
storage_archive="$backup_dir/private-storage.tar.gz"
manifest="$backup_dir/manifest.json"
[ -f "$database_dump" ] && [ -f "$storage_archive" ] && [ -f "$manifest" ] || { echo "Backup files are incomplete." >&2; exit 1; }

expected_database_sha=$(sed -n 's/.*"database_dump_sha256": "\([0-9a-f]*\)".*/\1/p' "$manifest")
expected_storage_sha=$(sed -n 's/.*"private_storage_sha256": "\([0-9a-f]*\)".*/\1/p' "$manifest")
expected_pair_id=$(sed -n 's/.*"recovery_pair_id": "\([0-9a-f]*\)".*/\1/p' "$manifest")
[ "$(sha256sum "$database_dump" | awk '{print $1}')" = "$expected_database_sha" ] || { echo "Database dump checksum mismatch." >&2; exit 1; }
[ "$(sha256sum "$storage_archive" | awk '{print $1}')" = "$expected_storage_sha" ] || { echo "Private-storage archive checksum mismatch." >&2; exit 1; }
created_at=$(sed -n 's/.*"created_at_utc": "\([^"]*\)".*/\1/p' "$manifest")
actual_pair_id=$(printf '%s\0%s\0%s' "$created_at" "$expected_database_sha" "$expected_storage_sha" | sha256sum | awk '{print $1}')
[ -n "$expected_pair_id" ] && [ "$actual_pair_id" = "$expected_pair_id" ] || { echo "Recovery-pair manifest mismatch." >&2; exit 1; }

compose() {
    docker compose -p "$project_name" -f "$compose_file" "$@"
}

if [ -z "$(compose ps -q db)" ]; then
    echo "The target db service must be running before restore." >&2
    exit 1
fi

echo "DESTRUCTIVE: this replaces the target database and all private managed media from $backup_dir." >&2
compose stop web || true
compose exec -T db sh -lc 'set -eu; MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"'
compose exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot "$MYSQL_DATABASE"' < "$database_dump"
cat "$storage_archive" | compose run --rm --no-deps -T --entrypoint sh web -c 'set -eu; target="$ATHERCAR_STORAGE_ROOT"; [ -n "$target" ] && [ "$target" != / ] || exit 1; staging=$(mktemp -d); trap "rm -rf \"$staging\"" EXIT; tar -xzf - -C "$staging"; find "$target" -mindepth 1 -maxdepth 1 -exec rm -rf {} +; cp -a "$staging"/. "$target"/; chown -R www-data:www-data "$target"'
compose up -d web
echo "Restore completed. Verify migration ledger, ownership, public routes, messages, and managed media before reopening mutations." >&2
