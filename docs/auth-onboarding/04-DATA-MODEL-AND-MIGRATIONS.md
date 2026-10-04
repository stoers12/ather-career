# 04 — Data model and migration plan

**Plan only.** No migration file or live row is changed by this record. The verified baseline has ordered migrations 001–013. Schema names below are proposals until isolated migration rehearsal and review. Current behavior is detailed in [01](01-CURRENT-STATE.md); decisions in [02](02-ARCHITECTURE-DECISIONS.md).

## Current relevant model and limitations

| Current structure | Verified role | Limit |
| --- | --- | --- |
| users | Local account, account status/authorization version and one OIDC issuer/subject pair | Cannot safely hold multiple identities; new subjects can become active without new gates |
| portfolios | One owner portfolio and publication/slug state | Private portfolio default, but no reviewed historical-slug lifecycle |
| personal_info and profile-related rows | Current name, professional and contact facts | Required onboarding fields and per-contact privacy need a careful mapping |
| projects, project_technologies, project evidence fields | Owner-scoped work and Evidence data | No per-project or independent public Evidence choice |
| portfolio/contact visibility state | Existing portfolio and contact publication controls | New phone-by-default and type-by-type contract needs review |
| migration ledger | Applied schema history | Expected 001–013; exact live ledger must be SELECT-checked before any rollout |

Ownership relationships and indexes must be inspected against the actual schema and collation in the Phase 1 characterization slice. Do not infer unique issuer/subject semantics from current email or profile fields.

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

Add user_identities and unique (issuer, subject), user_id FK and required indexes. Detect duplicate/missing issuer-subject pairs and collation collisions **before** live writes. Backfill exactly one binding per existing users row from existing issuer/subject columns into its existing user_id; verify one-to-one counts and owner edges. Keep old columns for dual-read compatibility and rollback. A callback change follows characterization and isolated rehearsal, not the migration alone. No registration, linking, user merge or portfolio move in Phase 1.

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
