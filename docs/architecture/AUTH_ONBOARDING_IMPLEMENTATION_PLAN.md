# Ather authentication and onboarding implementation plan

Status: proposed delivery plan, documented 2026-10-04. No route, migration, tenant setting, database row or runtime behavior is changed by this document. The authoritative product/security choices are in `AUTH_ONBOARDING_DECISIONS.md`; its official-source register and requirement-versus-inference notation apply here.

## Baseline to protect

The verified documentation baseline is `main` at `adbd1fd5772f6ce16b4844e29d0c6052e8eda221`, with migration ledger 001–013. One development portfolio contains four protected projects. The existing Evidence Hub calculation for that portfolio reports 12/12 complete Evidence fields, 4/4 projects with complete Evidence, 27 technology occurrences, 24 distinct mapped technologies and zero unmapped. These are regression invariants, not authorization to alter any row. The five account records are described only as **Five owner-created legacy/test account records associated with development use.** They are not five customers and must not be consolidated as part of this implementation plan.

Before every implementation slice, verify Git cleanliness, isolated test database identity, migration ledger, protected portfolio aggregates, public privacy state, Docker/volume identity where access is available, and backup readiness. Never derive authority from a submitted owner ID, email equality or public slug.

## Delivery order and acceptance gates

Small independently reviewable commits are preferred. Each phase has a stopping point for owner acceptance before changing the next boundary. Migrations are additive first; initial rollout does not drop the current issuer/subject columns.

| Phase | Scope and proposed review slices | Definition of done | Stop for owner acceptance |
| --- | --- | --- | --- |
| 0. Architecture | These two documents and official-source review | Decisions, migration order, routes, tests and legal boundaries are reviewable; no product change | Approve architecture and Phase 1 scope |
| 1. Identity foundation | Characterization tests; migration 014; safe backfill; dual-read/compatibility repository; no account linking UI | Every current identity has exactly one matching new binding; five records and all portfolio ownership unchanged; unique issuer/subject race test passes; repeated migration/restart rehearsal passes | Accept backfill and compatibility proof before callback cutover |
| 2. Account/security gate | Migration 015; verified-email holding; eligibility/consent state; role/MFA assurance; Ather Sign In; logout and “Use another account”; session policy; Auth0 non-production validation | No unverified/incomplete/privileged-bypass path reaches Dashboard; both Ather and Auth0 sessions end on standard logout; wrong-account path works for each configured connection; old Owner routes respect gates | Accept tenant results, policy text/version, role assignment and session model before enabling registration |
| 3. Five-section onboarding | Migration 016; draft repository and endpoints; minimal profile fields; translation foundation; accessible responsive screens; private preview; atomic completion | Resumable five-section flow, safe Back, optimistic concurrency, idempotency, CSRF and cross-owner denial; no automatic publication; first project appears only after Dashboard | Accept content, bilingual review, privacy review and measured accessibility results |
| 4. Publication boundaries | Migration 017; project/Evidence visibility; separate contact controls; public HTML/JSON/media filters; slug history and safe redirects | New projects private, project publication independent of Evidence; all public surfaces agree; existing published portfolio behavior follows approved backfill rule; protected metrics unchanged | Approve published-data inventory and legacy visibility backfill before public rollout |
| 5. Linked identities and account tools | User-initiated link/unlink with step-up; conflict handling; security audit; per-device session review/revocation design; account data workflows | Both identities freshly proven; no email auto-link; duplicate identity cannot cross accounts; last login method protected; safe logging and account recovery rehearsed | Accept account-security and recovery evidence; separately authorize any legacy account reconciliation |
| 6. Staged beta | Flagged internal, owner, limited beta, then public beta | Production-like smoke, migration recovery, accessibility, security and legal gates pass; monitoring and rollback owner are assigned | Explicit owner launch approval and qualified legal/privacy sign-off |

Phase order may be adjusted for review, but registration must not be enabled before the verification, eligibility, consent, MFA and session boundaries are working. The separate reconciliation operation is not a prerequisite for preserving all five existing records during the additive rollout.

## Planned migrations after 013 — plan only

### 014 — Identity model and safe backfill

Add `user_identities`: internal ID; `user_id` FK; issuer and subject as bounded binary-safe values; unique `(issuer, subject)`; connection/provider classification; primary/linked status; created/last-authenticated/linked timestamps and safe audit references as needed. Verify uniqueness against the actual database collation and issuer length. Backfill each existing `users.oidc_issuer` and `users.oidc_subject` into exactly one identity for its existing `users.id`. Detect duplicates, issuer mismatch, missing values and interrupted DDL before any write. Retain old columns during dual-read transition. Do not merge users or move portfolios. Safe linking may need both local and Auth0-side state; specify its authority and conflict recovery before enabling it.

### 015 — Verification, role, security, onboarding and consent state

Add explicit account readiness/onboarding lifecycle fields or a normalized state table; current account-status and authorization-version checks remain authoritative. Add role assignments for normal, administrator and support, with auditable assignment; verification provenance and checked time without trusting editable profile contact fields; MFA/step-up assurance metadata; age eligibility attestation; versioned Terms acceptance and Privacy Notice acknowledgement with timestamps. Keep consent events separate from mutable profile fields. Define privacy-conscious data retention and revocation semantics. Design a bounded server-side session registry only after its storage and recovery model is reviewed.

### 016 — Server drafts and minimal professional profile

Add one owner-scoped onboarding draft per user with step, server-side payload/normalized columns, optimistic version, created/updated timestamps, status and bounded idempotency data. Add required profile fields: flexible display name mapping, ISO country code, preferred language, professional status, field/specialization and canonical short headline. Preserve existing profile values and the old `full_name`, `professional_title` and `hero_headline` meanings until a reviewed mapping is in place. Keep optional education, city, images, links, skills, biographies and phone outside mandatory completion. Reserve public slug atomically against current and historical slugs, while keeping its portfolio private.

### 017 — Independent project and Evidence publication

Add project visibility with private default and explicit publication transition. Add independent per-project public Evidence choice, private default. Separate contact visibility by type and default phone to private. Add slug-history/redirect/retirement state if not introduced earlier. Update public HTML, JSON and media reads in the same slice. Existing published projects require an owner-approved visibility backfill rule; do not silently change existing public exposure or publish private Evidence.

For every migration: use additive schema first, enforce FKs/checks/indexes, verify exact live preconditions, rehearse fresh and upgraded databases, detect partial MySQL DDL, rerun to prove idempotence, and provide a forward-recovery path. No destructive column removal in 014–017 initial rollout. A later contract migration needs separate approval and rollback design.

## Schema responsibilities and authority

| Responsibility | Proposed store | Authority and privacy rule |
| --- | --- | --- |
| Durable account, status, authz version, roles | `users` plus role/security state | Internal ID only; callback resolves verified identity, routes recheck current row |
| Multiple external identities | `user_identities` | Unique issuer/subject; email never authorizes binding |
| Verification | Account-readiness record or trusted identity metadata | Provider/connection-specific verified signal; recheck before protected access |
| Eligibility and policy acceptance | Versioned eligibility/consent records | Private, timestamped; policy versions retained for audit |
| Public professional facts | `personal_info` and related optional tables | Owner-scoped; sanitized preview; contact controls separate |
| Public handle/publication | `portfolios` plus slug history | Atomic reservation; draft remains private; explicit publish |
| Draft workflow | `onboarding_drafts` | Owner-scoped, allow-listed, CSRF protected, versioned, private |
| Projects and Evidence | `projects` and visibility fields/related state | Owner-only writes; separate public project and Evidence gates |
| Sessions/security events | Server-side session store and bounded audit | No secrets or raw PII in browser storage or security logs |

## Proposed route contracts

Routes are proposed contracts, not fixed filenames. Preserve old Owner URLs as guarded compatibility routes until cutover. Every response that contains private data is `no-store`; redirects use configured allowlists rather than request-supplied destinations.

| Route | Method and state | Contract |
| --- | --- | --- |
| `/sign-in` | GET, anonymous or safe remembered hint | Ather entry page; Google, Microsoft and email/password lead to Auth0; no local password field |
| `/sign-up` | GET, anonymous | Explain eligibility and Auth0 registration intent; no Ather credential form |
| `/auth/start` | GET or CSRF-protected POST after route review | Rate-limited, purpose-bound state/nonce/PKCE; bounded return target; optional signup or chooser intent |
| `/auth/callback` | GET, one-time transaction | Validate all OIDC checks; resolve identity; rotate session; route to denied, verify, onboarding or Dashboard; never link by email |
| `/verify-email` | GET restricted; POST resend/status | Holding page; no Owner resource authority; resend limit and safe feedback |
| `/logout` | POST with CSRF | End local session, then Auth0 SSO logout; allowlisted anonymous `/sign-in` return; no federated logout default |
| `/use-another-account` | POST with CSRF | End local and Auth0 SSO, begin a fresh chooser/re-authentication path; rate limited |
| `/account/identities/link` and callback | POST start/GET one-time callback | Current account, recent step-up and fresh second identity; uniqueness and conflict checks; no portfolio move |
| `/account/identities/unlink` | POST with CSRF | Recent step-up; prevent last-method removal; security audit |
| `/onboarding?step=n` | GET, verified eligible account | Authorized draft read; visible logical step and Back/Change links |
| `/onboarding/draft` | POST with CSRF | Active-step allow-list and validation; expected version; idempotent response; stale-version conflict and safe errors |
| `/onboarding/preview` | GET, draft owner | Sanitized private profile preview; no public slug publication side effect |
| `/onboarding/complete` | POST with CSRF | Full validation and atomic private portfolio/profile completion; redirect to Dashboard |
| `/p/...` public routes | GET, anonymous | Published portfolio plus project/Evidence/contact visibility at every HTML, JSON and media endpoint |

For mixed-direction content, render trusted UI strings with locale `lang`/`dir`, and isolate user-authored values with `dir="auto"` or `bdi`. URL slugs remain directionally isolated ASCII identifiers.

## Existing files and components likely affected

- OIDC and sessions: `includes/auth0_oidc.php`, `includes/auth0_identity.php`, `includes/session.php`, `includes/owner_session.php`, `includes/authorization.php`, `includes/owner_flow.php`, `includes/csrf.php`, `includes/rate_limit.php`, `includes/security_events.php`, `includes/http.php`, `includes/runtime_readiness.php`.
- Existing entry and compatibility routes: `owner_login.php`, `owner_oidc_callback.php`, `owner_logout.php`, `owner_onboarding.php`, `owner.php`, `public/` route shims and web-server routing rules.
- Profile, publication and public reads: `includes/profile_actions.php`, `includes/portfolio_scoped_data.php`, `includes/public_lifecycle.php`, `includes/media_access.php`, `includes/project_evidence_repository.php`, `includes/owner_publication_presentation.php`, `public_portfolio.php`, `public_projects_json.php`, `public_media.php`, `owner_profile.php`, `owner_projects.php`, `owner_project_evidence.php`.
- Shared UI: `includes/owner_layout.php`, `includes/owner_form_feedback.php`, `includes/portfolio_presentation.php`, `admin.css`, `admin.js`, `evidence_hub.css`, `owner_theme.js`, `portfolio.css`, `portfolio.js`, and new locale resources. Reuse the existing form/error helpers and green/gold tokens rather than copying components.
- Delivery checks: `database/migrate.php`, new ordered migration files later, `tests/phase2/`, `scripts/run-phase2-tests.php`, isolated rehearsals, `.github/workflows/ci.yml`, production security checks and smoke scripts. No such file is changed by Phase 0.

## Characterization and verification matrix

Before behavior changes, pin the current owner-session and OIDC callback contracts, existing five identity-to-user bindings, one-portfolio-per-owner invariant, protected four-project portfolio aggregates, Evidence 12/12 and 4/4, technology 27/24/0, private/public lifecycle behavior, media isolation, existing published route output and migration ledger 001–013. Use synthetic or sanitized fixtures; do not print private values.

| Area | Required focused tests |
| --- | --- |
| Authentication | Google/Microsoft/database connection callback with mocked Auth0; state, nonce, PKCE, issuer, audience, signature, expiry, replay, canceled login, wrong-account selection and direct old-route bypass |
| Registration and verification | New/returning identity; unverified holding; trusted claim refresh; resend throttling; disabled account; existing-owner transition; no Dashboard bypass |
| Linking and MFA | Both identities freshly proven, step-up freshness, admin/support MFA, duplicate issuer/subject race, conflict elsewhere, unlink-last-method denial, no email auto-link, sanitized audit |
| Sessions and CSRF | Standard and opt-in idle/absolute bounds, privileged cap, cookie attributes, rotation on authentication/step-up, local/Auth0 logout, revocation, cross-site POST denial, no auth data in browser storage |
| Abuse and privacy | Rate limits for all new actions; Auth0 bot/breach settings in non-production; generic errors; no PII/secret logs; private phone; account enumeration checks |
| Onboarding | Five logical sections, minimal required fields, autosave debounce/idempotence/version conflict, stale tab, Back/resume, active-step validation, atomic final validation, duplicate final POST, private preview and no auto-publication |
| Ownership/publication | Cross-owner draft/profile/project/Evidence/image denial; new project private; separate Evidence choice; consistent HTML/JSON/media projection; published legacy project policy; slug reservation/race/history/retirement |
| Localization/accessibility/responsive | Arabic and English translation completeness, `lang`/`dir` and mixed text, keyboard/focus/error summary, screen-reader labels, reduced motion, 320px through desktop layouts and form reflow |
| Operations | Fresh/upgraded/partial migration rehearsal, repeat run, production-like smoke, backup/restore on isolated copy, Auth0 connection and logout validation, monitored rollout/rollback |

Do not run data-writing rehearsals against the canonical database. Existing tests are a foundation, not evidence that the new behavior works.

## Isolated migration rehearsal and recovery

1. Capture sanitized schema/row-count and protected aggregate fingerprints from the canonical database using SELECT only. Create test copies through the approved isolated fixture process; never print identity or project content.
2. Rehearse 014–017 against a fresh database and an upgraded 001–013 copy. Inject duplicate identity, concurrent slug, stale draft and partial-MySQL-DDL cases. Check FKs, unique indexes and null/default behavior after each migration.
3. Run each migration twice and verify the second run is a no-op. Compare row counts, ownership relationships, private/public flags and content fingerprints for protected projects, Evidence, technology values and images.
4. For additive failures, prefer a documented forward fix; do not automatically drop columns or roll back a partially applied MySQL DDL. Stop the rollout, keep registration disabled, diagnose exact applied state and restore an isolated copy before any production recovery.
5. Before any live migration, make a protected backup/recovery point and prove restore on a separate database. Define owner, maintenance window, rollback trigger and versioned application compatibility. Existing columns remain until a later approved contract migration.

## Auth0 non-production tenant validation

Use a separate non-production tenant and disposable identities. Verify Universal Login connections, the minimum OIDC email scope and trusted verification signal for each connection, password policy including the 15-character single-factor minimum, password-manager/paste behavior, breached-password and attack protection, bot/CAPTCHA behavior, MFA enrollment and privileged enforcement, WebAuthn/TOTP, plan entitlement for adaptive MFA and linking, fresh step-up evidence, Auth0 SSO logout with allowlisted return, and account switching for Google and Microsoft. Do not infer provider session deletion from Auth0 logout. Record pass/fail and safe configuration facts only; never retain tokens, raw subjects, credentials, personal data or tenant-specific sensitive settings in repository artifacts. Any plan or connection limitation that weakens an approved decision stops rollout for owner review.

## Separate legacy/test-account reconciliation operation

**Five owner-created legacy/test account records associated with development use.** This operation is later and requires its own owner approval; no automatic merge occurs in migrations or login.

1. Preserve all five account and portfolio records initially.
2. Have the owner authenticate control of each external identity through a fresh supported flow; email equality is never proof.
3. Identify the portfolio with four protected projects by authorized, sanitized inventory; do not infer canonical-account status from project count.
4. Ask the owner to confirm the canonical account only after identity and portfolio ownership are proven.
5. Inventory each portfolio and dependent profile, project, Evidence, technology, media, skill, experience, message, slug and publication row before any consolidation.
6. Decide separately for each noncanonical portfolio whether it stays private, is migrated, archived or removed; do not bundle those decisions with identity linking.
7. Rehearse uniqueness and FK conflicts and prohibit cross-owner data movement without explicit, auditable mapping.
8. Create a protected backup and recovery point and prove restoration before a live write.
9. Recheck every affected ownership edge, row count and content fingerprint afterward, including the four protected projects and their images, Evidence and technology values.
10. Use an owner-approved runbook with stop/rollback conditions, safe event logging and no raw identifiers in shared reports.

## Staged rollout, risks and stopping rules

Roll out behind a disabled registration feature flag: isolated tests, non-production Auth0, internal owner-only rehearsal, limited beta, then public beta after qualified legal/privacy review. Keep the old Owner workflow available only through the same new gates during transition. Measure failed callbacks, verification loops, account-switch failures, draft conflicts, access denials and publication leaks using safe event codes. Do not log request bodies or identity subjects.

Primary risks are Auth0 plan/connection limitations, issuer/subject migration mismatches, stale sessions bypassing new gates, loss of access to a legacy owner portfolio, MySQL partial DDL, slug-history privacy, contact/public Evidence leakage, Arabic/English regression and unreviewed legal assumptions. Stop for owner acceptance at every phase gate above; stop immediately on a protected-data fingerprint mismatch, uniqueness conflict, verification bypass, missing MFA for privileged roles, unproven recovery, unsafe official-source conflict or unavailable required Auth0 capability.

## Recommended first implementation phase

Start with **Phase 1 identity foundation only**: add characterization tests, design and rehearse migration 014 on isolated fresh and upgraded databases, safely backfill one identity per existing user, add a compatibility repository that preserves current callback behavior, and prove the five account-to-portfolio relationships and protected four-project metrics are unchanged. Do not enable registration, linking, new login UI, or account reconciliation in this first phase. Return the backfill and migration evidence for owner acceptance before Phase 2.
