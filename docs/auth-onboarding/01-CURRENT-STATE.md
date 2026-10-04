# 01 — Verified current state

Baseline: main at adbd1fd5772f6ce16b4844e29d0c6052e8eda221, documented in Phase 0 on 2026-10-04. “Verified” below means repository behavior and prior sanitized discovery; live tenant settings, upstream provider sessions and Docker identities were not independently verified for this documentation expansion. New architecture is in [ADRs](02-ARCHITECTURE-DECISIONS.md).

**Phase 1 branch distinction:** Local, unpushed `feat/auth-onboarding-phase1` now has additive 014 and an unwired, read-only identity compatibility repository at `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e`. The canonical database remains at ledger 001–013. The existing callback, session, Owner authorization and public routes described below remain their current behavior; no new authentication or registration journey is live.

## Authentication and authorization

**Verified current behavior.** GET owner_login.php redirects directly to Auth0 Universal Login. The authorization-code callback in owner_oidc_callback.php uses PKCE S256, state and nonce and validates signed ID-token signature, issuer, audience and expiry. A successful callback finds the stored issuer/subject or provisions an active local user, rotates the PHP session identifier, and routes an owner with a portfolio toward the Dashboard or to current onboarding. Owner routes use server-side identity and authorization checks. Existing authentication and ownership helpers include includes/auth0_oidc.php, includes/auth0_identity.php, includes/session.php, includes/owner_session.php, includes/authorization.php and includes/owner_flow.php.

**Known limitation.** New subjects become active local users without the approved verified-email, eligibility, consent or five-step gates. Current user lookup has one OIDC issuer/subject pair per user. Legacy Owner URLs must be included in any future gate. Tenant connection settings and real provider outcomes need isolated validation.

**Proposed future behavior.** Identity compatibility first, then verified-email holding, account state, privileged MFA, and five-step onboarding. See [phases](06-IMPLEMENTATION-PLAN.md).

## Sessions, logout and wrong account

**Verified current behavior.** Server-side PHP session; session identifier rotation after authentication; 30-minute idle and 12-hour absolute limit. Current logout clears the local Ather session. Login starts immediately at Auth0 without an Ather chooser.

**Known limitation.** Local logout alone leaves the Auth0 SSO browser session and potentially the Google/Microsoft provider session. A later login can silently reuse the former identity. Ather cannot destroy an upstream provider session. There is no dedicated Use another account journey, bounded long-lived opt-in, or reviewed device-session list/revocation.

**Proposed future behavior.** Ather Sign In, CSRF-protected local plus Auth0 standard logout with an allowlisted return, and a distinct fresh chooser/reauthentication switch path; no federated logout by default. See [flows](05-USER-FLOWS-AND-ROUTES.md).

## Users, ownership and onboarding

**Verified current behavior.** users stores one issuer/subject; ownership is user → one portfolio → scoped profile/projects and related data. Current onboarding is a single portfolio-creation POST. The development database contains **Five owner-created legacy/test account records associated with development use.** They are not independent customers. One portfolio contains four protected projects; its owning account is a canonical candidate only.

**Known limitation.** The model cannot safely represent multiple identities for one account. Email equality is not identity proof. Existing records must not be merged, moved, disabled, archived or deleted as a byproduct of migration. Current onboarding has no private versioned draft, five logical steps or full required-data/consent lifecycle.

**Proposed future behavior.** Add unique issuer/subject identity bindings, explicit account and onboarding states, and private owner-scoped drafts. Legacy reconciliation is a separate owner-approved operation after fresh authentication of each external identity.

## Publication and Evidence

**Verified current behavior.** Portfolio publication has a private-by-default flag. A published portfolio currently exposes its projects through public routes. Evidence Hub fields are owner-editable and not independently public. Profile contact visibility already has a migration-backed setting; project-specific and Evidence-specific public choices do not exist.

**Known limitation.** No per-project visibility or independent Evidence publication state; HTML, JSON and media will need coordinated filters. A public slug or published portfolio cannot be treated as authority for new draft data. Current slug history/change controls are insufficient for the approved future behavior.

**Proposed future behavior.** New portfolios and projects private by default; explicit portfolio/project/Evidence publication and separate contact-type controls; controlled slug history and privacy retirement.

## Privacy, localization and UI

**Verified current behavior.** Owner/public layouts, form feedback helpers, green-and-gold styling and responsive work exist. Relevant files include includes/owner_layout.php, includes/owner_form_feedback.php, includes/portfolio_presentation.php, admin.css, admin.js, owner_theme.js and portfolio.css.

**Known limitation.** No complete application-level Arabic/English translation and RTL/LTR system for the proposed authentication/onboarding journeys. The present form is not the approved five-section experience. Existing Auth0 claims do not establish Ather page accessibility.

**Proposed future behavior.** Locale catalogs and correct lang/dir; mixed-direction isolation; measured keyboard, focus, error, reduced-motion and mobile checks. No visual interface is implemented by this documentation.

## Data and verification boundaries

**Verified current behavior.** Ordered migrations 001–013 exist. Prior sanitized baseline for the protected portfolio was Evidence 12/12 complete, projects 4/4 complete, technologies 27 occurrences, 24 distinct mapped and zero unmapped. Existing tests include OIDC static/session contracts, ownership/migration checks, public lifecycle, Evidence and HTTP smoke support.

**Known limitation.** These aggregate values are regression invariants, not proof of new features. A future live check must use SELECT-only sanitized queries. Auth0 tenant configuration, provider sessions, private data and protected Docker identity cannot be inferred from repository code or open ports.

**Proposed future behavior.** Characterize old behavior with synthetic fixtures before code changes; rehearse migrations on isolated fresh and upgraded databases; compare ownership and protected-content fingerprints before any live write.
