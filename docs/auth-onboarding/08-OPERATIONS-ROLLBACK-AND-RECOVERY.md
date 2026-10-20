# 08 — Operations, rollback and recovery

Completion UI recovery: [31 — private final review and completion UI](31-COMPLETION-EDITOR-UI-HANDOFF.md) adds no deployment/config change. All actual gates remain off and production policies null. Unconfirmed completion retains its exact serialized intent only in RAM and offers explicit authorized receipt GET, with no automatic POST retry/new intent/deletion. Null recovery is not proof an earlier request cannot finish. Reload/later sessions recover from durable evidence without browser-stored tokens. No routing/launch/publication or legal retention decision is introduced.

Completion transport operations: [30 — completion HTTP transport](30-COMPLETION-HTTP-HANDOFF.md). `ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED` is independently default `0` in both Compose files and `.env.example`; only exact `1` enables admission. All actual environments stay off. No migration is added. For an unconfirmed/lost confirmation response explicitly GET the authenticated completion receipt; do not automatically replay, delete possible committed data or generate a replacement intent. Null production policies still block new completion; authorized recovery remains independent. No rollout/live operation is authorized.

UI recovery boundary: [29 — private editor attestation UI](29-ATTESTATION-EDITOR-UI-HANDOFF.md). Keep all actual gates off and both production document resolvers null. Off/unavailable attestation presentation leaves draft editing/saving operational. After an uncertain POST, explicitly re-read current evidence and its original timestamp before deciding a next action; no automatic replay or assertion that nothing committed. Stale presentations clear actions/documents and require fresh loading/review. Authentication changes stop both protected boundaries and require an explicit fresh page; RAM-only edits do not survive a forced reload. No deployment/live migration/activation is part of this slice.

**Card 04 HTTP boundary (2026-10-19):** [28](28-ATTESTATION-HTTP-HANDOFF.md) adds only a
default-off capability; actual environments remain off and no migration is added.
Even a syntactically enabled production route cannot present/record without approved
current descriptors AND matching fixed bytes; both resolvers stay null. Roll back
future availability by disabling the route, never by deleting evidence. On dependency
failure, evidence may already be committed: use a fresh authorized GET on a working
connection/current bundle, inspect exact status/time, then explicitly choose next
action. No automatic retry, compensation, policy activation or existing-owner gate.

**2026-10-19 bounded first-completion core:** 019 is additive and not applied live. Code requires the full verified schema; no activation/deployment is authorized. Interrupted whole CREATE is recoverable only if empty/exact; partial or populated unrecorded tables require review. Unknown COMMIT may have committed: use authorized durable receipt recovery on a working connection; never delete potentially committed rows. Retention/deletion remains a launch decision. See [handoff 27](27-FIRST-COMPLETION-CORE-HANDOFF.md). No HTTP/UI/routing/publication or live change.

**2026-10-19 local final facts:** 018 is additive and unapplied live. The four Profile
controls/read queries require its verified schema/full ledger; never deploy against an
older schema. A completed compatible ALTER can be verified/recorded on retry; partial or
incompatible shapes stop for review. Do not drop columns, backfill drafts/provider values,
reset profile versions or force a catalog replacement to recover. Preserve all stored
facts; prefer a reviewed forward repair. Older-code rollback requires checking its ledger/
schema compatibility, not destructive schema downgrades. Catalog updates are deliberate
offline reviewed revisions with notices/hashes and preserved unchanged retired values.
No downloader/cleanup service/activation is added. [Handoff 24](24-FINAL-PROFILE-FIELDS-HANDOFF.md).

**2026-10-18 local profile-version slice:** 017 is additive and unapplied live.
Do not deploy the new profile writers against pre-017 schema; normal migration
verification/readiness must pass first. Old forms without a version require
explicit reload. Do not roll back by resetting counters or replaying stale input.
After uncertain media commit/rollback acknowledgement, retain potentially referenced
files and inspect authorized current state under the existing recovery process;
there is no automatic cleanup/deletion job. See [handoff 23](23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md).

**2026-10-05 status update:** Migration 014 is already deployed with ledger 001–014. The local Phase 2A callback candidate is not intentionally deployed. A canonical bind mount briefly exposed it during local branch work; maintenance restored the mounted checkout to approved main. Its quiesced recovery boundary and the danger of blindly restoring the old auto-creating callback are in [10](10-PHASE2A-CALLBACK-RESOLUTION.md). The original Phase 1 runbook below is historical.

**Historical Phase 1 runbook; not an instruction to rerun 014.** Its initial rehearsal used disposable fresh and upgraded databases. A later protected maintenance task deployed 014; the canonical ledger is now 001–014. This documentation correction performs no live migration or tenant mutation. The bind-mount service maintenance is recorded below. Any live operation requires an owner-approved phase plan and maintenance window. Store recovery artifacts outside Git in access-controlled storage. Use placeholders named RECOVERY_STORE, DATABASE_COPY and DEPLOYMENT_REF; never paste credentials, tokens, personal rows or live absolute backup paths here. See [migrations](04-DATA-MODEL-AND-MIGRATIONS.md) and [tests](07-TEST-AND-ACCEPTANCE-MATRIX.md).

## Preflight and recovery artifacts

1. Verify canonical repository path, clean Git tree, branch/HEAD/parent, upstream and remote refs without fetching; record reviewed commit and expected file scope.
2. Read-only inspect ordered migration ledger, schema, sanitized table counts, account-to-portfolio edges and protected project/Evidence/image/technology fingerprints. Record expected 12/12 Evidence, 4/4 completed projects, 27 technology occurrences, 24 distinct mapped and zero unmapped only after fresh SELECT confirmation.
3. Inspect service identity, health, loopback ports, networks, mounts, protected volumes and image digests using read-only Docker API access. An open port proves reachability, **not** container identity or preservation. If access is denied, record the exact limitation and stop identity claims.
4. Before any live write, create a protected recovery Git ref and Git bundle for the exact reviewed code, a logical database backup, and a sanitized manifest of schema/ledger/counts/fingerprints plus checksum files. Protect Docker volumes and image assets; determine whether logical backup covers media or whether separate volume snapshots are required. Record restore owner, location class, retention and access controls outside Git.
5. Prove restore of the logical backup and required media artifacts on an isolated copy. Verify checksums and protected aggregates. A backup without restore evidence is not a recovery point.
6. Capture Auth0 non-production configuration/versioned change record without secrets or tenant-sensitive IDs; define how to revert settings independently of app deployment. Never infer tenant state from repository config alone.

## Rehearsal and maintenance window

Rehearse each additive migration on (a) a fresh database and (b) a sanitized/upgraded copy at 001–013. Run twice for idempotence; inject duplicate issuer/subject, uniqueness race, stale draft, slug conflict and partial MySQL DDL. Confirm indexes, FKs, defaults, ledger and fingerprints. Use a feature-disabled application version compatible with both pre/post schema. Define a maintenance window, decision owner, communications, service stop/start authority, expected downtime and explicit abort thresholds **before** any production cutover. This runbook does not authorize stopping or restarting current services.

For 014, isolated rehearsals passed fresh 001–014, protected 001–013-copy upgrade, repeat-run no-op, malformed legacy identity rejection before DDL, conflicting binding rejection, unique-pair rejection and interruption immediately after table DDL followed by completion through the normal migration runner. The interrupted state has an empty `user_identities` table and no 014 ledger entry. It is **not an application-serving state**: compatibility lookup is checked only after successful rerun and backfill. The five copied legacy/test identities resolve to their original user IDs, and protected row fingerprints and owner edges remain unchanged in the isolated copy. These tests do not prove a canonical backup/restore or authorize a live write. Later migrations and tenant capabilities have not been rehearsed.

**014 live-migration boundary, subject to separate owner approval:** Quiesce application traffic and writers before the first 014 DDL; the current callback can still create a legacy `users` row without a new binding, so do not allow it to run between snapshot and reconciliation. Verify exact reviewed code, fresh 001–013 ledger, five identity/ownership edges, protected aggregates, service identity, a protected logical backup and isolated restore. Run `php database/migrate.php --through=014` through the normal runner. Keep traffic quiesced until 014 is recorded and the binding count, exact pairs, owner edges and protected fingerprints are reconciled. If interrupted after DDL, keep services quiesced, inspect the incomplete schema and ledger, and rerun the same runner only if the state matches the rehearsed partial schema. Release traffic only after successful recovery and read-only compatibility checks. Do not cut over callbacks, enable registration/linking, or change Auth0 in this operation. A state outside the rehearsed boundary requires reviewed recovery before resumption.

## Future deployment order

1. Obtain owner phase acceptance and latest legal/tenant gates. Capture preflight manifest and proven recovery point.
2. Keep registration/linking/publication disabled and traffic quiesced. Apply reviewed additive schema in the authorized window; verify ledger and row/ownership fingerprints before resuming traffic.
3. Deploy compatibility application, then guarded security behavior, then UI, linking and publication slices in their approved phases. A schema migration alone never enables an external journey.
4. Validate authentication callbacks, restricted verification, old Owner route gates, session rotation/logout, role MFA, draft privacy, public HTML/JSON/media and protected aggregates using disposable/synthetic accounts and SELECT-only canonical inspections where practical.
5. Enable cohorts only after [Phase 7](06-IMPLEMENTATION-PLAN.md) gates. Monitor safe event-code rates for callbacks, verification loops, switches, draft conflicts and access denials; never log request bodies or raw identities.

## Rollback triggers and order

Trigger a stop on failed migration/partial DDL, identity uniqueness conflict, owner/portfolio fingerprint mismatch, verification or privileged-MFA bypass, private preview/media/Evidence leak, authentication outage, unusable Arabic/English accessibility path, or unavailable restore. First disable new registration, linking or publication flags and halt writes where authorized; preserve incident evidence without secrets. Then assess application/schema compatibility. Revert application code to the protected Git ref only if compatible with the additive schema; otherwise use a reviewed forward fix. Do **not** automatically drop columns after MySQL partial DDL. Restore database and media from protected artifacts only under an owner-approved incident procedure, with a verified consistency point and documented handling of legitimate writes after that point. Revert Auth0 changes from the non-production-proven configuration record separately; distinguish local, Auth0 and provider-session effects. Service stop/start must be executed only by the authorized operator in the approved window.

MySQL DDL auto-commits; a failed 014 may leave a table without backfilled rows or a ledger entry. Git rollback does not undo that schema. The normal runner safely completes the rehearsed post-`CREATE TABLE` interruption, while arbitrary partial or incompatible tables require manual review. Do not claim an automatic down migration or drop `user_identities` while live writes or dependent code may exist. A full restore can lose legitimate writes made after its consistency point; reconcile those writes under an owner-approved incident procedure. The locally verified Git bundle is code recovery, not a substitute for a proven live database/media restore.

## Incident evidence and final reconciliation

Record time, approved phase, code ref, migration ledger, sanitized schema/count/fingerprint manifest, container image/network/mount/volume/port identifiers if accessible, feature-flag state, safe event codes and affected route classes. Keep full backups, secrets and incident private data outside Git. After recovery or deployment, compare pre/post Git refs, ledger, ownership edges, all protected content/image fingerprints, Evidence and technology aggregates, publication flags, port reachability and Docker identities where API access exists. Note unavailable checks explicitly. The separate five-record reconciliation requires its own backup, mapping, owner approval and post-write fingerprint runbook; never merge by email.

## Canonical bind-mount deployment boundary (2026-10-05)

The canonical repository checkout is a live deployment surface because the web service bind-mounts it. Keep that checkout clean on the approved main commit. Develop and verify feature branches only in separate worktrees or disposable clones outside every canonical bind mount. Before any checkout change on the canonical path, preserve a recovery ref and verified bundle, quiesce only the application services, verify the database and protected data read-only, then check the served file hashes and routes after restarting the original services. A healthy container identity alone does not prove that bind-mounted application code is unchanged. The temporary Phase 2A exposure was corrected by restoring main while traffic was quiesced; retained logs showed no callback request or successful Owner authentication in the observed interval. The callback candidate remains unpushed and needs a separately approved rollout.
