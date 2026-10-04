# 08 — Operations, rollback and recovery

**Future runbook, not an instruction to act now.** This documentation branch performs no backup, migration, tenant mutation, service action or deployment. Any live operation requires an owner-approved phase plan and maintenance window. Store all recovery artifacts outside Git in access-controlled storage. Use placeholders named RECOVERY_STORE, DATABASE_COPY and DEPLOYMENT_REF; never paste credentials, tokens, personal rows or live absolute backup paths here. See [migrations](04-DATA-MODEL-AND-MIGRATIONS.md) and [tests](07-TEST-AND-ACCEPTANCE-MATRIX.md).

## Preflight and recovery artifacts

1. Verify canonical repository path, clean Git tree, branch/HEAD/parent, upstream and remote refs without fetching; record reviewed commit and expected file scope.
2. Read-only inspect ordered migration ledger, schema, sanitized table counts, account-to-portfolio edges and protected project/Evidence/image/technology fingerprints. Record expected 12/12 Evidence, 4/4 completed projects, 27 technology occurrences, 24 distinct mapped and zero unmapped only after fresh SELECT confirmation.
3. Inspect service identity, health, loopback ports, networks, mounts, protected volumes and image digests using read-only Docker API access. An open port proves reachability, **not** container identity or preservation. If access is denied, record the exact limitation and stop identity claims.
4. Before any live write, create a protected recovery Git ref and Git bundle for the exact reviewed code, a logical database backup, and a sanitized manifest of schema/ledger/counts/fingerprints plus checksum files. Protect Docker volumes and image assets; determine whether logical backup covers media or whether separate volume snapshots are required. Record restore owner, location class, retention and access controls outside Git.
5. Prove restore of the logical backup and required media artifacts on an isolated copy. Verify checksums and protected aggregates. A backup without restore evidence is not a recovery point.
6. Capture Auth0 non-production configuration/versioned change record without secrets or tenant-sensitive IDs; define how to revert settings independently of app deployment. Never infer tenant state from repository config alone.

## Rehearsal and maintenance window

Rehearse each additive migration on (a) a fresh database and (b) a sanitized/upgraded copy at 001–013. Run twice for idempotence; inject duplicate issuer/subject, uniqueness race, stale draft, slug conflict and partial MySQL DDL. Confirm indexes, FKs, defaults, ledger and fingerprints. Use a feature-disabled application version compatible with both pre/post schema. Define a maintenance window, decision owner, communications, service stop/start authority, expected downtime and explicit abort thresholds **before** any production cutover. This runbook does not authorize stopping or restarting current services.

## Future deployment order

1. Obtain owner phase acceptance and latest legal/tenant gates. Capture preflight manifest and proven recovery point.
2. Keep registration/linking/publication feature flags off. Apply reviewed additive schema in the authorized window; verify ledger and row/ownership fingerprints immediately.
3. Deploy compatibility application, then guarded security behavior, then UI, linking and publication slices in their approved phases. A schema migration alone never enables an external journey.
4. Validate authentication callbacks, restricted verification, old Owner route gates, session rotation/logout, role MFA, draft privacy, public HTML/JSON/media and protected aggregates using disposable/synthetic accounts and SELECT-only canonical inspections where practical.
5. Enable cohorts only after [Phase 7](06-IMPLEMENTATION-PLAN.md) gates. Monitor safe event-code rates for callbacks, verification loops, switches, draft conflicts and access denials; never log request bodies or raw identities.

## Rollback triggers and order

Trigger a stop on failed migration/partial DDL, identity uniqueness conflict, owner/portfolio fingerprint mismatch, verification or privileged-MFA bypass, private preview/media/Evidence leak, authentication outage, unusable Arabic/English accessibility path, or unavailable restore. First disable new registration, linking or publication flags and halt writes where authorized; preserve incident evidence without secrets. Then assess application/schema compatibility. Revert application code to the protected Git ref only if compatible with the additive schema; otherwise use a reviewed forward fix. Do **not** automatically drop columns after MySQL partial DDL. Restore database and media from protected artifacts only under an owner-approved incident procedure, with a verified consistency point and documented handling of legitimate writes after that point. Revert Auth0 changes from the non-production-proven configuration record separately; distinguish local, Auth0 and provider-session effects. Service stop/start must be executed only by the authorized operator in the approved window.

## Incident evidence and final reconciliation

Record time, approved phase, code ref, migration ledger, sanitized schema/count/fingerprint manifest, container image/network/mount/volume/port identifiers if accessible, feature-flag state, safe event codes and affected route classes. Keep full backups, secrets and incident private data outside Git. After recovery or deployment, compare pre/post Git refs, ledger, ownership edges, all protected content/image fingerprints, Evidence and technology aggregates, publication flags, port reachability and Docker identities where API access exists. Note unavailable checks explicitly. The separate five-record reconciliation requires its own backup, mapping, owner approval and post-write fingerprint runbook; never merge by email.
