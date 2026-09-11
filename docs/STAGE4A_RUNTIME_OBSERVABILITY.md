# Stage-4A runtime health and observability

This document records the Stage-4A runtime contract for the single-host Compose
topologies. It does not add an external monitoring service, make a production
availability claim, or change authentication, schema, public presentation, or
the canonical public route (`/p/<slug>`).

## Topology

| Topology | Service | Purpose and ports | Dependencies and persistent state | Startup, shutdown, restart, and logs |
| --- | --- | --- | --- | --- |
| Development | `db` | MySQL; loopback `3308 -> 3306` | Named `portfolio_db_data`; baseline SQL bind mount | Docker MySQL init; `SIGTERM` has a 60-second grace period; no restart policy; Docker logs |
| Development | `web` | Apache/PHP; loopback `8088 -> 80` | Requires healthy `db`; source bind mount and named private storage | Entrypoint validates storage, waits for baseline, and applies locked migrations before Apache; 30-second grace; no restart policy; Apache/PHP stderr and Docker logs |
| Owner HTTPS overlay | `owner_https` | Caddy TLS bridge; loopback `8443 -> 443` | Requires healthy `web`; no persistent state | Caddy is PID 1; 15-second grace; intentional `restart: "no"`; Docker logs |
| Production | `db` | MySQL on the internal Compose network only | Named `portfolio_production_db_data`; immutable baseline SQL bind mount | Docker MySQL init; 60-second grace; `unless-stopped`; bounded Docker JSON logs |
| Production | `web` | Apache/PHP; loopback `8098 -> 80` by default | Requires healthy `db`; named private-media and rate-limit volumes | Entrypoint security gate, baseline wait, and migration sequence finish before Apache runs; 30-second grace; `unless-stopped`; PHP errors to stderr and bounded Docker JSON logs |

No separately containerized production reverse proxy is present in the current
production Compose file. The deployment edge/TLS remains outside this scope.

The migration runner uses MySQL advisory locking. Compose startup ordering avoids
the initial database race but is not treated as permanent dependency recovery:
readiness continues to report dependency loss after startup, and automatically
recovers when a compatible dependency returns.

## Liveness and readiness

`GET` and `HEAD` on `/health.php` are liveness only. A successful response is
exactly `200 OK` with `OK\n`. It starts no database connection, filesystem
probe, migration validation, Auth0 call, or internet request. If Apache/PHP is
not able to process the request, the normal process/container failure is
observable rather than being fabricated as a success.

`GET` and `HEAD` on `/ready.php` are readiness. A successful response remains
exactly `200 OK` with `READY\n`. Readiness fails closed with the sanitized body
`UNAVAILABLE\n` and a non-sensitive `503` whenever any critical dependency is
unavailable or incompatible:

- required database configuration is absent or malformed;
- MySQL cannot complete a `SELECT 1`;
- the complete migration ledger or required post-migration columns do not match
  the checked-in schema contract;
- the configured private-media root is not a writable safe location; or
- required local public-base, session-cookie, or OIDC configuration is missing
  or malformed.

Readiness parses OIDC configuration locally but never contacts Auth0. Neither
endpoint includes connection strings, hostnames, credentials, SQL, filesystem
paths, exception messages, or secrets in its response.

## Container healthchecks

| Service | Command | Timing and purpose |
| --- | --- | --- |
| Development `web` | `php -r 'file_get_contents("http://127.0.0.1/public/health.php")'` and exact `OK\n` check | 10s interval, 3s timeout, 3 retries, 30s start period; local process liveness |
| Production `web` | `php -r 'file_get_contents("http://127.0.0.1/health.php")'` and exact `OK\n` check | 10s interval, 3s timeout, 3 retries, 30s start period; production process liveness |
| Owner HTTPS Caddy | local `curl --fail --silent --show-error --insecure --resolve localhost:443:127.0.0.1 https://localhost/health.php` | 10s interval, 3s timeout, 3 retries, 15s start period; verifies the local TLS proxy and upstream |
| MySQL | `MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h localhost -u root --silent` | 5s interval, 5s timeout, 20 retries, 20s start period; database process availability |

The selected PHP and Caddy `curl` clients were confirmed in the corresponding
images. Checks run only in container namespaces, use no public Auth0 endpoint,
do not write data, and use liveness rather than readiness to avoid coupling
container health to temporary database loss.

## Structured application events and correlation

Application-controlled events are JSON Lines on the PHP/Apache container-safe
error destination (stderr in production). The stable fields, where applicable,
are `timestamp`, `level`, `event`, `request_id`, `method`, route category,
`status`, `duration_ms`, `outcome`, and sanitized `reason`. Security and
application-error events retain their existing safe numeric/category metadata
and now include the request ID when a request context exists.

Each application request receives a generated 32-character lowercase-hex
identifier (`[a-f0-9]{32}`) and the `X-Request-ID` response header. The service
does not accept inbound request-ID, forwarded, or proxy headers because no
trusted-proxy boundary is configured. IDs are never authentication or
authorization inputs. One completion event is emitted per application request;
expected 4xx responses are classified as `client_rejection`, while 5xx responses
are `server_error`. Successful liveness/readiness probes are suppressed to keep
normal logs quiet; unsuccessful probes retain a completion event.

Events never include passwords, credentials, Auth0 artifacts, cookies, session
identifiers, CSRF values, complete bodies, raw connection strings, exception
messages, private storage paths, or raw query strings. Existing rate-limit
events retain their sanitized scope and reason metadata.

## Operational boundaries

Stage-4A is limited to runtime health, startup/shutdown behavior, correlation,
and local structured observability. Stage-4B covers Edge Security, CSP, and
Abuse Protection. Stage-4C covers Backup, Recovery,
vulnerability/operational-security closure, and monitoring readiness. Stage-5
owns the actual production domain, DNS, TLS, Auth0 production configuration,
deployment, production acceptance, rollback, and explicitly authorized
merge/push/tag.
