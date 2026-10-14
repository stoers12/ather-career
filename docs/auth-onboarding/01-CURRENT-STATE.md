# 01 — Verified current state

**Current baseline (2026-10-09 UTC):** Approved main `82353dc2d2a709858d0b56852182acce20f06009`. Phase 2A is merged, deployed and accepted; Phase 2B account switching is permanently finalized through PR #7. Ledger 001–014 and five exact bindings remain. See [finalization evidence](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md#finalization-evidence-2026-10-09-utc) and [ADRs](02-ARCHITECTURE-DECISIONS.md).

**Historical baseline and supersession:** Phase 0 described main `adbd1fd5772f6ce16b4844e29d0c6052e8eda221` on 2026-10-04. Phase 1 at `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e` was then local, unpushed and unwired, with canonical ledger 001–013. The 2026-10-05 update recorded deployed 014 but a local Phase 2A candidate and legacy main callback. Those statuses are superseded by the completed 014 deployment and accepted Phase 2A/2B rollouts; they do not describe current behavior.

## Authentication and authorization

**Verified current behavior.** GET owner_login.php redirects directly to Auth0 Universal Login. The authorization-code callback in owner_oidc_callback.php uses PKCE S256, state and nonce and validates signed ID-token signature, issuer, audience and expiry. A successful callback requires the deployed Migration 014 table and resolves the exact primary issuer/subject binding through `findCompatibleIdentityUser`, cross-checking both legacy identity columns and active account/authorization-version state. Unknown, missing, conflicting or disabled identities fail closed; no user is provisioned or linked. It rotates the PHP session identifier and routes by current ownership to the Dashboard or current onboarding. Callback failure now renders the sanitized bilingual recovery page in a fresh anonymous session; only a valid, one-time `access_denied` response is classified as cancellation/denial. Owner routes use server-side identity and authorization checks. Existing authentication and ownership helpers include includes/auth0_oidc.php, includes/auth0_identity.php, includes/session.php, includes/owner_session.php, includes/authorization.php and includes/owner_flow.php.

**Known limitation.** New subjects are denied. The approved verified-email, eligibility, consent, MFA and five-step gates are still absent. The current callback accepts only an exact primary binding consistent with the legacy pair; a multi-identity linking journey is not implemented. Legacy Owner URLs must be included in any future gate. Tenant connection settings and real provider outcomes need isolated validation.

**Proposed future behavior.** Build verified-email holding, readiness/assurance state, privileged MFA and five-step onboarding on the deployed exact-binding foundation. See [phases](06-IMPLEMENTATION-PLAN.md).

## Sessions, logout and wrong account

**Verified current behavior.** Server-side PHP session; session identifier rotation after authentication; 30-minute idle and 12-hour absolute limit. Current logout clears the local Ather session. Login starts immediately at Auth0 without an Ather chooser.

**Known limitation.** Local logout alone leaves the Auth0 SSO browser session and potentially the Google/Microsoft provider session. A later login can silently reuse the former identity. Ather cannot destroy an upstream provider session. The implemented **Use another account** POST and signed-out recovery retry request `prompt=login`; ordinary login remains unprompted. The switch rejects query fields, uploads, extra POST fields and malformed CSRF before session retirement, checks the active User, rate limit and discovery, retires the local session, and starts fresh state/nonce/PKCE. Retry also requires CSRF and a signed-out session. Neither route calls an Auth0 or provider logout endpoint. Bounded long-lived opt-in and device-session list/revocation remain unimplemented.

**Proposed future behavior.** Ather Sign In and CSRF-protected local plus Auth0 standard logout with an allowlisted return; no federated logout by default. The accepted local switch/retry slice supplies reauthentication intent, while the broader two-session logout design remains open. See [flows](05-USER-FLOWS-AND-ROUTES.md).

## Users, ownership and onboarding

**Verified current behavior.** `user_identities` holds five exact primary bindings and `users` retains its legacy pair for consistency checks; ownership is user → one portfolio → scoped profile/projects and related data. Current onboarding is a single portfolio-creation POST. The development database contains **Five owner-created legacy/test account records associated with development use.** They are not independent customers. One portfolio contains four protected projects; its owning account is a canonical candidate only.

**Known limitation.** The current resolver requires a primary binding matching the legacy pair; the schema foundation alone does not enable multiple login methods for one account. Email equality is not identity proof. Existing records must not be merged, moved, disabled, archived or deleted as a byproduct of migration. Current onboarding has no private versioned draft, five logical steps or full required-data/consent lifecycle.

**Proposed future behavior.** Extend the deployed unique issuer/subject foundation with reviewed multi-identity behavior, explicit readiness/onboarding states and private owner-scoped drafts. Legacy reconciliation is a separate owner-approved operation after fresh authentication of each external identity.

## Publication and Evidence

**Verified current behavior.** Portfolio publication has a private-by-default flag. A published portfolio currently exposes its projects through public routes. Evidence Hub fields are owner-editable and not independently public. Profile contact visibility already has a migration-backed setting; project-specific and Evidence-specific public choices do not exist.

**Known limitation.** No per-project visibility or independent Evidence publication state; HTML, JSON and media will need coordinated filters. A public slug or published portfolio cannot be treated as authority for new draft data. Current slug history/change controls are insufficient for the approved future behavior.

**Proposed future behavior.** New portfolios and projects private by default; explicit portfolio/project/Evidence publication and separate contact-type controls; controlled slug history and privacy retirement.

## Privacy, localization and UI

**Verified current behavior.** Owner/public layouts, form feedback helpers, green-and-gold styling and responsive work exist. Relevant files include includes/owner_layout.php, includes/owner_form_feedback.php, includes/portfolio_presentation.php, admin.css, admin.js, owner_theme.js and portfolio.css.

**Known limitation.** No complete application-level Arabic/English translation and RTL/LTR system for the proposed authentication/onboarding journeys. The present form is not the approved five-section experience. Existing Auth0 claims do not establish Ather page accessibility.

**Proposed future behavior.** Locale catalogs and correct lang/dir; mixed-direction isolation; measured keyboard, focus, error, reduced-motion and mobile checks. No visual interface is implemented by this documentation.

## Data and verification boundaries

**Verified current behavior.** Ordered migrations and canonical ledger are 001–014; 015–017 remain proposals. Prior sanitized baseline for the protected portfolio was Evidence 12/12 complete, projects 4/4 complete, technologies 27 occurrences, 24 distinct mapped and zero unmapped. Existing tests include OIDC static/session contracts, ownership/migration checks, public lifecycle, Evidence and HTTP smoke support.

**Known limitation.** These aggregate values are regression invariants, not proof of new features. The saved finalization evidence reports unchanged tables 10/10, private-storage files 13/13 and matching runtime hashes. Four project originals match recovered historical hashes; the fifth current image reference is a profile original, with no older profile baseline claimed. This documentation review did not repeat database or application rehearsals. A future live check must use SELECT-only sanitized queries. Auth0 tenant configuration, provider sessions, private data and protected Docker identity cannot be inferred from repository code or open ports.

**Proposed future behavior.** Characterize old behavior with synthetic fixtures before code changes; rehearse migrations on isolated fresh and upgraded databases; compare ownership and protected-content fingerprints before any live write.
