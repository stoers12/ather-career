# Stage-4C recovery and operational-security closure

This document defines the repository-supported recovery contract. It is not a
claim that a production backup, recovery environment, vendor account, or
certificate deployment exists. Stage-5 owns release-specific image provenance,
real infrastructure, encrypted backup storage, TLS edge operation, and a
production change approval.

## Scope and state classification

| State | Classification | Recovery treatment |
| --- | --- | --- |
| MySQL schema, application data, and `schema_migrations` ledger | Must back up | Transaction-consistent logical dump. |
| Recognized managed media: profile originals/presentations and project originals/presentations | Must back up | Archive only validated managed keys and retain original bytes. |
| Git source and immutable production image | Rebuildable/provenance | Recover through the recorded commit and image identity, not the data archive. |
| Generated presentation derivatives | Included with managed media | They are recoverable from originals but included to preserve immediate public rendering. |
| Rate-limit files, PHP sessions, temporary quota locks, runtime logs, caches, and temporary uploads | Ephemeral; must not restore | Excluded from every archive and never recreated by restore. |
| Environment files, database credentials, Auth0 configuration, tokens, keys, and certificates | Separate secure recovery | Never appear in archive payloads, manifests, reports, or command-line arguments. |
| Monitoring vendor, DNS, TLS certificates, external backup storage, and production deployment configuration | Deployment-specific | Deferred to Stage-5. |

An empty unrecognized media artifact is not part of recognized managed media.
It is ignored by backup and is neither deleted nor quarantined automatically.
Quarantine or removal requires a separately approved maintenance action after
ownership and retention review. Transient `.quota.lock` files are likewise
counted as excluded operational state, not backup content.

## Backup format v1

`scripts/stage4c-recovery.php backup` creates one atomically finalized
`stage4c-<UTC>-<random>` directory beneath an explicit operator-provided output
directory. It contains only:

- `manifest.json` — format `ather-career-stage4c-backup`, version `1`;
- one logical MySQL dump, optionally recipient-encrypted;
- one managed-media tar.gz archive, optionally recipient-encrypted; and
- `media-manifest.json`, containing deterministic relative managed keys,
  byte sizes, and SHA-256 checksums.

The top-level manifest contains safe compatibility metadata: UTC creation time,
application commit and optional image identity, database family/version and
charset, migration-ledger count/hash/high-water mark, critical counts, artifact
names/sizes/SHA-256 values, recognized-media count/content-manifest hash,
backup mode, encryption state, and tool versions. It deliberately excludes
passwords, DSNs, Auth0 settings, tokens, cookies, raw messages, raw IPs, and
absolute private filesystem paths.

The dump uses `mysqldump --single-transaction --skip-lock-tables --routines
--events --triggers --set-charset --default-character-set=utf8mb4 --hex-blob
--no-tablespaces`. The MySQL password is read only from a named already-managed
environment variable (or a separately provisioned MySQL defaults file for the
client); it is not serialized or printed.

The tool writes into a generated incomplete directory, validates non-zero
output and checksums, then renames it atomically. A command failure removes
that generated incomplete backup directory. It never creates a default backup
under the repository or selects a Compose project on the operator's behalf.

## Consistency boundary

MySQL and a filesystem do not share a transaction. Production backup therefore
requires an operator-entered quiesced-state confirmation during a controlled
read-only/maintenance window after mutation traffic has been removed. The tool
does not stop web or database services. It records media and database-reference
snapshots before capture, captures the consistent database dump and media
archive, then repeats both snapshots. If a recognized media byte/path or a
database media reference changed, it aborts rather than presenting a mixed pair
as successful. Every database reference must exist in the archived managed
media set. Unreferenced recognized files are recorded as a count and retained;
they are never deleted by backup.

Residual risk is limited to a database/media change made despite the declared
quiesced window. A production operator must investigate that refusal and repeat
the maintenance window; the tool must not silently retry against live mutation
traffic.

## Encryption and retention

Production mode fails closed unless either:

1. `age` or GPG is already installed and an explicit recipient is supplied; or
2. the operator explicitly declares an independently managed encrypted storage
   layer with its own access control, encryption, retention, and restore
   evidence.

No encryption package is installed by this repository. Private keys and
passphrases are never accepted as command arguments, repository files, manifest
fields, or logs. Disposable rehearsal output may be unencrypted only with both
`--mode disposable-rehearsal` and `--allow-unencrypted-rehearsal`; production
restore rejects that artifact.

Retention is an operator policy. `retention-plan` requires an explicit resolved
backup directory, a recognized v1 manifest, a positive minimum retention count,
and `--dry-run`; it only lists candidates. It refuses broad roots, home, and the
repository root. It does not delete unknown directories and Stage-4C performs
no retention deletion.

## Safe backup operation

Run this only from an operational toolbox or the production image, with an
explicit new backup output directory outside application storage. The image
contains the MySQL client solely for the backup/restore operation; it does not
contain a scanner, browser, or source-control credential.

The wrapper already selects `backup`; do not include the word `backup` twice.
An illustrative production invocation is:

```sh
scripts/backup-production.sh \
  --mode production --output-dir /operator-approved/encrypted-backups \
  --media-root /operator-approved/private-media --db-host db --db-port 3306 \
  --db-name portfolio_data --db-user backup_user \
  --db-password-env STAGE4C_DB_PASSWORD \
  --quiesced-confirmation I_CONFIRM_QUIESCED_STAGE4C \
  --encrypt age --recipient <approved-recipient>
```

Use a separate secure channel to recover secret configuration. Do not copy a
certificate, `.env` file, rate-limit directory, PHP session directory, or
container logs into a data backup.

## Restore safety

`scripts/restore-production.sh` delegates to the same verifier. It checks the
manifest format/version, directory allow-list, artifact sizes/checksums,
media-manifest checksums, deterministic managed-key grammar, duplicate and
unrecognized archive members, symlinks, traversal, and plaintext checksums
after decryption. It never restores sessions, rate-limit state, logs, locks,
or test cookies.

Restore defaults to a new approved disposable database namespace matching
`ather_stage4c_restore_<24 lowercase hex>` or
`ather_career_restore_<24 lowercase hex>` and a new empty explicit media
directory. It refuses a non-empty target, a database target with tables or
active connections, an unexpected database name, and any implicit resident
target. It imports only after archive validation and does not automatically
cut over traffic. It validates migration ledger, critical counts, database
media references, and restored media SHA-256 entries before media is finalized.
An interrupted disposable restore remains marked incomplete for inspection;
there is no live cutover to roll back.

Production-mode restore additionally requires a separate explicit authorization
for a new target and rejects unencrypted rehearsal artifacts. In-place restore,
volume replacement, service stopping, and DNS cutover are intentionally
outside this tool and require an approved Stage-5 change procedure.

## Disaster-recovery rehearsal

The stable `scripts/run-stage4c-dr-rehearsal.ps1` runner creates a named,
loopback-only disposable production-like source stack and a distinct restore
stack. It uses `scripts/stage4c-dr-fixture.php`, which refuses any namespace
other than an exact generated source `ather_stage4c_dr_<24 lowercase hex>` or
restore `ather_stage4c_restore_<24 lowercase hex>` name.
The fixture creates one active synthetic Owner, a complete profile and contact
configuration, one published Portfolio, at least seven projects, five skills,
four experiences, one message, profile/project originals and presentation
derivatives, and a valid migration ledger. The runner captures counts and the
content-only managed-media manifest, declares the synthetic quiesced boundary,
backs up, destroys only source disposable volumes, restores into distinct empty
volumes/database, and checks public, JSON, media, private-denial, and Owner
session behavior. Its measurements are local rehearsal RPO/RTO evidence only,
not production guarantees.

The deterministic negative runner covers truncated/modified dumps, modified
media, missing archive/referenced media, extra and traversal archive entries,
escaping symlinks, unsupported manifests, incompatible migrations, non-empty
and wrong/running targets, interrupted staging, production rejection of
unencrypted rehearsal output, and missing encryption recipients. It uses only
temporary disposable directories.

## Container hardening posture

Development, production, and local Owner HTTPS Compose services set
`no-new-privileges:true`. The Compose definitions use loopback host bindings,
do not request privileged mode, host PID/network/IPC, a Docker socket, or a
host-root mount. Production web data and rate-limit volumes are named volumes;
the database init SQL and Caddy configuration are read-only bind mounts where
applicable. JSON log rotation and graceful stop periods are configured.

The production Apache image intentionally retains a root Apache master to bind
and manage the standard Apache lifecycle before worker privilege drop; this is
an accepted residual privilege, not a claim of a non-root master. Read-only
root filesystem and wholesale capability dropping were assessed but are not
claimed because entrypoint ownership repair, Apache runtime files, PHP sessions,
and migration/bootstrap behavior require a separately demonstrated compatible
mount and privilege plan. Stage-5 must reassess them against the immutable
release image. MySQL initialization also remains writable by design on its
dedicated named data volume.

## Dependency, image, and secret assessment process

Use only already available local tools. Run Composer lock validation/audit in
the built image when Composer is present, Docker Scout local image analysis when
available, package and extension inventory, image history/config inspection,
and a tracked-source secret-pattern scan that reports paths/counts rather than
values. Do not upload source, images, SBOMs, or secrets. Record scanner version
and vulnerability database freshness; if a database cannot refresh, report the
limitation rather than treating the scan as current.

Any critical/high production-image finding that is exploitable in this service
and lacks a tested mitigation blocks closure until explicit risk acceptance.
Stage-5 must generate a release-specific SBOM and image scan for the immutable
release image, confirm base-image provenance/digest/update procedure, and make
the final vulnerability disposition.

## Monitoring signals

Monitoring is vendor-neutral and must use structured logs, health/readiness
endpoints, Docker/container state, and backup/rehearsal results. Safe labels
are route class, HTTP status family, sanitized reason, deployment/image ID,
environment, and aggregate storage bucket. Never label a metric with raw IP,
cookie/session ID, token, authorization code, message text, or private path.
Apache access records deliberately retain only timestamp, method/protocol,
status, and byte count; they omit peer addresses and request targets. Use the
correlated, sanitized application route class rather than raw URLs for
investigation.

| Signal | Source / safe labels | Threshold guidance and severity | Action / limitation |
| --- | --- | --- | --- |
| Liveness availability | `/health.php`; environment | Two failed probes in 2 minutes: warning; sustained 5 minutes: critical | Runbook: container crash loop; does not prove DB health. |
| Readiness failures | `/ready.php`, sanitized reason | Any sustained 2 minutes: critical | Runbook: readiness/database/storage; no secrets in reason. |
| HTTP 5xx and latency | JSON logs; route class/status family | >1% 5xx or p95 above service SLO for 10 minutes: warning/critical | Runbook: elevated 5xx; latency needs a collector. |
| 403/429 and limiter denials | JSON security events; limiter scope | Material baseline deviation for 10 minutes: warning | Runbook: abuse; do not retain client identifiers. |
| OIDC, contact, upload, publication denials | Sanitized security event and scope | Sudden sustained rise: warning | Runbook: Auth0/abuse; external Auth0 availability is not measured here. |
| Database connectivity | readiness + container health | Any readiness DB failure: critical | Runbook: database unavailable. |
| Storage capacity/writability | readiness/storage probe + host volume telemetry | Warning at operator-defined 80%; critical at 90% or any write failure | Runbook: storage; capacity requires platform telemetry. |
| Container restart/health | Docker state; service/image | restart count increase or unhealthy: warning/critical | Runbook: crash loop. |
| Backup result and age | manifest/result metadata; no file paths | failed backup: critical; age beyond RPO: critical | Runbook: backup failure/checksum. |
| Restore rehearsal age | controlled rehearsal record | beyond recovery-policy interval: warning/critical | Runbook: restore validation. |
| Media-reference mismatch | backup/rehearsal validator count only | any mismatch: critical | Runbook: missing/corrupt media. |
| Certificate expiry | real Stage-5 edge only | platform policy | Deferred; local Caddy is not production certificate evidence. |

## Incident runbooks

Every incident begins with the current change record, UTC time, image/commit
identity, sanitized readiness reason, aggregate container state, and only
approved logs. Never collect or paste credentials, tokens, cookies, message
contents, raw IPs, or private paths into tickets.

| Incident | Detection and containment | Safe evidence / recovery / validation | Escalation and approval |
| --- | --- | --- | --- |
| Database unavailable | Readiness DB failure or unhealthy DB; remove mutation traffic if approved | Record health/restarts, connection reason, disk aggregate; repair platform DB, then readiness/migrations/counts | DBA/platform on-call; failover/restore needs explicit change approval. |
| Readiness failure | `/ready.php` sanitized reason | Identify DB/storage/migration class, preserve aggregate evidence, correct bounded cause, recheck readiness | Platform owner; data changes require approval. |
| Storage unwritable/full | Storage readiness or upload errors | Record capacity bucket and volume state, halt uploads if needed, add approved capacity, recheck a disposable write path | Storage/platform owner; cleanup of data requires explicit approval. |
| Elevated 5xx | 5xx trend/latency alert | Rate-limit risky traffic if appropriate, correlate safe request IDs, roll back only approved release | Incident commander; Stage-5 rollback approval. |
| Elevated 429/abuse | Limiter/security-event trend | Preserve aggregate counters, tune only after review, do not reset rate state as a first action | Security/on-call; policy change approval. |
| Auth0 outage/misconfiguration | OIDC safe failure/denial trend | Validate configuration presence without values, dependency reachability through approved tooling, retain existing sessions | Identity owner; credential rotation/change approval. |
| Suspected credential compromise | Security report or anomalous access | Contain affected integration, preserve safe audit metadata, rotate through secret manager | Security lead; credential revocation/deployment approval. |
| Backup failure | Failed job/manifest absence | Preserve sanitized error and output state, verify target/quiescence/tool availability, repeat only in controlled window | Backup owner; retention or storage change approval. |
| Backup checksum failure | Verify failure | Quarantine the artifact, do not restore it, verify storage transport, create a new backup | Backup/security owner; deletion requires approval. |
| Restore validation failure | Rehearsal mismatch | Do not cut over, preserve disposable target evidence, compare ledger/count/hash only, correct tooling or input | DR owner; any production restore needs explicit approval. |
| Missing/corrupt media | Reference mismatch/public derivative failure | Stop destructive cleanup, inventory categories/hashes, recover into new target, validate authorization | Data owner/security; replacement/cutover approval. |
| Container crash loop | Restart/health alert | Capture image ID, exit code, safe logs, config presence flags; fix bounded defect and test disposable stack | Platform owner; deployment approval. |
| CSP regression | Browser/report or header contract failure | Preserve route/status/header names, roll back approved image if needed, rerun header matrix | Security/frontend owner; release approval. |
| Vulnerable dependency/image | Scanner finding | Record identifier/component/severity/fix/reachability, isolate if exploited, patch only tested scope | Security lead; risk acceptance or release approval. |
| Rollback handoff | Failed release validation | Use immutable prior image and verified data compatibility; do not overwrite data blindly | Stage-5 incident/change authority only. |

## Stage-5 handoff

Stage-5 may begin only after this repository's disposable rehearsal and
security gates pass. It must choose encrypted backup storage and retention
ownership, provision real secret recovery, configure DNS/TLS/Auth0/monitoring,
produce a release-specific SBOM and current image scan, approve a production
maintenance window, validate immutable image provenance, and own any production
restore/cutover or rollback decision.
