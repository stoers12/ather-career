# Ather authentication and onboarding architecture decisions

Status: owner-approved architecture for phased implementation. Documented 2026-10-04. This document defines product and engineering direction; it does not claim that the features are implemented or that Ather has obtained legal compliance advice. The implementation plan is in `AUTH_ONBOARDING_IMPLEMENTATION_PLAN.md`.

## Purpose and scope

Ather is a professional platform, initially focused on students and graduates in Jordan. It helps people preserve and present the story, decisions, role, evidence, lessons, and impact behind their work. This decision record covers registration, authentication, account linking, sessions, five-section onboarding, publication privacy, and Arabic/English operation. AI recommendations and recruiter/company accounts are future capabilities.

**Source notation:** `[S#]` refers to the official-source register below. A source requirement is labeled as such. An **engineering inference** is a design choice made from the source and Ather's approved product requirements; it is not attributed to the source as a mandate. Sources were accessed 2026-10-04. Auth0 feature availability and exact tenant behavior must be verified in a non-production tenant before rollout.

## Current state and product principles

- The current Owner GET sign-in route redirects straight to Auth0 Universal Login. The callback validates authorization code, PKCE S256, state, nonce, signed ID token, issuer, audience and expiry; it creates an active local user when the subject is new. The current session is a server-side PHP session with rotation after authentication, a 30-minute idle limit and a 12-hour absolute limit. Current logout ends only the Ather session.
- A user has one stored OIDC issuer/subject on `users`, one owned portfolio, and a portfolio-private-by-default publication flag. Current onboarding is a single portfolio-creation POST. A published portfolio currently exposes every project through public routes; projects and Evidence have no independent public state.
- The live development database has **Five owner-created legacy/test account records associated with development use.** They are not five independent customers. No account is canonical merely because a portfolio contains four protected projects or because identity attributes look alike.
- Preserve the established green and gold Ather identity. Keep required data minimal, drafts private, publication explicit, accessibility measurable, and authentication authority separate from editable profile fields.

## Numbered decisions

### ADR-01 — Auth0 owns authentication and passwords

Use Auth0 Universal Login with Google, Microsoft, and an Auth0 database email/password connection. Ather does not collect, validate, hash, or store passwords. Preserve the current authorization-code protections: PKCE, state, nonce, exact issuer/audience, signature and expiry checks. Add purpose binding to transactions used for sign-in, step-up, switching and linking. **Rationale:** a hosted authentication boundary reduces local credential handling while preserving the existing validated OIDC boundary. Auth0 documents Universal Login and supported connection types [S1]; OWASP ASVS documents OIDC verification concerns [S10]. The transaction-purpose extension is an engineering inference.

### ADR-02 — Verified-email holding gate

An authenticated user whose email is not verified has a restricted verification-holding state, not Dashboard authority. Every Owner route must enforce the gate, including old filenames and direct URLs. Extend the current `openid` request with the minimum email scope/claims needed for this check, then validate the verification signal from a trusted Auth0 connection-specific result or fresh provider-side check; editable `personal_info.email` is never authentication authority. Verification resend is rate limited. **Rationale:** verified address possession is required for Ather's account readiness, while Auth0 warns that email alone is not proof of identity [S2]. A restricted local holding state is an engineering inference to support a usable verification journey.

### ADR-03 — One local account may have multiple proven identities

Use a future `user_identities` table with a unique `(issuer, subject)` binding to a local `user_id`. A link requires the authenticated existing account, recent step-up, fresh authentication of the second identity, a one-time purpose-bound transaction, conflict rejection if that identity belongs elsewhere, and safe security audit events. Unlinking also requires step-up and must not remove the last usable authentication method. Matching email may help find a candidate but must never authorize linking. **Rationale:** Auth0 explicitly advises authentication of both accounts for manual linking and notes plan-dependent availability [S3]. The local uniqueness, last-method guard and audit design are engineering inferences. Auth0-side linking, if adopted, must be reconciled with local identity authority and primary-user semantics before enabling it.

### ADR-04 — Sign-in, logout and wrong-account recovery

Show an Ather Sign In page before Auth0. It may show a safely remembered account hint, without exposing private account data to a shared browser; the hint is never an authentication credential. Standard logout is a CSRF-protected POST that invalidates Ather session state and invokes Auth0 SSO logout with an allowlisted anonymous Sign In return URL. It does not request federated Google or Microsoft logout by default. “Use another account” clears Ather and Auth0 sessions and requests a chooser or fresh authentication. Ather cannot guarantee removal of an upstream provider browser session. **Rationale:** Auth0 distinguishes application, Auth0 SSO and IdP sessions [S4]. The separate account-switch action and safe hint are Ather product choices.

### ADR-05 — MFA and recent authentication

Offer and encourage MFA for normal users. Require it for administrator and support roles. Require fresh step-up for identity linking/unlinking, authentication-email changes, security settings, account deletion and privileged actions. Prefer WebAuthn/passkeys and support TOTP; SMS is not the primary method. Adaptive MFA is conditional on the chosen Auth0 plan [S5]. **Rationale:** stronger assurance is warranted for elevated and irreversible actions; OWASP ASVS requires full reauthentication for sensitive account attributes [S9]. Optional MFA for normal users is a product risk choice, not a claim that Ather meets an ASVS L2 or NIST AAL2 target for every normal session. Revisit that classification before public beta.

### ADR-06 — Auth0 database password policy

For a password used as the only factor, target at least **15 characters** in the Auth0 database connection and document tenant enforcement. Permit password managers, autofill and paste; avoid character-class composition rules and arbitrary periodic changes; enable breached-password and common-password protections. If Auth0 cannot enforce the required policy in the chosen configuration, pause the email/password launch for a new decision. Ather duplicates none of this validation. **Rationale:** NIST SP 800-63B-4 requires a 15-character minimum for single-factor passwords, allows an 8-character minimum only when the password is part of MFA, rejects composition rules, and supports password managers [S6]. Auth0 documents breached-password protection [S7].

### ADR-07 — Bounded server-side sessions

Keep identity state server side; store no authentication token, refresh token, session ID or sensitive onboarding draft in browser storage. Keep production cookies Secure, HttpOnly and appropriately SameSite. Keep CSRF tokens on state-changing requests; SameSite is defense in depth. Rotate the session ID after initial authentication and step-up. Beta defaults: standard 30-minute idle/12-hour absolute; opt-in “Keep me signed in” 7-day idle/30-day absolute. Privileged sessions have no weaker limits than standard sessions and require MFA. Future account settings should show and revoke device sessions. **Rationale:** OWASP recommends session renewal after privilege changes and coordinated SSO/session limits [S8, S9]; OWASP treats SameSite as additional CSRF protection [S11]. These exact durations are Ather risk-based defaults, not universal numbers. The 30-day convenience session must not stand in for fresh privileged or sensitive-action authentication; NIST describes differing reauthentication expectations by assurance level [S6].

### ADR-08 — Layered abuse defense

Retain application rate limits and add bounded limits for switching, verification resend, linking, recovery and draft writes. Verify Auth0 brute-force, suspicious-IP, bot-detection and breached-password settings. Use risk-based CAPTCHA/challenges where supported; do not challenge every ordinary request without evidence. Return safe errors and avoid account enumeration. **Rationale:** Auth0 documents these attack-protection options and risk-triggered CAPTCHA [S7]. Scope-specific application limits are an engineering inference.

### ADR-09 — Precautionary 18+ public beta

Require an 18+ eligibility attestation for the first public beta without collecting exact date of birth. This is a precautionary launch decision, **not a legal conclusion**. Ages 16–17 are a future capability only after qualified Jordanian legal/privacy review and a design for verified parent or guardian consent, age-appropriate notice, data minimization, withdrawal, deletion and a documented legal basis. **Rationale:** minimizing young-user data and delaying a legally sensitive capability reduces launch risk. Jordan's official Personal Data Protection Law publication is a legal-review input [S15], not a substitute for counsel. The earlier 16+ proposal is superseded by this owner decision.

### ADR-10 — Five logical onboarding sections

Use the five sections in the matrix below, with visible position such as “Step 2 of 5.” A section may use shorter question pages. Require only the listed account-readiness and profile-identity facts. Optional city, university, graduation year, biography, skills, links, image, Arabic/English name variants and phone belong after onboarding. The first project is added after Dashboard arrival. **Rationale:** short, relevant questions and easy Back navigation support comprehension and mobile use [S12, S13]. The five-section content is an Ather product decision.

### ADR-11 — Private server-side draft and atomic completion

Autosave owner-scoped drafts to the server with CSRF validation, a writable-field allow-list, active-step validation, expected version/optimistic concurrency and idempotency for duplicate submissions. Debounce client requests; restore saved values on return; provide Saving, Saved, stale-version and failure feedback. At final confirmation, validate all required answers and create/update the private portfolio and profile atomically. Never publish automatically. **Rationale:** GOV.UK recommends designing form structure around needed questions and supports incremental saving [S12]. The concurrency and transaction boundaries are engineering inferences for Ather's PHP/MySQL architecture.

### ADR-12 — Arabic, English and measured accessibility

Establish an application locale and translation catalog. Set document `lang` and `dir` correctly; isolate mixed-direction user content with `dir="auto"`, `bdi` or an equivalent safe strategy. Preserve keyboard operation, visible focus, labeled controls, focused error summaries, reduced-motion behavior and responsive layouts. Verify Ather pages and Auth0 customizations independently; Universal Login accessibility statements alone do not prove Ather conformance. **Rationale:** W3C explains direction handling for unknown-direction text [S14]; GOV.UK specifies Back links and focused error summaries for forms [S13].

### ADR-13 — Privacy, publication, contact and Evidence

New portfolios stay private, and new projects default private once per-project state is added. Publication is an explicit owner action. Contact visibility is separate by type and phone is private by default. Evidence remains owner-editable only; any public Evidence requires its own per-project visibility choice. Publishing a project does not publish its Evidence. Public HTML, JSON and media must apply identical visibility rules. **Rationale:** these rules implement Ather's privacy-by-default product boundary. The route-consistency requirement is an engineering inference from the current public read architecture and OWASP access-control verification principles [S10].

### ADR-14 — Controlled public-slug history

Allow controlled slug changes with atomic reservation, reserved-name protection, a cooldown or comparable abuse limit, and a historical-slug record. Normally redirect old public slugs permanently to the current published slug. A documented safety/privacy process may retire an old redirect. A reserved slug never publishes a draft. **Rationale:** continuity of public links and abuse resistance are product choices. Privacy retirement is necessary because an old public handle may itself disclose a connection the owner no longer wants public; exact redirect status and retirement rules need implementation review.

### ADR-15 — Data minimization, consent and safe logs

Record Terms acceptance and Privacy Notice acknowledgement with version and timestamp. Plan authenticated access, correction, withdrawal, deletion, restriction, objection and export workflows, subject to legal review. Do not log passwords, tokens, session IDs, raw subjects, draft bodies, private Evidence or unnecessary personal fields. Internal opaque IDs and bounded event codes are acceptable for security logs. Phone is optional, private and not a login/recovery factor without a separately documented need. Do not require father's name, second phone, legal name components, gender, exact birth date or home address. **Rationale:** minimized collection and private defaults are approved Ather choices; Jordan law and implementing rules require counsel review before public launch [S15].

## Account and onboarding state model

```text
anonymous
  -> Auth0 transaction in progress
  -> authenticated / email unverified -> verification holding only
  -> email verified / eligibility or consent incomplete -> onboarding step 1
  -> onboarding draft (steps 1–5, resumable and private)
  -> full validation + explicit create -> private Dashboard
  -> explicit owner publication -> public portfolio

disabled or invalid identity -> denied
link pending -> current local account retained; no ownership change until proof succeeds
logout -> local session ended -> Auth0 SSO ended -> anonymous Sign In
```

Every protected Owner route checks current account status, authorization version, verification, consent/eligibility, onboarding completion and role assurance as applicable. A portfolio row alone does not mean onboarding is complete. Publication state is independent from account and onboarding state.

| Step | Required fields/action | Optional or deferred | Storage responsibility |
| --- | --- | --- | --- |
| 1. Account readiness | Verified-email status, 18+ attestation, versioned Terms acceptance, versioned Privacy Notice acknowledgement | No date of birth | Trusted identity status; account eligibility and consent records |
| 2. Public identity | Flexible display/professional name, ISO-backed country, preferred language | Arabic/English name variants, city | Professional profile and locale preference |
| 3. Professional snapshot | Current professional status, field/specialization, short headline | University and graduation year, biography | Professional profile or later education record |
| 4. Public profile identity | Public slug, visibility explanation, private sanitized preview, separate contact controls | Image, phone, links, skills | Portfolio slug; explicit contact visibility settings |
| 5. Review and create | Review, change links, explicit confirmation; private profile and portfolio creation/update | First project follows on Dashboard | Atomic onboarding completion and private profile state |

## Identity, logout and privacy boundaries

- Ather session, Auth0 SSO session and upstream Google/Microsoft sessions have separate lifetimes. Clearing the first two does not assert control over the third.
- A remembered account hint may identify the last chosen account only where safe; it grants no authority and must be omitted on shared-device uncertainty. Its storage and retention need a separate privacy design.
- Linking and legacy reconciliation are distinct. Routine linking is user-initiated for proven identities; consolidation of existing portfolios requires an owner-approved operation with backup, row inventory and recovery.
- A private draft and an unpublished portfolio return no public profile, project, media or Evidence representation, even if a slug is reserved.
- Published Evidence is a filtered read projection; writes always derive the authorized Owner context. The current Evidence Hub content and recommendation data are not modified by this phase.

## Legacy/test account treatment

**Five owner-created legacy/test account records associated with development use.** Preserve all five records and their portfolios. The portfolio containing four protected projects is only a candidate for canonical selection until the owner authenticates the relevant external identities and confirms ownership. Do not merge, relink, disable, archive or delete an account from email similarity, project count or this document. A later owner-approved reconciliation must separately inventory and protect dependent data.

## Non-goals and open approvals

This phase does not implement routes, Auth0 configuration, schema changes, account consolidation, visual screens, public Evidence, AI recommendations or recruiter/company accounts. It does not publish a profile or change existing contact visibility.

Before implementation or beta, resolve: exact role assignment and administrator recovery; recent-step-up freshness; supported Auth0 plan and linking method; non-production validation of provider chooser and email verification semantics; remembered-account hint retention; policy text and versions; existing-account transition into new onboarding gates; slug cooldown and redirect retirement policy; session-storage/revocation design; existing published-project backfill rule; public Evidence field scope; and qualified Jordanian legal/privacy review. A decision that cannot be enforced by the chosen Auth0 plan or connection must return for owner review rather than silently weakening this record.

Review this document when a material Auth0, NIST, OWASP or Jordanian legal source changes, when a threat model or launch audience changes, and before each rollout gate. Record changed decisions in a new reviewed revision; do not silently amend owner-approved security boundaries.

## Official-source register

All sources accessed **2026-10-04**. These references support the indicated decisions; they are not a claim that Ather is certified against a standard or legally compliant.

| ID | Title and organization | URL | Supports |
| --- | --- | --- | --- |
| S1 | Auth0 Universal Login — Auth0 | https://auth0.com/docs/authenticate/login/auth0-universal-login | ADR-01 |
| S2 | Verify Emails using Auth0 — Auth0 | https://auth0.com/docs/manage-users/user-accounts/verify-emails | ADR-02 |
| S3 | Link User Accounts — Auth0 | https://auth0.com/docs/manage-users/user-accounts/user-account-linking/link-user-accounts | ADR-03 |
| S4 | Log Users Out of Applications — Auth0 | https://auth0.com/docs/authenticate/login/logout/log-users-out-of-applications | ADR-04 |
| S5 | Adaptive MFA; WebAuthn as Multi-Factor Authentication — Auth0 | https://auth0.com/docs/secure/multi-factor-authentication/adaptive-mfa ; https://auth0.com/docs/secure/multi-factor-authentication/webauthn-as-mfa | ADR-05 |
| S6 | SP 800-63B-4, Authentication and Authenticator Management — National Institute of Standards and Technology | https://pages.nist.gov/800-63-4/sp800-63b.html | ADR-06, ADR-07 |
| S7 | Attack Protection — Auth0 | https://auth0.com/docs/secure/attack-protection | ADR-06, ADR-08 |
| S8 | Session Management Cheat Sheet — OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html | ADR-07 |
| S9 | ASVS 5.0 V7 Session Management — OWASP | https://github.com/OWASP/ASVS/blob/master/5.0/en/0x16-V7-Session-Management.md | ADR-05, ADR-07 |
| S10 | ASVS 5.0 V6 Authentication and V10 OAuth/OIDC — OWASP | https://github.com/OWASP/ASVS/blob/master/5.0/en/0x15-V6-Authentication.md ; https://github.com/OWASP/ASVS/blob/master/5.0/en/0x19-V10-OAuth-and-OIDC.md | ADR-01, ADR-13 |
| S11 | Cross-Site Request Forgery Prevention Cheat Sheet — OWASP | https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html | ADR-07 |
| S12 | Structuring forms — GOV.UK Service Manual | https://www.gov.uk/service-manual/design/form-structure | ADR-10, ADR-11 |
| S13 | Question pages and Error summary — GOV.UK Design System | https://design-system.service.gov.uk/patterns/question-pages/ ; https://design-system.service.gov.uk/components/error-summary/ | ADR-10, ADR-12 |
| S14 | Structural markup and right-to-left text in HTML — W3C Internationalization | https://www.w3.org/International/questions/qa-html-dir | ADR-12 |
| S15 | Personal Data Protection Law No. (24) of 2023, official translation and legal-publication index — Jordan Ministry of Digital Economy and Entrepreneurship | https://www.modee.gov.jo/EBV4.0/Root_Storage/AR/11/PDP_Law_-_English_Version1.pdf ; https://modee.gov.jo/EN/List/The_law_regulations_and_instructions | ADR-09, ADR-15; counsel review only |
