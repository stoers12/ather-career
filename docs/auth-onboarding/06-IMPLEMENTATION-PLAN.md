# 06 — Phased implementation plan

**2026-10-09 UTC completion update:** Phase 1 is deployed with ledger 001–014. Phase 2A is merged, deployed and accepted; Phase 2B account switching is permanently finalized through PR #7 at `82353dc2d2a709858d0b56852182acce20f06009`. The broader Phase 2 account/security gate remains incomplete. Landing, full registration/verification/recovery journeys, MFA, identity linking and five-step onboarding remain incomplete. Migrations 015–017 remain proposals. Historical local-only statuses and phase plans below are not current deployment instructions.

**Historical Phase 1 review status (2026-10-04; superseded by the status update above):** Phase 1 identity foundation was committed locally at `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e` and reviewed against the approved architecture. The six-file implementation is unpushed and undeployed. 014 passed isolated rehearsal; the canonical ledger remains 001–013. Phases 2–7 remain proposed and blocked by their own gates. Keep open registration disabled until security, privacy, accessibility, recovery, tenant and legal gates pass. [Data plan](04-DATA-MODEL-AND-MIGRATIONS.md) distinguishes implemented 014 from proposed 015–017; [test matrix](07-TEST-AND-ACCEPTANCE-MATRIX.md) records scoped evidence.

## Phase 0 — Architecture documentation

- **Objective:** Architecture documentation.
- **Prerequisites:** Clean main baseline and owner decisions.
- **Expected files/components:** docs/auth-onboarding/* only.
- **Data impact:** None.
- **Product behavior:** No feature change.
- **Threats:** Unreviewed assumption, unsafe source conflict.
- **Implementation steps:** Record ADRs, current state, routes, threats, tests, recovery and references; owner review.
- **Test gates:** Link/ID/privacy/diff checks; official-source review.
- **Recovery point:** Existing main commit and no runtime change.
- **Owner approval point:** Owner architecture/documentation acceptance.
- **Definition of done:** Eleven indexed documents and local commit reviewed.
- **Forbidden actions:** No application, migration, tenant or data write.
- **Documentation files requiring updates:** All eleven, especially README and CHANGELOG.

## Phase 1 — Identity foundation

**Completion note (2026-10-09 UTC):** Phase 1 subsequently merged and 014 was deployed using the protected recovery procedure; Phase 2A callback cutover and owner acceptance are also complete. The local-verification/recovery bullets below describe the original Phase 1 review boundary, not outstanding work to redeploy 014.

- **Objective:** Identity foundation.
- **Prerequisites:** Accepted Phase 0; clean baseline; isolated databases.
- **Implemented files/components:** `database/identity_foundation.php`, `database/migrate.php`, `database/migrations/014_identity_foundation.sql`, `includes/identity_repository.php`, and two isolated rehearsal scripts. Existing OIDC/callback/session files and Phase 2 tests remain unchanged.
- **Data impact:** Additive identity table and safe backfill only.
- **Product behavior:** Current callback behavior preserved; no registration/linking/UI redesign.
- **Threats:** OIDC regression, duplicate identity, cross-owner move.
- **Implementation steps:** Characterization tests; precondition scan; 014; exact current-identity backfill; compatibility repository; isolated fresh/upgraded and repeat-run rehearsal.
- **Test gates:** AUTH-OIDC, AUTH-LINK race, MIGRATION, ownership fingerprints.
- **Recovery point:** Verified local Git recovery bundle and isolated upgraded-copy rehearsal; protected live database/media backup and restore are still required before any live migration. Old columns remain.
- **Owner approval point:** Accept backfill and compatibility proof before callback cutover.
- **Local verification:** Fresh and upgraded 001–013 copies, malformed/conflicting input, interrupted DDL and repeat-run no-op passed; five existing bindings map to original users with unchanged ownership/protected fingerprints. Live deployment and callback cutover remain separate gates.
- **Forbidden actions:** No registration, linking, consolidation, new sign-in UI or destructive schema.
- **Documentation files requiring updates:** 01, 03, 04, 06, 07, 08, CHANGELOG, README.

## Phase 2 — Account and security gate

**Completed slices (2026-10-09 UTC):** [2A](10-PHASE2A-CALLBACK-RESOLUTION.md) accepts only known exact bindings; [2B](11-PHASE2B-ACCOUNT-SELECTION.md) supplies local switching, callback recovery and signed-out retry, strict switch input, deployed CSP redirect support and finalized `prompt=login` for switch/retry. Ordinary login remains unprompted. Owner switch/return acceptance and exact-merge CI are recorded in [13](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md#finalization-evidence-2026-10-09-utc). The following broader objectives/definition of done remain open, including two-session standard logout, verified-email holding, privileged MFA and step-up. Callback recovery here is not completion of account/password recovery journeys.

- **Objective:** Account and security gate.
- **Prerequisites:** Accepted 014; non-production Auth0 tenant; role/recovery decisions.
- **Expected files/components:** database/migrations/015_*.sql, includes/authorization.php, includes/owner_session.php, includes/session.php, includes/owner_flow.php, includes/csrf.php, owner_login.php, owner_oidc_callback.php, owner_logout.php, guarded Owner routes.
- **Data impact:** Add account/verification/role/assurance/consent structure; no open signup.
- **Product behavior:** Verification holding, Ather Sign In, standard logout, switch, bounded sessions, privileged MFA.
- **Threats:** Bypass through old URL, wrong-account reuse, session theft.
- **Implementation steps:** Implement gates, role checks, step-up hooks, local+Auth0 logout, switch and safe errors; validate provider behavior.
- **Test gates:** AUTH-EMAIL, AUTH-SESSION, AUTH-MFA, AUTH-ABUSE, TENANT.
- **Recovery point:** Pre-change code ref, 015 backup/restore rehearsal and safe feature toggle.
- **Owner approval point:** Accept tenant results, roles, session and legacy transition before registration.
- **Definition of done:** No unverified/disabled/incomplete or privileged bypass; session/logout controls verified.
- **Forbidden actions:** No public registration, identity linking or legacy consolidation.
- **Documentation files requiring updates:** 01–09 as affected, CHANGELOG, README.

## Phase 3 — Consent and server-side onboarding state

- **Objective:** Consent and server-side onboarding state.
- **Prerequisites:** Accepted verification/security gate; policy versions and eligibility review.
- **Expected files/components:** database/migrations/016_*.sql, includes/profile_actions.php, draft repository, onboarding endpoints, owner_onboarding.php.
- **Data impact:** Private drafts and required profile fields; versioned consent from 015.
- **Product behavior:** Five logical sections API, Back/resume, atomic private completion; UI may still be basic.
- **Threats:** Mass assignment, stale writes, duplicate completion, preview leak.
- **Implementation steps:** Allow-listed active-step saves, CSRF, optimistic version, idempotency, full final transaction and private preview endpoint.
- **Test gates:** ONBOARD-DRAFT, PRIVACY, MIGRATION, cross-owner tests.
- **Recovery point:** Isolated restore after 016; no partial DDL auto-drop.
- **Owner approval point:** Accept field map, policy text and draft privacy before UI rollout.
- **Definition of done:** Resumable private draft and atomic completion without publication or first project.
- **Forbidden actions:** No localStorage drafts, auto-publication or mandatory optional fields.
- **Documentation files requiring updates:** 01, 03–08, CHANGELOG, README.

## Phase 4 — Bilingual authentication/onboarding UI

- **Objective:** Bilingual authentication/onboarding UI.
- **Prerequisites:** Accepted backend contracts; Arabic/English content and design review.
- **Expected files/components:** includes/owner_layout.php, includes/owner_form_feedback.php, includes/portfolio_presentation.php, admin.css, admin.js, owner_theme.js, locale catalogs, new auth/onboarding presentation routes.
- **Data impact:** None beyond owner-scoped drafts.
- **Product behavior:** Sign In/Sign Up/Verify and five sections with private live preview; green/gold preserved.
- **Threats:** RTL spoofing, focus loss, responsive privacy leak.
- **Implementation steps:** Reuse shared helpers; render lang/dir, mixed-text isolation, saved/conflict/error states and mobile layout.
- **Test gates:** ONBOARD-UI, A11Y, I18N, responsive and tenant customization checks.
- **Recovery point:** Revert presentation slice; preserve drafts.
- **Owner approval point:** Owner accepts bilingual content, measured accessibility and privacy review.
- **Definition of done:** Keyboard/screen-reader/reduced-motion/mobile verification passes.
- **Forbidden actions:** No duplicate design system, client-side sensitive draft storage or public preview.
- **Documentation files requiring updates:** 02, 03, 05–09, CHANGELOG, README.

## Phase 5 — Explicit identity management

- **Objective:** Explicit identity management.
- **Prerequisites:** Accepted 014/015 gates, step-up and recovery policy; Auth0 plan verified.
- **Expected files/components:** identity repository, includes/auth0_oidc.php, includes/auth0_identity.php, account identity routes, security audit, tests.
- **Data impact:** New links only; no portfolio move.
- **Product behavior:** User-initiated link/unlink and per-device review/revocation design.
- **Threats:** Email auto-link, duplicate race, last-method lockout.
- **Implementation steps:** Purpose-bound second auth, step-up, unique conflict handling, audit and recovery rehearsal.
- **Test gates:** AUTH-LINK, AUTH-MFA, AUTH-SESSION, TENANT.
- **Recovery point:** Feature flag off; preserve original identities and restore plan.
- **Owner approval point:** Accept conflict/recovery evidence; separate approval for legacy reconciliation.
- **Definition of done:** Both identities proven; unique binding and last-method rules enforced.
- **Forbidden actions:** No email-based merge, account consolidation or raw subject logging.
- **Documentation files requiring updates:** 02–09, CHANGELOG, README.

## Phase 6 — Project and Evidence publication

- **Objective:** Project and Evidence publication.
- **Prerequisites:** Accepted legacy published-project rule and public field scope.
- **Expected files/components:** database/migrations/017_*.sql, includes/public_lifecycle.php, includes/media_access.php, includes/project_evidence_repository.php, includes/portfolio_scoped_data.php, public_portfolio.php, public_projects_json.php, public_media.php, owner publication routes.
- **Data impact:** Project/Evidence flags, contact type and slug history; protected backfill.
- **Product behavior:** Explicit project/Evidence/contact publication and controlled slug changes.
- **Threats:** Private media/Evidence/contact leak; legacy publication regression.
- **Implementation steps:** Add 017; use common public predicate for HTML/JSON/media; atomic slug reservation/history/retirement.
- **Test gates:** PRIVACY, MIGRATION, SMOKE and protected fingerprints.
- **Recovery point:** Protected backup and isolated restore; rollback/forward repair per 017.
- **Owner approval point:** Accept legacy visibility inventory/backfill and public route evidence.
- **Definition of done:** New private defaults and separate Evidence choice proven on all surfaces.
- **Forbidden actions:** No automatic Evidence publish or silent legacy visibility change.
- **Documentation files requiring updates:** 01–09, CHANGELOG, README.

## Phase 7 — Open-registration rollout and monitoring

- **Objective:** Open-registration rollout and monitoring.
- **Prerequisites:** All prior owner gates; counsel review; non-production tenant validation; recovery rehearsal.
- **Expected files/components:** feature flags/config, production smoke scripts, operations and monitoring configuration.
- **Data impact:** Live new accounts/drafts after approved rollout.
- **Product behavior:** Flagged internal → owner → limited beta → public beta.
- **Threats:** Abuse, legal/privacy failure, inaccessible flow, rollback gaps.
- **Implementation steps:** Check readiness, enable cohorts, observe safe event codes, exercise rollback triggers and rights workflows.
- **Test gates:** TENANT, SMOKE, ROLLBACK, A11Y, I18N and security matrix.
- **Recovery point:** Protected recovery ref/bundle/database backup and proven isolated restore.
- **Owner approval point:** Explicit owner launch acceptance and qualified legal/privacy sign-off.
- **Definition of done:** Gates pass, monitoring owner named, rollback tested, no protected-data drift.
- **Forbidden actions:** No public beta before legal and security acceptance; no secret-bearing logs.
- **Documentation files requiring updates:** All eleven; README and CHANGELOG mandatory.

## Cross-phase dependencies and stops

### Existing component inventory for implementation review

- OIDC/session/authorization: includes/auth0_oidc.php, includes/auth0_identity.php, includes/session.php, includes/owner_session.php, includes/authorization.php, includes/owner_flow.php, includes/csrf.php, includes/rate_limit.php, includes/security_events.php, includes/http.php and includes/runtime_readiness.php.
- Compatibility entry routes: owner_login.php, owner_switch_account.php, owner_auth_retry.php, owner_oidc_callback.php, owner_logout.php, owner_onboarding.php and owner.php. Public route shims and web-server routing rules must be checked before adding new endpoints.
- Profile/publication/media: includes/profile_actions.php, includes/portfolio_scoped_data.php, includes/public_lifecycle.php, includes/media_access.php, includes/project_evidence_repository.php, includes/owner_publication_presentation.php, public_portfolio.php, public_projects_json.php, public_media.php, owner_profile.php, owner_projects.php and owner_project_evidence.php.
- Shared presentation: includes/owner_layout.php, includes/owner_form_feedback.php, includes/portfolio_presentation.php, admin.css, admin.js, evidence_hub.css, owner_theme.js, portfolio.css and portfolio.js. Reuse these and established green/gold tokens rather than duplicate controls.
- Delivery: database/migrate.php, tests/phase2/, scripts/run-phase2-tests.php, isolated rehearsal scripts, production smoke checks and .github/workflows/ci.yml. A later implementation commit may need workflow changes after explicit review; this documentation commit changes none.

Migrations are additive first, with no destructive issuer/subject removal during initial rollout. Rehearse fresh and upgraded copies and a repeat run before live migration. Use only sanitized counts/fingerprints for the five owner-created legacy/test account records and protected four-project portfolio. Do not treat project count or email equality as identity proof. A separate owner-approved reconciliation may occur only after identity authentication, portfolio inventory, backup and recovery rehearsal.

Non-production Auth0 validation must cover Google, Microsoft, database connection, verification semantics, password minimum and breach controls, standard logout and chooser, WebAuthn/TOTP, privileged MFA, step-up, adaptive/CAPTCHA entitlements and linking authority. Tenant incapability that would weaken an approved ADR stops the phase for owner review.

Stop immediately on account/portfolio ownership mismatch, protected-project fingerprint drift, verification or MFA bypass, private media/Evidence leak, missing recovery evidence, source conflict or unresolved legal gate. Do not run migration rehearsals on the canonical database. [Operations](08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) defines the future deployment boundary.

## Next safe action

The four original approved design references have been recovered from `Ather_Approved_Designs_2026-10-04.zip`; README and approval-evidence.json were read and all four sizes/SHA-256 hashes verified. The Landing source is `ather-landing-high-fidelity.html`, approved 2026-10-04 at 20:01:49 Asia/Amman (green/gold; not the earlier young-man/falling-stars concept). Next: complete the documentation follow-up, then implement Landing only in an isolated worktree from approved main; retain originals unchanged and compare desktop/mobile in both themes. No merge or deployment is authorized. Phase 2A/2B finalization requires no further owner browser attempt, temporary-patch alignment, migration or deployment. Broader account/security and registration/onboarding work retains its own gates; Auth0 capability testing, plan constraints and legal/privacy review remain separate. Do not upgrade, delete or mutate the tenant from this record.
