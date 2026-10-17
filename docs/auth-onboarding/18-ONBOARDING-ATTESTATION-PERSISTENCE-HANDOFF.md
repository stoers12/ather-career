# Versioned onboarding-attestation persistence core — 2026-10-17

Baseline: `6aa1b3e34807591088eb782d0a74aeb45e19daae` (approved merged PR #21;
Main CI passed). Isolated worktree: `.worktrees/onboarding-attestation`.
No AGENTS.md was found in the checkout, worktree or ancestor chain. Handoffs
14–17, AUTH-ADR-014/023, the data-model contract, authorization/session helpers,
migration runner and existing registration/migration/ownership tests were read.
The canonical checkout's unrelated required-gates script edit is preserved.

## Internal API and trust boundary

`includes/onboarding_attestations.php` exposes:

- `currentOnboardingAttestationBundle(): ?OnboardingAttestationBundle` always
  returns **null**. No approved policies, links, active versions, environment
  overrides or production synthetic fallbacks exist.
- `recordOnboardingAttestations(PDO, AuthenticatedUserContext,
  ?OnboardingAttestationBundle, array): array` accepts exactly
  `age_18_or_older`, `terms_accepted`, `privacy_notice_acknowledged`, each the
  boolean `true`. Missing, false, coerced, malformed and extra fields fail before
  writes. No client timestamp, User ID, version, digest or language is accepted.
  Null bundle throws; no evidence is recorded.
- `readOnboardingAttestations(PDO, AuthenticatedUserContext,
  ?OnboardingAttestationBundle): ?array` returns the exact matching stored row
  or null. Null configuration, missing evidence and changed descriptors never
  count as current acceptance. It does not select the latest historical row.

Descriptors are final readonly internal objects, constructed only from trusted
server document resolution. Construction validates shape, **not legal approval**.
A future server resolver must own immutable identifier/version-to-content
mapping, compute lowercase SHA-256 of the exact presented bytes, and bind the
document translation to the presentation. Never hydrate these objects from
POST/query/claims or let a request choose the authoritative bundle. Identifier
and version are exact ASCII tokens of 1–64 bytes; digest is exactly 64 lowercase
hex characters; document language is a bounded 2–35 byte language tag (2–3 lower
case primary letters and optional 2–8 alphanumeric subtags). No normalization
occurs. Each of age statement, Terms and Privacy Notice has its own descriptor.
The age descriptor versions the 18+ self-attestation statement, not verified age.

One row means all three positive actions occurred together: “I confirm I am 18
or older”, acceptance of Terms of Use, and acknowledgement of the Privacy Notice.
Privacy acknowledgement is not blanket consent to every processing purpose.
The core stores no birth date, email, external subject, IP, user agent, document
body, private profile content or session secret. Synthetic descriptors exist
only in guarded CLI test fixtures.

Both data operations require an active PHP session, exception-mode PDO and an
owned transaction. The existing `requireAuthenticatedUser()` validates the
current account, authorization version and idle/absolute lifetime; a supplied
context must match that independently derived User. The operation locks that
User row, repeats the helper before evidence access, and commits only its own
work. Disabling/revocation commits before the lock are observed; changes after
the lock serialize after this operation. Session lifetime is checked at the
authorization boundary, using the existing helper limits. Internal helper
`onboardingAttestationOperation` is implementation detail, not an input API.
Future routes must call the explicit write operation and supply their reviewed
CSRF, presentation-binding and request controls.

## Schema and migration/recovery contract

015 was unused at the approved baseline. Actual migration
`015_onboarding_attestations` is narrower than the historical broad 015 proposal:
one additive InnoDB table, no backfill and no account/workflow/security changes.

| Column(s) | Exact storage |
| --- | --- |
| `user_id` | `INT UNSIGNED NOT NULL` |
| `{age,terms,privacy}_identifier` | Each `VARBINARY(64) NOT NULL` |
| `{age,terms,privacy}_version` | Each `VARBINARY(64) NOT NULL` |
| `{age,terms,privacy}_digest` | Each `VARBINARY(64) NOT NULL`; lowercase SHA-256 text |
| `{age,terms,privacy}_language` | Each `VARBINARY(35) NOT NULL` |
| `recorded_at` | `DATETIME(6) NOT NULL`, server `UTC_TIMESTAMP(6)` on INSERT |

The primary key is `user_id` followed by all twelve descriptor columns in the
above age/terms/privacy order, each identifier/version/digest/language. Its full
binary values preserve case and exact translations without a hash-collision
identity shortcut. Its User prefix serves User lookups. No extra row ID,
acceptance booleans or mutable current-acceptance flag is needed.
`fk_onboarding_attestations_user` references `users(id)` with explicit update
and delete **RESTRICT**. There is no cascade, automatic deletion, expiry or
retention policy. Rights/deletion/retention handling needs separate review.

The User lock serializes same-User submissions; the full primary key is the
final database uniqueness boundary. Repeats return the original row/time,
without UPDATE/IGNORE/upsert. Changed documents create separate historical
rows. SQL/commit failures roll back; caller-owned transactions are refused.

The normal advisory-locked migration runner executes a guarded whole-table
CREATE, verifies exact columns, attributes, full key parts, FK, engine and
absence of extra constraints/indexes/triggers, then records 015. An interrupted
CREATE with no ledger is recovered only if the table is compatible and empty.
Incompatible/partial shapes or unexpected pre-ledger evidence stop for manual
inspection; there is no destructive auto-repair or ledger invention. Repeats
also verify 015's schema even when its ledger entry exists. Readiness retains
its complete manifest/ledger check and now requires the evidence timestamp
column. Restored-startup CI deliberately expects 001–015, retaining full dump
fingerprint preservation. Older migration files and existing data are unchanged.

## Validation and retained review evidence

Disposable MySQL 8.4.11 and cached dependency-complete PHP 8.3.33 were used, on
a generated network with no host database ports. Source is mounted read-only;
the runner copies it into container-local scratch and uses the cached image's
vendor tree. Generated credentials, fixture databases, PHP sessions and
container storage are removed. No canonical credentials/services are used.

`scripts/run-onboarding-attestation-tests.php` requires CLI, exact test mode and
the matching generated database namespace. It covers the populated 001–014
upgrade, interrupted DDL/repeat migration, partial/incompatible shapes, strict
affirmations, bounded exact descriptors, UTC time under a non-UTC connection,
unavailable/missing/stale evidence, anonymous/inactive/disabled/expired/revoked/
malformed/cross-User authority, revocation between validations, caller transaction
and silent-PDO refusal, failures before and after a real INSERT, direct duplicate
key refusal, repeat evidence, historical preservation, FK restriction, and two
genuinely concurrent child processes released at a pre-lock barrier. Both workers
return the same durable row. Snapshots prove all preexisting rows are unchanged
and test fixtures are removed. Test fault/barrier PDO subclasses add no production
hook. CI invokes this suite once with existing generated database-scoped
credentials, retaining its EXIT and always-cleanup controls.

Local review commands/results are retained alongside the complete baseline-to-head
patch in `.worktrees/attestation-review`. `validate.ps1`/`validate.sh` reproduce the
disposable setup and command sequence; timestamped logs include actual output.
The successful checks include focused tests on populated and empty-fresh migrated
databases, ownership/public lifecycle/tenant authorization/Owner flow/Experience/
Auth0 rehearsals, registration core and real routing/SDK tests, full Phase 2,
Sign In, readiness claim/session/real POST regressions, and PHP lint. CI YAML
parsing, changed CI Bash syntax and `git diff --check` are retained separately.
Remote CI is not run because no push is authorized.

Resolved runner failures are retained: the first setup used the wrong cached
vendor directory; a later extra fresh fixture incorrectly assumed the baseline
had no seed project. The empty-fresh fixture now removes only its own disposable
seed before migration; the populated preservation rehearsal exercises the existing
seed/ownership path. Those initial runs are not represented as complete passes.

## Remaining integration and rollout dependencies

Registration remains Google-only and default off. No production route/UI includes
or calls this core. Preview checkboxes, current owner access and legacy route gates
are unchanged. Attestations grant no Portfolio access and change no User status;
they do not establish email readiness, provider freshness, legal compliance or
completed onboarding.

Before rollout: approve actual Terms/Privacy text, links, immutable versions and
translations; review policy presentation and bundle binding, CSRF and acceptance
routes/UI; decide legacy-route disposition and account/onboarding gates; complete
required profile/workflow integration and activation/rollback review. Provider
provenance/freshness, qualified legal review, legal basis, retention and access/
correction/withdrawal/deletion/restriction/objection/export remain separate work.
No withdrawal/deletion workflow, framework or generic policy engine is added.

No push, merge, deployment, live migration, registration activation, Auth0 change,
canonical resource change or Notion update was performed.
