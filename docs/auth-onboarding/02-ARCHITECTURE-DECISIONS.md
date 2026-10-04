# 02 — Architecture decision records

These records convert the owner-approved Phase 0 decisions into stable IDs. Approval date for every ADR: **2026-10-04**. “Approved” means architecture approval, not implemented behavior or legal approval. Official source IDs resolve in [the reference register](09-OFFICIAL-REFERENCES.md). Where a source informs a product choice, that choice is an **engineering inference**, not an external mandate. Supersede an ADR explicitly and update the threat model, tests and changelog in the same commit.

## AUTH-ADR-001 — Auth0 Universal Login and initial methods

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** The existing Owner OIDC boundary already uses hosted login.
- **Decision:** Use Universal Login with Google, Microsoft and Auth0 email/password; Ather stores no passwords. Preserve code+PKCE S256, state, nonce, signature, issuer, audience and expiry validation, with purpose-bound transactions.
- **Rationale:** Limits credential handling while retaining the validated OIDC boundary.
- **Alternatives considered:** Ather password forms; implicit flow.
- **Security and privacy effects:** Credential and callback attack surface stays at Auth0/OIDC boundary; Ather still protects local authorization.
- **Implementation effects:** Non-production connection and callback tests before activation.
- **Supporting official references:** S1, S10, S10B; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Auth0 protocol/connection change.

## AUTH-ADR-002 — Verified email before Dashboard

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** Current local provision creates active access without an email gate.
- **Decision:** Use trusted connection-specific email verification, restricted holding state and gate every Owner route; editable profile email is never authority.
- **Rationale:** Prevents an unverified address from conferring full account readiness.
- **Alternatives considered:** Let a callback or profile email imply verification.
- **Security and privacy effects:** Holding state exposes no Owner resources; resend is throttled.
- **Implementation effects:** Add verification provenance, claim refresh and old-route guard.
- **Supporting official references:** S2; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Provider claim semantics or recovery change.

## AUTH-ADR-003 — Proven multi-identity linking

- **Status:** Approved engineering; Auth0-plan dependent. **Approval date:** 2026-10-04.
- **Context:** Current users allow one issuer/subject; email matching is ambiguous.
- **Decision:** Allow multiple unique issuer/subject bindings only after current-account authentication, recent step-up and fresh second-identity authentication; reject conflicts and protect last usable method.
- **Rationale:** Both identities must be controlled by the same actor.
- **Alternatives considered:** Automatic link by matching email; database row reassignment.
- **Security and privacy effects:** Prevents takeover and cross-owner movement; audit safe codes only.
- **Implementation effects:** Add identity table, purpose-bound one-time transaction, race tests and Auth0/local authority review.
- **Supporting official references:** S3, S10B; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Linking capability or authority model changes.

## AUTH-ADR-004 — Standard local and Auth0 logout

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** Current logout ends only PHP session.
- **Decision:** CSRF-protected POST invalidates Ather session and clears Auth0 SSO, returning to allowlisted Sign In; no federated provider logout by default.
- **Rationale:** Closes the two sessions Ather controls without disrupting wider provider use.
- **Alternatives considered:** Local-only logout; default federated logout.
- **Security and privacy effects:** Upstream Google/Microsoft browser session may persist; never promise otherwise.
- **Implementation effects:** Review Auth0 logout endpoint/return allowlist and failure fallback.
- **Supporting official references:** S4, S11; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Auth0 logout semantics change.

## AUTH-ADR-005 — Use another account

- **Status:** Approved product; provider behavior dependent. **Approval date:** 2026-10-04.
- **Context:** Direct Auth0 entry can reselect the previous SSO identity.
- **Decision:** Offer separate switch action that ends local/Auth0 sessions and requests chooser or fresh reauthentication; only show a privacy-safe remembered hint.
- **Rationale:** Makes wrong-account recovery explicit.
- **Alternatives considered:** Rely on standard logout alone; force federated logout.
- **Security and privacy effects:** Hint is never proof and must be suppressed where unsafe.
- **Implementation effects:** Test each connection in non-production; rate-limit switch requests.
- **Supporting official references:** S4; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Chooser/remembered-hint review.

## AUTH-ADR-006 — Role-sensitive MFA

- **Status:** Approved engineering; plan dependent. **Approval date:** 2026-10-04.
- **Context:** Privileged actions need stronger assurance than ordinary access.
- **Decision:** Encourage optional MFA for normal users; require MFA for administrator and support roles. Adaptive MFA only if the chosen Auth0 plan supports it.
- **Rationale:** Risk-based launch policy with stronger privileged boundary.
- **Alternatives considered:** Mandatory MFA for all at initial beta; SMS-only MFA.
- **Security and privacy effects:** No privileged route may bypass assurance; normal-user assurance classification needs beta review.
- **Implementation effects:** Define roles, assignment/recovery and assurance check on old and new routes.
- **Supporting official references:** S5, S6, S9; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Role model, assurance target or plan changes.

## AUTH-ADR-007 — Recent step-up for sensitive actions

- **Status:** Approved engineering; freshness value unresolved. **Approval date:** 2026-10-04.
- **Context:** A long-lived session alone is insufficient for security changes.
- **Decision:** Require fresh authentication for link/unlink, authentication-email and security-setting changes, account deletion and privileged actions; rotate session ID afterward.
- **Rationale:** Reduces impact of an unattended or stolen session.
- **Alternatives considered:** Use ordinary session age alone.
- **Security and privacy effects:** Purpose-bound freshness and audit prevent privilege confusion.
- **Implementation effects:** Specify freshness window and Auth0 evidence before enabling actions.
- **Supporting official references:** S6, S9; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Sensitive-action list or assurance level changes.

## AUTH-ADR-008 — WebAuthn/passkeys and TOTP

- **Status:** Approved engineering; plan dependent. **Approval date:** 2026-10-04.
- **Context:** Factors vary in phishing resistance and accessibility.
- **Decision:** Prefer WebAuthn/passkeys; offer TOTP alternative; SMS is not primary.
- **Rationale:** Supports stronger and usable MFA choices.
- **Alternatives considered:** SMS as default; single factor option.
- **Security and privacy effects:** Recovery and enrollment must avoid weaker bypass.
- **Implementation effects:** Validate selected Auth0 plan, device recovery and accessible flows.
- **Supporting official references:** S5, S5B; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Factor availability or recovery model changes.

## AUTH-ADR-009 — Auth0 database password policy

- **Status:** Approved engineering; connection dependent. **Approval date:** 2026-10-04.
- **Context:** Email/password is one of the initial connections.
- **Decision:** For single-factor password authentication target at least 15 characters; permit managers, paste and autofill; avoid composition/periodic-change rules; enable breached-password protection. Pause launch if tenant cannot enforce policy.
- **Rationale:** Follows current NIST single-factor guidance without duplicating password handling.
- **Alternatives considered:** Ather-side password validator/storage; arbitrary complexity rules.
- **Security and privacy effects:** No password or reset secret enters Ather logs or database.
- **Implementation effects:** Verify tenant configuration using disposable non-production identities.
- **Supporting official references:** S6, S7; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** NIST revision or enforcement limitation.

## AUTH-ADR-010 — Server-side sessions and bounded lifetime

- **Status:** Approved engineering; durations risk-based. **Approval date:** 2026-10-04.
- **Context:** Current PHP session is 30-minute idle/12-hour absolute.
- **Decision:** Keep auth state server side, Secure/HttpOnly/appropriate SameSite cookies, rotation at login and step-up, standard 30-minute idle/12-hour absolute; privileged no weaker and MFA-bound.
- **Rationale:** Limits theft and stale authorization.
- **Alternatives considered:** Browser token storage; unbounded session.
- **Security and privacy effects:** No auth tokens, refresh tokens, session IDs or sensitive drafts in browser storage; future device revocation.
- **Implementation effects:** Recheck expiry, authz version, cookie flags and secure proxy handling.
- **Supporting official references:** S6, S8, S9; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Threat/assurance or storage change.

## AUTH-ADR-011 — Opt-in Keep me signed in

- **Status:** Approved provisional beta default. **Approval date:** 2026-10-04.
- **Context:** Some users need bounded continuity.
- **Decision:** Offer opt-in 7-day idle/30-day absolute session, never substituting for recent step-up; plan per-device review/revocation.
- **Rationale:** Balances convenience and exposure by bounded limits.
- **Alternatives considered:** Permanent session; mandatory long session.
- **Security and privacy effects:** Shared-device and stolen-device risk remains; clear opt-in semantics.
- **Implementation effects:** Design server-side registry, revocation and renewal tests.
- **Supporting official references:** S8, S9; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Risk assessment or telemetry changes.

## AUTH-ADR-012 — CSRF protection

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** Cookie authentication remains vulnerable to cross-site state changes.
- **Decision:** Keep CSRF tokens on every state-changing route; SameSite is defense in depth, not replacement.
- **Rationale:** Protects logout, drafts, linking and publication.
- **Alternatives considered:** SameSite-only defense.
- **Security and privacy effects:** Reject missing/invalid token even with authenticated cookie.
- **Implementation effects:** Reuse existing CSRF helper and test old/new routes.
- **Supporting official references:** S11; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Session/cookie design changes.

## AUTH-ADR-013 — Risk-based abuse protection

- **Status:** Approved engineering; plan dependent. **Approval date:** 2026-10-04.
- **Context:** Registration, resend and drafts introduce abuse surfaces.
- **Decision:** Keep application limits; add switch, resend, link, recovery and draft-write limits. Verify Auth0 attack and breached-password protection; use risk-based bot challenge/CAPTCHA where supported.
- **Rationale:** Avoids blanket friction while bounding automation.
- **Alternatives considered:** CAPTCHA on every request; provider-only controls.
- **Security and privacy effects:** Generic errors limit enumeration; safe event codes support monitoring.
- **Implementation effects:** Tenant validation and synthetic rate-limit tests.
- **Supporting official references:** S7; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Abuse evidence or tenant capability changes.

## AUTH-ADR-014 — Provisional 18+ public beta

- **Status:** Provisional product; legal review dependency. **Approval date:** 2026-10-04.
- **Context:** Earlier 16+ proposal lacks completed legal/privacy review.
- **Decision:** Launch beta for attested ages 18+ without exact birth date. Ages 16–17 are deferred pending verified parent or legal-guardian consent, age-appropriate privacy information, minimization, withdrawal/deletion handling, a documented legal basis and qualified Jordanian counsel.
- **Rationale:** Precautionary launch boundary, not a legal conclusion.
- **Alternatives considered:** Launch at 16+ now; collect exact birth dates.
- **Security and privacy effects:** Age attestation has residual misstatement risk; counsel must assess legal basis.
- **Implementation effects:** Version eligibility event and block ineligible onboarding.
- **Supporting official references:** S15, S15B; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Qualified legal advice or launch audience change.

## AUTH-ADR-015 — Five logical onboarding sections

- **Status:** Approved product. **Approval date:** 2026-10-04.
- **Context:** Current onboarding is a single POST.
- **Decision:** Use account readiness, public identity, professional snapshot, public profile identity, review/create; visible position and Back; optional shorter question pages; first project after Dashboard.
- **Rationale:** Small relevant questions improve comprehension.
- **Alternatives considered:** One long form; first project inside onboarding.
- **Security and privacy effects:** All draft sections remain private; no automatic publish.
- **Implementation effects:** Define step allow-lists and atomic final review.
- **Supporting official references:** S12, S13; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Required fields or research changes.

## AUTH-ADR-016 — Private server draft and atomic completion

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** Autosave must survive navigation without browser-sensitive storage.
- **Decision:** Owner-scoped CSRF-protected allow-listed writes, active-step validation, debounce, expected version and idempotency; full atomic final validation; clear saving/stale/failure states.
- **Rationale:** Prevents lost work, races and duplicate creation.
- **Alternatives considered:** LocalStorage drafts; whole-form validation on each save.
- **Security and privacy effects:** Private preview and draft boundary; no mass assignment.
- **Implementation effects:** Migration 016, repositories, transaction and conflict tests.
- **Supporting official references:** S11, S12; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Draft storage or transactional boundary changes.

## AUTH-ADR-017 — Minimal required personal data

- **Status:** Approved product; legal review for notices. **Approval date:** 2026-10-04.
- **Context:** Broad identity collection is unnecessary for first profile.
- **Decision:** Require flexible display name, ISO country, preferred language, professional status, specialization, headline, slug and account-readiness attestations. Do not require father name, second phone, legal-name parts, gender, exact birth date or address.
- **Rationale:** Supports onboarding with less personal data.
- **Alternatives considered:** Collect all possible profile facts up front.
- **Security and privacy effects:** Smaller private-data footprint and fewer breach consequences.
- **Implementation effects:** Map existing profile fields carefully; defer optional fields.
- **Supporting official references:** S12, S15; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Launch purpose or counsel advice changes.

## AUTH-ADR-018 — Optional private phone

- **Status:** Approved product. **Approval date:** 2026-10-04.
- **Context:** A phone is not currently justified as required recovery data.
- **Decision:** Phone is optional, private by default and not a login/recovery factor without a separate documented need; contact visibility is per type.
- **Rationale:** Avoids public exposure and unnecessary collection.
- **Alternatives considered:** Mandatory primary/secondary phone.
- **Security and privacy effects:** Phone access must be owner-scoped and excluded from public routes.
- **Implementation effects:** Add contact-type visibility and export/deletion handling.
- **Supporting official references:** S15; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Documented recovery need or counsel advice.

## AUTH-ADR-019 — Arabic/English and measured accessibility

- **Status:** Approved engineering. **Approval date:** 2026-10-04.
- **Context:** Ather serves mixed-language professional content.
- **Decision:** Use catalogs, correct document lang/dir, dir=auto or bdi for user text, keyboard/focus/error-summary/screen-reader/reduced-motion/mobile checks. Test Ather customizations independently.
- **Rationale:** Direction and accessibility cannot be inferred from hosted-login claims.
- **Alternatives considered:** English-only launch; rely on Auth0 claim alone.
- **Security and privacy effects:** Mixed-direction content and error feedback require tests.
- **Implementation effects:** Reuse layouts/form helpers and green/gold tokens; measured validation.
- **Supporting official references:** S13, S13B, S14; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Locale scope or accessibility target changes.

## AUTH-ADR-020 — Private-by-default portfolios and projects

- **Status:** Approved product. **Approval date:** 2026-10-04.
- **Context:** Portfolio flag exists; published portfolio currently exposes all projects.
- **Decision:** New portfolio and project private by default; only explicit authorized owner action publishes. Public HTML, JSON and media enforce the same state.
- **Rationale:** Prevents accidental disclosure.
- **Alternatives considered:** Publish at onboarding completion; inherit public state blindly.
- **Security and privacy effects:** Backfill existing published projects only by approved rule.
- **Implementation effects:** Migration 017 and coordinated public projections.
- **Supporting official references:** S10; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Legacy publication decision changes.

## AUTH-ADR-021 — Independent Evidence visibility

- **Status:** Approved product. **Approval date:** 2026-10-04.
- **Context:** Evidence tells sensitive work stories and is owner-editable.
- **Decision:** Public Evidence requires a separate per-project choice; publishing a project never publishes Evidence. Only authorized owner edits.
- **Rationale:** Separates portfolio/project discovery from evidence disclosure.
- **Alternatives considered:** Evidence inherits project publication.
- **Security and privacy effects:** Public projection may include only approved fields/media.
- **Implementation effects:** Migration 017 and consistent HTML/JSON/media tests.
- **Supporting official references:** S10; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Evidence field scope changes.

## AUTH-ADR-022 — Controlled public slug history

- **Status:** Approved product; exact policy unresolved. **Approval date:** 2026-10-04.
- **Context:** A public handle can change but old links may expose identity.
- **Decision:** Reserve atomically, block system names, rate-limit changes, preserve history, normally permanent-redirect old public slugs, and allow safety/privacy retirement. Draft slug never publishes.
- **Rationale:** Maintains link continuity with privacy escape.
- **Alternatives considered:** Immutable slug; reusable historical slug.
- **Security and privacy effects:** Old-handle exposure remains until retirement.
- **Implementation effects:** Define cooldown/status and redirect policy before UI.
- **Supporting official references:** S10; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Redirect/cooldown/privacy review.

## AUTH-ADR-023 — Versioned consent and safe privacy operations

- **Status:** Approved engineering; legal dependency. **Approval date:** 2026-10-04.
- **Context:** Terms and notice change over time; private data needs lifecycle handling.
- **Decision:** Record versioned acceptance/acknowledgement and timestamps; plan access, correction, withdrawal, deletion, restriction, objection and export. Log opaque IDs/event codes, never secrets, raw subjects or private bodies.
- **Rationale:** Creates auditable boundaries without retaining unnecessary fields.
- **Alternatives considered:** Single mutable acceptance flag; verbose request logging.
- **Security and privacy effects:** Counsel must determine legal basis/retention and rights handling.
- **Implementation effects:** Migration 015, safe audit schema and operations review.
- **Supporting official references:** S15, S15B; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Policy version, counsel advice or retention change.

## AUTH-ADR-024 — Preserve five legacy/test accounts

- **Status:** Approved owner correction. **Approval date:** 2026-10-04.
- **Context:** Development records were all created/used by one owner.
- **Decision:** Preserve all five; the four-project account is only a canonical candidate. Reconcile separately after each external identity is freshly authenticated and owner confirms portfolio disposition; never merge by email.
- **Rationale:** Avoids losing protected work or assigning identity incorrectly.
- **Alternatives considered:** Automatic merge; choose by project count or email.
- **Security and privacy effects:** No cross-owner moves in migrations or linking.
- **Implementation effects:** Separate approved backup, inventory, rehearsal and fingerprinted reconciliation.
- **Supporting official references:** S3; see [source register](09-OFFICIAL-REFERENCES.md).
- **Review triggers:** Separate reconciliation authorization.

## Explicit boundaries and non-goals

This architecture does not implement new routes, migrations, UI, Auth0 settings or account consolidation. AI recommendations and recruiter/company accounts remain future capabilities. The 18+ boundary is precautionary and subject to counsel; adaptive MFA, exact linking and bot features depend on Auth0 plan/connection behavior. Unresolved owner choices include role assignment and admin recovery, step-up freshness, remembered-hint retention, terms/notice versions, legacy account transition, published-project backfill, Evidence public field scope, slug cooldown and retirement, and device-session storage. Stop implementation when an official source or tenant capability contradicts an approved security decision; request a new reviewed ADR rather than silently weakening it.

Review this record before each rollout gate and when Auth0, NIST, OWASP, W3C or Jordanian legal guidance materially changes.
