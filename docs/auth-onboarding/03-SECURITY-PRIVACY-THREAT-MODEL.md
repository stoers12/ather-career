# 03 — Security and privacy threat model

**Implemented-control update (2026-10-09 UTC):** Accepted Phase 2A requires exact primary 014 issuer/subject bindings consistent with legacy columns and active account/authz-version checks; unknown identities fail closed without creation or email linking. Finalized Phase 2B preserves those checks and OIDC validation, CSRF, rate limits, discovery, local session retirement/rotation and owner-derived portfolio access while using `prompt=login` for switch/retry only. Ordinary login remains unprompted. Callback recovery is a sanitized no-store page in a fresh anonymous session. This records scoped controls for TM-05/07/09/10/11/12/15/16, not completion of every mitigation in those rows. Standard Auth0 logout, readiness/role/assurance gates and the other future controls below remain open; see [acceptance scope](07-TEST-AND-ACCEPTANCE-MATRIX.md).

**Scope:** Proposed authentication, registration, account management, onboarding, profile publication and related public routes. This is a design threat model, not proof of controls. [ADRs](02-ARCHITECTURE-DECISIONS.md) define approved choices; [tests](07-TEST-AND-ACCEPTANCE-MATRIX.md) define gates. Revisit on every route/schema/tenant change.

**Historical Phase 1 control status (before protected deployment):** Migration 014 provides an exact binary issuer/subject unique constraint and a read-only compatibility lookup that cross-checks the legacy binding. It does not integrate with the callback, link accounts, change sessions or authorize portfolios. Its isolated failure and recovery tests are recorded in [07](07-TEST-AND-ACCEPTANCE-MATRIX.md). No Auth0 tenant capability or live recovery control was proven by this slice.

## Assets, actors and trust boundaries

Protected assets: external identity bindings, account/role/verification state, server sessions, OIDC transactions, draft answers, profile/contact data, portfolio/project/Evidence/media content, consent events, slug history and audit events. Actors: anonymous visitor, normal account holder, administrator, support account, Auth0, Google/Microsoft identity providers, and an attacker controlling a browser, request body or another local account.

- **Authentication boundary:** Auth0 authenticates; callback validates code/PKCE/state/nonce and token properties. An email string is not an identity binding.
- **Authorization boundary:** Ather reloads account status, role, assurance, verification and authorization version; Owner IDs come from the session, never request parameters.
- **Auth0 boundary:** Ather can request Auth0 SSO logout, but cannot guarantee upstream provider logout. Plan features and claims require non-production verification.
- **Session boundary:** Secure/HttpOnly/SameSite cookie identifies server-side state; rotation, expiry, CSRF and revocation remain independent checks.
- **Portfolio ownership boundary:** User → owned portfolio → projects/Evidence/media, with owner-scoped joins and no cross-owner reassignment in ordinary linking.
- **Publication boundary:** Public reads require explicit portfolio, project, Evidence and contact-type decisions; draft preview never grants public authority.
- **Onboarding-draft boundary:** Per-owner private server data, field allow-list, versioned writes, CSRF, no-store responses and atomic completion.

## Threat register

Each mitigation is a future requirement unless [current state](01-CURRENT-STATE.md) explicitly verifies it. Phase numbers follow [implementation plan](06-IMPLEMENTATION-PLAN.md).

| ID / threat | Affected asset | Entry point | Possible effect | Mitigation | Verification | Remaining risk | Phase |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TM-01 — Account enumeration | Account existence | Sign In/resend/recovery | Targeted attacks | Generic responses, bounded timing, limits | Different known/unknown fixtures | Timing side channel remains | 2/7 |
| TM-02 — Brute force | Credential authority | Auth0 database login | Compromise | Auth0 attack/breached-password controls; app limits | Non-production tenant attack tests | Provider detection gaps | 2/7 |
| TM-03 — Bot abuse | Availability, drafts | Sign-up, resend, writes | Cost and spam | Risk-based challenge, per-action limits | Burst/normal synthetic traffic | Distributed sources | 2/3/7 |
| TM-04 — Phishing | User identity | Hosted-login entry | Credential theft | Universal Login, clear Ather origin, passkey preference | Non-production journey review | External phishing remains | 2/4 |
| TM-05 — Session fixation | Local session | Callback/step-up | Session takeover | Rotate ID, invalidate old ID | Old/new session replay | Stolen live cookie remains | 2 |
| TM-06 — Session theft | Local session | Browser cookie | Account access | Secure HttpOnly SameSite, bounded lifetime, revocation plan | Cookie flags, expiry and revoke tests | Compromised endpoint | 2/5 |
| TM-07 — CSRF | All owner writes | Logout, draft, link, publish | Unauthorized mutation | CSRF tokens plus SameSite | Cross-site POST denial | Client-side XSS can bypass | 2–6 |
| TM-08 — XSS | Session and draft | Preview/user text | Session action or leak | Context escaping, sanitized preview, CSP review | Malicious text fixtures and browser tests | Third-party script risk | 3/4 |
| TM-09 — Open redirect | Login continuity | Return URL, slug redirect | Phishing/token redirection | Fixed allowlist and normalized targets | External/protocol-relative redirect cases | Configuration error | 2/6 |
| TM-10 — Token leakage | OIDC tokens | Callback, logs, browser storage | Identity compromise | Server-only handling; no secret logs/storage | Storage and log scan | Memory/runtime compromise | 2 |
| TM-11 — OIDC transaction confusion | Login/link authority | State/nonce/PKCE callback | Wrong account bound | One-time purpose-bound transaction and exact checks | Replay, mismatch, expiry tests | Provider integration regression | 1/5 |
| TM-12 — Wrong-account login | Owner context | Auth0 SSO reuse | Wrong portfolio access | Ather Sign In, safe hint, explicit switch | Google/Microsoft chooser rehearsal | Upstream provider may still reuse | 2 |
| TM-13 — Unsafe identity linking | User ownership | Link callback | Account takeover | Current account + step-up + fresh second identity; no email linking | Email-equal different-owner test | Recovery complexity | 5 |
| TM-14 — Duplicate link race | Identity uniqueness | Concurrent callbacks | Identity assigned twice | Unique issuer/subject and transactional conflict | Concurrent insert fixture | MySQL deadlock retry policy | 1/5 |
| TM-15 — Cross-account access | Account/private data | Owner route parameters | Private data disclosure | Derive owner from session; current status/version checks | Two-owner direct-ID tests | Missed legacy route | 1–7 |
| TM-16 — Cross-portfolio access | Projects, Evidence, media | Project/media IDs | Unauthorized edit/read | Owner-scoped joins and media policy | Two-portfolio HTML/JSON/media tests | New route drift | 1/6 |
| TM-17 — Mass assignment | Profile/security state | Draft/body fields | Privilege or visibility change | Writable allow-list and type validation | Inject role, owner, published fields | Future field additions | 3 |
| TM-18 — Stale draft write | Onboarding answers | Concurrent tabs | Lost data | Expected version, 409 conflict and restore | Two-tab version test | User conflict resolution burden | 3/4 |
| TM-19 — Repeated submission | Profile/portfolio | Complete/link POST | Duplicate state | Idempotency key and unique constraints | Replay same request | Interrupted response ambiguity | 3/5 |
| TM-20 — Public preview leak | Private draft | Preview URL/cache | Unpublished data disclosed | Owner auth, no-store, sanitized HTML, no public index | Anonymous/cross-owner/cache tests | Browser screenshots | 3/4 |
| TM-21 — Private media leak | Images/files | Media and CDN route | Private image disclosure | Same visibility predicate for media and HTML/JSON | Anonymous media matrix | Cache propagation delay | 6 |
| TM-22 — Evidence exposure | Work evidence | Project publication/API | Sensitive Evidence public | Independent per-project Evidence choice, private default | Publish project without Evidence tests | Owner may intentionally publish | 6 |
| TM-23 — Contact exposure | Phone and links | Public profile/API | Unwanted contact disclosure | Per-type visibility, phone private | Public projection and export tests | User-selected disclosure | 3/6 |
| TM-24 — Sensitive logging | Tokens and PII | Error/audit pipelines | Secondary disclosure | Opaque IDs, safe event codes, redaction and retention | Log scan with synthetic markers | Infrastructure logs outside app | 2–7 |
| TM-25 — Privileged-role abuse | Admin/support authority | Admin routes | Broad compromise | Mandatory MFA, step-up, least privilege, auditable role assignment | Privileged bypass and revoke tests | Insider risk | 2 |
| TM-26 — Consent withdrawal | Policy state | Account/privacy action | Unlawful processing continuity | Versioned event, restricted state and reviewed workflow | Withdrawal and access-control tests | Legal interpretation | 3/7 |
| TM-27 — Deletion/retention failure | All personal data | Delete/export/recovery | Data persists improperly | Inventory dependencies, retention policy, tested recovery boundaries | Deletion/export isolated fixture | Backup retention complexity | 5/7 |
| TM-28 — Mixed-direction text | Identity/UI integrity | Arabic/English user content | Spoofing or unreadable controls | lang/dir, isolation, safe escaping | Mixed script, punctuation and screen-reader tests | External copied text | 4 |

## Review and stop conditions

Treat verification bypass, raw token/subject logging, cross-owner access, accidental private publication, missing privileged MFA, unsafe identity conflict handling and failed protected-data fingerprints as release blockers. Legal questions about ages 16–17, consent, rights and retention require qualified Jordanian counsel; this table is not legal advice. Do not weaken a mitigation because a tenant feature is unavailable: return the affected ADR for owner review.
