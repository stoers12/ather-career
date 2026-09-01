# Production runbook

This runbook is for the current single-host Docker Compose deployment. It does not provide TLS; public launch requires a real domain/DNS and a trusted HTTPS reverse-proxy or cloud edge. Enable HSTS only at that verified HTTPS edge.

## Prerequisites and configuration

- Docker Engine with Compose v2 and host access restricted to operators.
- Copy `.env.example` to a host-only `.env`; set unique database credentials, admin credentials, and `SESSION_COOKIE_SECURE=true`.
- Keep `.env` and `backups/` outside Git and outside the public document root.
- Use a short read-only/maintenance window for backup, migration, and restore operations.

## Release an existing deployment

Run these commands from the repository checkout. Replace the version with the commit being released.

```sh
export APP_VERSION="$(git rev-parse HEAD)"
docker compose -f docker-compose.production.yml build --build-arg APP_VERSION="$APP_VERSION" web
docker compose -f docker-compose.production.yml up -d db
docker compose -f docker-compose.production.yml ps
./scripts/backup-production.sh --app-version "$APP_VERSION"
docker compose -f docker-compose.production.yml run --rm --no-deps web php database/migrate.php
docker compose -f docker-compose.production.yml run --rm --no-deps web php database/migrate.php
docker compose -f docker-compose.production.yml exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot "$MYSQL_DATABASE" -e "SELECT version, name, applied_at FROM schema_migrations ORDER BY version"'
docker compose -f docker-compose.production.yml up -d --force-recreate web
curl -fsS http://127.0.0.1:8098/health.php
curl -fsS http://127.0.0.1:8098/api/projects.php
docker compose -f docker-compose.production.yml logs --tail 100 web db
```

The web healthcheck is liveness only: `GET /health.php` returns `OK` without a DB query. Use `GET /api/projects.php` as the DB-backed readiness signal: `200` is ready and `503` means the DB dependency is unavailable.

## First deployment / empty volumes

`docker compose up -d db` initializes `database/portfolio_db.sql` on an empty MySQL volume. It creates the baseline tables but does not create the migration ledger or apply later migrations. Before starting web for the first time, run the two migration commands above: the first adopts `001_baseline` and applies `002_integrity_constraints`; the second must print `No pending migrations.`

## Backup and restore

`scripts/backup-production.sh` creates timestamped `database.sql`, `private-storage.tar.gz`, and `manifest.json` under ignored `backups/`. The manifest binds both checksums into one recovery-pair identifier. It captures MySQL and private managed media sequentially, so avoid mutations during the short backup window. Rate-limit state, PHP sessions, and container logs are intentionally excluded.

Restore is destructive and requires explicit confirmation:

```sh
./scripts/restore-production.sh --backup-dir backups/20260101T000000Z --confirm-restore
```

It stops web, replaces the selected MySQL database and managed uploads, then starts web. Validate the migration ledger, `/health.php`, `/api/projects.php`, and referenced uploads before reopening mutations. Backup artifacts contain user data and require host-level access control.

## Failure and rollback

- If migration fails, stop the release. Inspect the logged migration error and ledger; do not start new web code blindly or edit the ledger manually.
- If migration succeeds but new web fails, start the previous application image/commit. Migration `002` is additive and backward-compatible with pre-Stage-5 application SQL.
- `docker compose stop`/`start` and normal container recreation preserve named volumes. **`docker compose down -v` deletes DB, upload, and rate-limit volumes and is destructive.**

Admin sessions are container-local and may be lost when web is recreated. Minimum operating checks are web liveness, API readiness, `docker compose ps`, host disk capacity, Docker log growth, and successful backup completion.
