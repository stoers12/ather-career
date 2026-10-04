# 06 — Phased implementation plan

**Current status:** Phase 0 documentation completed locally; owner documentation review is next. All later phases are proposed implementation packages, not work performed by this branch. Prefer small independently reviewable commits within each package. Keep open registration disabled until security, privacy, accessibility, recovery, tenant and legal gates pass. [Data plan](04-DATA-MODEL-AND-MIGRATIONS.md) defines 014–017; [test matrix](07-TEST-AND-ACCEPTANCE-MATRIX.md) defines evidence.

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

- **Objective:** Identity foundation.
- **Prerequisites:** Accepted Phase 0; clean baseline; isolated databases.
- **Expected files/components:** includes/auth0_identity.php, includes/auth0_oidc.php, identity repository, database/migrations/014_*.sql, tests/phase2, scripts/run-phase2-tests.php.
- **Data impact:** Additive identity table and safe backfill only.
- **Product behavior:** Current callback behavior preserved; no registration/linking/UI redesign.
- **Threats:** OIDC regression, duplicate identity, cross-owner move.
- **Implementation steps:** Characterization tests; precondition scan; 014; exact current-identity backfill; compatibility repository; isolated fresh/upgraded and repeat-run rehearsal.
- **Test gates:** AUTH-OIDC, AUTH-LINK race, MIGRATION, ownership fingerprints.
- **Recovery point:** Protected backup/isolated restore plan before live migration; old columns retained.
- **Owner approval point:** Accept backfill and compatibility proof before callback cutover.
- **Definition of done:** Every existing binding maps exactly once; five records and all ownership/data fingerprints unchanged.
- **Forbidden actions:** No registration, linking, consolidation, new sign-in UI or destructive schema.
- **Documentation files requiring updates:** 01, 03, 04, 06, 07, 08, CHANGELOG, README.

## Phase 2 — Account and security gate

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
- Compatibility entry routes: owner_login.php, owner_oidc_callback.php, owner_logout.php, owner_onboarding.php and owner.php. Public route shims and web-server routing rules must be checked before adding new endpoints.
- Profile/publication/media: includes/profile_actions.php, includes/portfolio_scoped_data.php, includes/public_lifecycle.php, includes/media_access.php, includes/project_evidence_repository.php, includes/owner_publication_presentation.php, public_portfolio.php, public_projects_json.php, public_media.php, owner_profile.php, owner_projects.php and owner_project_evidence.php.
- Shared presentation: includes/owner_layout.php, includes/owner_form_feedback.php, includes/portfolio_presentation.php, admin.css, admin.js, evidence_hub.css, owner_theme.js, portfolio.css and portfolio.js. Reuse these and established green/gold tokens rather than duplicate controls.
- Delivery: database/migrate.php, tests/phase2/, scripts/run-phase2-tests.php, isolated rehearsal scripts, production smoke checks and .github/workflows/ci.yml. A later implementation commit may need workflow changes after explicit review; this documentation commit changes none.

Migrations are additive first, with no destructive issuer/subject removal during initial rollout. Rehearse fresh and upgraded copies and a repeat run before live migration. Use only sanitized counts/fingerprints for the five owner-created legacy/test account records and protected four-project portfolio. Do not treat project count or email equality as identity proof. A separate owner-approved reconciliation may occur only after identity authentication, portfolio inventory, backup and recovery rehearsal.

Non-production Auth0 validation must cover Google, Microsoft, database connection, verification semantics, password minimum and breach controls, standard logout and chooser, WebAuthn/TOTP, privileged MFA, step-up, adaptive/CAPTCHA entitlements and linking authority. Tenant incapability that would weaken an approved ADR stops the phase for owner review.

Stop immediately on account/portfolio ownership mismatch, protected-project fingerprint drift, verification or MFA bypass, private media/Evidence leak, missing recovery evidence, source conflict or unresolved legal gate. Do not run migration rehearsals on the canonical database. [Operations](08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) defines the future deployment boundary.

## First implementation recommendation

After owner acceptance, Phase 1 only: characterize existing OIDC/session/ownership behavior; create additive migration 014 in a later implementation commit; rehearse exact one-binding-per-existing-user backfill on fresh and upgraded isolated databases; add compatibility repository; prove five records, their portfolios and protected 12/12, 4/4, 27/24/0 aggregates unchanged. Do not enable registration, linking, UI redesign or consolidation.
