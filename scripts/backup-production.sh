#!/bin/sh
set -eu
umask 077

project_name="portfolio_course_production"
compose_file="docker-compose.production.yml"
backup_root="backups"
app_version="${APP_VERSION:-unknown}"

usage() {
    echo "Usage: $0 [--project NAME] [--compose-file FILE] [--backup-root DIR] [--app-version SHA]" >&2
    exit 2
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --project) project_name=${2:?missing project name}; shift 2 ;;
        --compose-file) compose_file=${2:?missing compose file}; shift 2 ;;
        --backup-root) backup_root=${2:?missing backup root}; shift 2 ;;
        --app-version) app_version=${2:?missing application version}; shift 2 ;;
        *) usage ;;
    esac
done

compose() {
    docker compose -p "$project_name" -f "$compose_file" "$@"
}

timestamp=$(date -u +%Y%m%dT%H%M%SZ)
backup_dir="$backup_root/$timestamp"
mkdir -p "$backup_dir"

if [ -z "$(compose ps -q db)" ] || [ -z "$(compose ps -q web)" ]; then
    echo "Both running db and web services are required for a coordinated backup." >&2
    exit 1
fi

echo "Create this backup during a short read-only/maintenance window: DB and uploads are captured sequentially." >&2
compose exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -h 127.0.0.1 -uroot "$MYSQL_DATABASE"' > "$backup_dir/database.sql"
compose exec -T web tar -C /var/www/app/uploads -czf - . > "$backup_dir/uploads.tar.gz"

database_sha=$(sha256sum "$backup_dir/database.sql" | awk '{print $1}')
uploads_sha=$(sha256sum "$backup_dir/uploads.tar.gz" | awk '{print $1}')

cat > "$backup_dir/manifest.json" <<EOF
{
  "created_at_utc": "$timestamp",
  "compose_project": "$project_name",
  "app_version": "$app_version",
  "database_dump_sha256": "$database_sha",
  "uploads_archive_sha256": "$uploads_sha"
}
EOF

echo "Backup created: $backup_dir" >&2
