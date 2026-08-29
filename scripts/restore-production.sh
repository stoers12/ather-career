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
uploads_archive="$backup_dir/uploads.tar.gz"
manifest="$backup_dir/manifest.json"
[ -f "$database_dump" ] && [ -f "$uploads_archive" ] && [ -f "$manifest" ] || { echo "Backup files are incomplete." >&2; exit 1; }

expected_database_sha=$(sed -n 's/.*"database_dump_sha256": "\([0-9a-f]*\)".*/\1/p' "$manifest")
expected_uploads_sha=$(sed -n 's/.*"uploads_archive_sha256": "\([0-9a-f]*\)".*/\1/p' "$manifest")
[ "$(sha256sum "$database_dump" | awk '{print $1}')" = "$expected_database_sha" ] || { echo "Database dump checksum mismatch." >&2; exit 1; }
[ "$(sha256sum "$uploads_archive" | awk '{print $1}')" = "$expected_uploads_sha" ] || { echo "Uploads archive checksum mismatch." >&2; exit 1; }

compose() {
    docker compose -p "$project_name" -f "$compose_file" "$@"
}

if [ -z "$(compose ps -q db)" ]; then
    echo "The target db service must be running before restore." >&2
    exit 1
fi

echo "DESTRUCTIVE: this replaces the target database and all managed uploads from $backup_dir." >&2
compose stop web || true
compose exec -T db sh -lc 'set -eu; MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"'
compose exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot "$MYSQL_DATABASE"' < "$database_dump"
cat "$uploads_archive" | compose run --rm --no-deps -T --entrypoint sh web -c 'set -eu; target=/var/www/app/uploads; [ "$target" = /var/www/app/uploads ] || exit 1; staging=$(mktemp -d); trap "rm -rf \"$staging\"" EXIT; tar -xzf - -C "$staging"; find "$target" -mindepth 1 -maxdepth 1 -exec rm -rf {} +; cp -a "$staging"/. "$target"/; chown -R www-data:www-data "$target"'
compose up -d web
echo "Restore completed. Verify migration ledger, /health.php, /api/projects.php, and managed upload paths before reopening mutations." >&2
