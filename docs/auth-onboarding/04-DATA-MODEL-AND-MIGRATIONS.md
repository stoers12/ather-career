# 04 — Data model and migration plan

**2026-10-05 status update:** The Phase 1 local-only statements below are historical. Migration 014 is deployed with five exact legacy bindings and ledger 001–014. The local Phase 2A callback contract is in [10](10-PHASE2A-CALLBACK-RESOLUTION.md); it makes no schema or data change.

**Phase 1 local implementation:** Migration 014 and a read-only compatibility repository exist in unpushed commit `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e`. Migration 014 passed isolated fresh and upgraded rehearsals; it has not been applied to the canonical database, whose ledger remains 001–013. Migrations 015–017 and all account-readiness, onboarding, linking and publication stores below remain proposals. Current behavior is detailed in [01](01-CURRENT-STATE.md); decisions in [02](02-ARCHITECTURE-DECISIONS.md).

## Current relevant model and limitations

| Current structure | Verified role | Limit |
| --- | --- | --- |
| users | Local account, account status/authorization version and one OIDC issuer/subject pair | Cannot safely hold multiple identities; new subjects can become active without new gates |
| portfolios | One owner portfolio and publication/slug state | Private portfolio default, but no reviewed historical-slug lifecycle |
| personal_info and profile-related rows | Current name, professional and contact facts | Required onboarding fields and per-contact privacy need a careful mapping |
| projects, project_technologies, project evidence fields | Owner-scoped work and Evidence data | No per-project or independent public Evidence choice |
| portfolio/contact visibility state | Existing portfolio and contact publication controls | New phone-by-default and type-by-type contract needs review |
| migration ledger | Applied schema history | Canonical live ledger 001–013 at Phase 1 review; SELECT-check again before any rollout |

The Phase 1 characterization inspected ownership relationships and the current binary issuer/subject columns. Do not infer identity from email or profile fields.

## Proposed responsibilities and constraints

| Data responsibility | Proposed store | Key constraints, lifecycle and privacy |
| --- | --- | --- |
| Core account | users plus account-security state | Stable opaque internal ID; status, authorization version, created/updated/disabled timestamps; no password; callback never trusts profile email |
| External identity | user_identities | ID, user_id FK, binary-safe bounded issuer/subject, provider/connection, primary/linked state, created/linked/last-authenticated timestamps; unique (issuer, subject), indexed user_id; no email-only linkage |
| Verification | Trusted identity/account-readiness record | Connection-specific provenance, verified status and checked time; stale/unverified users remain holding; no editable profile field authority |
| Roles and assurance | Role assignment/security state | Auditable normal/admin/support assignment, MFA requirement, recent step-up evidence and authorization-version invalidation; least privilege |
| Eligibility and consent | Versioned events | 18+ attestation without DOB; Terms acceptance and Privacy Notice acknowledgement with version, time and policy identifiers; withdrawal/restriction events separate from profile |
| Onboarding state | Account workflow state | invited/new/verification-holding/ineligible/drafting/completed/disabled transitions; completion timestamp; portfolio existence alone never means complete |
| Draft | onboarding_drafts | user_id unique FK, step, allow-listed normalized answers or bounded server payload, integer version, status, created/updated timestamps, idempotency record and expiry/retention policy; private owner-only |
| Professional profile | personal_info plus normalized optional tables | Flexible display name, ISO country, preferred language, status, specialization and headline required at completion; optional names, city, education, bio, skills, links and image later |
| Public profile identity | portfolios and slug_history | Atomic unique current/history reservation, reserved names, change cooldown, redirect/retired status and timestamps; private portfolio independent of slug |
| Contact | Per-type visibility setting | Phone optional and private default; no login/recovery role without separately approved need; public projections use explicit per-type choice |
| Project/Evidence | projects plus visibility state | New project private; separate per-project Evidence public choice private; published project alone never exposes Evidence; indexes for public filters |
| Security audit | Bounded event store | Opaque internal actor/target IDs, event code/time/result; no token, raw subject, session ID, request body or private Evidence |
| Server sessions | Existing PHP store; future reviewed registry | Bounded expiry, rotation, authz/assurance linkage; device review/revocation needs separate storage and recovery design |

All new foreign keys must use reviewed delete behavior. Decide cascade versus retention explicitly for consent/audit/legal obligations. Uniqueness must be proved under real MySQL collation and concurrency. Add indexes for user_id, account-state checks, draft owner/version, public visibility and slug lookups as query plans warrant. Persist timestamps in a documented time basis. Every owner write must derive user_id from authenticated server context.

## Five-section onboarding field matrix

| Step | Required answer or action | Optional or deferred | Authority and storage |
| --- | --- | --- | --- |
| 1. Account readiness | Trusted verified-email status, 18+ attestation, versioned Terms acceptance and Privacy Notice acknowledgement | No exact date of birth | Identity/readiness state and separate versioned eligibility/consent events |
| 2. Public identity | Flexible professional/display name, ISO-backed country, preferred language | Arabic/English name variants, city | Professional profile and locale preference |
| 3. Professional snapshot | Current professional status, field/specialization, short headline | University, graduation year, biography | Professional profile; optional education later |
| 4. Public profile identity | Public slug selection, visibility explanation, private sanitized preview and separate contact controls | Image, phone, links, skills | Atomic portfolio slug reservation; private preview and contact settings |
| 5. Review and create | Review all required answers, change links and explicit confirmation | First project after Dashboard | Atomic private profile/portfolio completion, never automatic publication |

Only active-step fields are validated on draft save; all required answers are validated together at completion. Saved values are restored on Back/resume. Phone stays optional and private; father's name, a second phone, full legal-name components, gender, exact birth date and home address are not required.

## Migration sequence after 013

### 014 — Identity model and safe backfill

**Implemented locally; not deployed.** The guarded 014 runner creates only `user_identities` (InnoDB, utf8mb4) and a migration-ledger entry after success. It does not alter `users`, portfolios, projects or their ownership. Exact schema:

| Column | Type and rule |
| --- | --- |
| `id` | `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY` |
| `user_id` | `INT UNSIGNED NOT NULL`; `fk_user_identities_user` to `users(id)` with update/delete `RESTRICT`; `idx_user_identities_user` |
| `oidc_issuer` | `VARBINARY(2048) NOT NULL` |
| `oidc_subject` | `VARBINARY(255) NOT NULL` |
| `is_primary` | `TINYINT(1) NOT NULL DEFAULT 1`; check permits only 0 or 1 |
| `provider_name` | nullable `VARCHAR(32)` using `ascii_bin`; not inferred during backfill |
| `connection_name` | nullable `VARCHAR(64)` using `ascii_bin`; not inferred during backfill |
| `created_at` | `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` |

`uq_user_identities_pair` uniquely indexes the full binary `(oidc_issuer, oidc_subject)` pair. Issuer and subject are opaque, case-sensitive bytes: validation rejects empty or over-limit values; it does not trim, case-fold, rewrite or derive either value from email. The legacy `users.oidc_issuer` and `users.oidc_subject` columns and their current unique-subject index remain intact. The migration preflights every legacy pair before DDL, locks users and bindings during backfill, rejects duplicate or conflicting rows, inserts exactly one primary binding per existing user and records 014 only after reconciliation. No identity consolidation or portfolio reassignment occurs.

`findCompatibleIdentityUser` is an unwired, read-only repository function. Before 014 it resolves a bounded subject through the legacy binary column and verifies the issuer exactly. After 014 it resolves the exact binary pair through `user_identities`, joins the original `users` row, and rejects a nonprimary or legacy-mismatched binding. It returns only internal user ID, account status and authorization version, or `null`; it neither creates an account nor grants portfolio access. Existing callbacks still use their legacy resolver; owner authorization still derives portfolio ownership from the authenticated server session. A callback cutover requires a later, separately reviewed change.

This slice implements the identity and preservation portions of [AUTH-ADR-001, AUTH-ADR-003 and AUTH-ADR-024](02-ARCHITECTURE-DECISIONS.md). The proof is scoped to `MIGRATION-001`, `MIGRATION-003`, the database-constraint portion of `AUTH-LINK-002` and the isolated 014 portion of `ROLLBACK-002` in [07](07-TEST-AND-ACCEPTANCE-MATRIX.md); it does not satisfy registration, verified-email or linking gates.

### 015 — Account verification, roles/security, onboarding and consent

Add explicit account/verification/onboarding state and provenance, auditable role/assurance state, age eligibility event and versioned Terms/Privacy events. Preserve existing authorization-version semantics; define transitions for legacy accounts and disabled/unverified accounts. Server session registry is optional until storage/recovery review. Gate every legacy Owner route before registration can open. Avoid mutable profile email as verified identity authority.

### 016 — Private drafts and profile fields

Add one owner-scoped versioned draft per user, bounded idempotency and timestamps. Map display name, ISO country, language, professional status, specialization and headline without silently overwriting existing full_name, professional_title or hero_headline meanings. The write contract uses active-step allow-list and optimistic version; final completion validates all fields and creates/updates private profile and portfolio in one transaction. Optional fields stay optional. Slug reservation remains private and atomic; create slug-history structure here or by 017 before changes are enabled.

### 017 — Independent project and Evidence publication

Add per-project visibility with private default, independent public Evidence choice with private default, contact-type controls and slug history/redirect retirement if not already created. Coordinate public HTML, JSON and media filters with schema rollout. Inventory already-published projects and obtain owner-approved backfill semantics; do not silently hide currently public work or publish private Evidence.

## Migration rehearsal and recovery requirements

Use additive-first migrations; no destructive column removal in 014–017 initial rollout. Rehearse a **fresh database** and an **upgraded copy at 001–013**; include partially applied MySQL DDL, duplicate identity, concurrent link and slug reservation, FK/collation and null/default cases. Run each migration twice and prove repeat-run no-op/idempotence. Compare before/after schema, ledger, counts, ownership edges, publication flags and content fingerprints for protected projects, images, Evidence and technologies. Obtain a protected backup and prove isolated restore before any live write. If a migration partially applies, stop registration and prefer a reviewed forward repair; never automatically drop columns or overwrite protected data. A later destructive contract migration needs separate approval.

## Separate legacy/test-account reconciliation

**Five owner-created legacy/test account records associated with development use.** Preserve all five initially. This is a future separately owner-approved operation, not migration backfill:

1. Authenticate control of every external identity; email equality is never proof.
2. Locate the portfolio with four protected projects through authorized sanitized inventory; treat its account only as a canonical candidate.
3. Select a canonical account only after owner confirmation; inventory every portfolio and profile, project, Evidence, technology, image/media, skill, experience, message, slug and publication dependent row.
4. Decide separately whether each noncanonical portfolio is retained privately, migrated, archived or removed. Rehearse unique-key/FK conflicts and prohibit unmapped cross-owner movement.
5. Create a protected backup/recovery point outside Git before any write, then use an approved runbook with stop conditions.
6. Verify every affected ownership edge, row count and data fingerprint afterward. Never log raw subjects, emails or private profile content.

Protected baseline: four-project portfolio, Evidence 12/12, complete-project progress 4/4, technology 27 occurrences, **24 distinct mapped technologies**, zero unmapped. These counts are regression checks, not permission to change data.
