# 05 — Proposed user flows and route contracts

**2026-10-05 status update:** The local Phase 2A candidate changes only the existing Owner callback's identity lookup; see [10](10-PHASE2A-CALLBACK-RESOLUTION.md). Proposed registration, linking, email verification and onboarding routes below remain disabled.

**All routes here are proposals, not implemented filenames.** Existing Owner URLs remain guarded during transition. Each private response is no-store; redirects use fixed allowlists. A route never accepts an owner ID as authorization. Method/URI spelling is subject to route review, while the security contract is binding. State model: anonymous → OIDC pending → unverified holding or verified onboarding → completed private Dashboard → explicit publication. Disabled/restricted states deny protected resources. See [ADRs](02-ARCHITECTURE-DECISIONS.md) and [test IDs](07-TEST-AND-ACCEPTANCE-MATRIX.md).

Migration 014 is deployed with five legacy bindings. The canonical main callback uses the legacy resolver; the isolated Phase 2A candidate uses the read-only repository. Neither phase enables registration, linking or the proposed routes below.

| Flow | Starting state | Action | Proposed route | Authorization | Security checks | State transition | Success | Safe failure | Audit code | Related ADR suffix | Required test |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Landing entry | anonymous | choose account action | GET / | public | safe next allowlist | anonymous→Sign In | clear entry | generic unavailable | ENTRY_VIEW | 001/005 | AUTH-OIDC-001 |
| Sign In | anonymous | choose method | GET /sign-in | public | safe hint; no local password | anonymous→OIDC start | Universal Login | safe retry | SIGNIN_START | 001/005 | AUTH-OIDC-001 |
| Sign Up | anonymous | choose registration | GET /sign-up | public | 18+ notice; rate limit | anonymous→OIDC registration | hosted signup | safe retry | SIGNUP_START | 001/014 | AUTH-OIDC-002 |
| Google | OIDC start | provider selection | GET /auth/start?method=google | anonymous | state/nonce/PKCE/purpose | start→callback | validated identity | safe denial | OIDC_START | 001 | AUTH-OIDC-001 |
| Microsoft | OIDC start | provider selection | GET /auth/start?method=microsoft | anonymous | state/nonce/PKCE/purpose | start→callback | validated identity | safe denial | OIDC_START | 001 | AUTH-OIDC-001 |
| Email/password | OIDC start | hosted database connection | GET /auth/start?method=password | anonymous | no Ather password field | start→callback | validated identity | safe denial | OIDC_START | 001/009 | TENANT-001 |
| Verify holding | authenticated unverified | view/resend/check | GET/POST /verify-email | restricted account | trusted status, CSRF on POST, throttle | unverified→verified | onboarding or Dashboard gate | remain restricted | VERIFY_STATE | 002 | AUTH-EMAIL-001 |
| Returning user | verified completed | callback | GET /auth/callback | one-time OIDC | account/role/authz checks; rotate | callback→Dashboard | authorized Dashboard | holding/denial | LOGIN_SUCCESS | 002/010 | AUTH-OIDC-003 |
| New user | unknown identity | callback | GET /auth/callback | one-time OIDC | unique identity, safe provision | callback→holding/onboarding | private account | safe conflict | ACCOUNT_CREATED | 002/015 | AUTH-OIDC-002 |
| Disabled account | disabled | callback or old Owner URL | GET /auth/callback | OIDC or session | current status deny | disabled→denied | no Owner access | generic denial | ACCOUNT_DENIED | 002 | AUTH-EMAIL-002 |
| Standard logout | signed in | submit logout | POST /logout | session | CSRF; destroy local; Auth0 SSO; allowlist | signed in→anonymous | Sign In | safe local termination | LOGOUT | 004/012 | AUTH-SESSION-001 |
| Use another account | signed in/wrong SSO | switch | POST /use-another-account | session or switch intent | CSRF, rate limit, local+Auth0 clear, chooser | signed in→fresh OIDC | account chooser | safe Sign In | ACCOUNT_SWITCH | 005 | AUTH-SESSION-002 |
| Canceled authentication | OIDC in progress | cancel at provider | GET /auth/callback | one-time transaction | reject error and clear transaction | start→anonymous | Sign In retry | generic error | OIDC_CANCEL | 001 | AUTH-OIDC-004 |
| Identity linking | signed in | step-up then second login | POST /account/identities/link; GET /auth/link/callback | current account | CSRF, fresh proofs, purpose, unique pair | unlinked→linked | same owner | no mutation | IDENTITY_LINK | 003/007 | AUTH-LINK-001 |
| Identity conflict | link pending | second identity belongs elsewhere | GET /auth/link/callback | current account | unique pair; no email merge | pending→unchanged | safe conflict | no transfer | IDENTITY_CONFLICT | 003 | AUTH-LINK-002 |
| MFA | normal or privileged login | enroll/challenge | Auth0 hosted challenge | authenticated transaction | role assurance, safe recovery | challenge→assured | permitted route | deny privileged | MFA_RESULT | 006/008 | AUTH-MFA-001 |
| Step-up | signed in sensitive action | fresh challenge | POST /account/step-up | session | CSRF, purpose/time, rotate | ordinary→recent assurance | resume action | deny action | STEPUP_RESULT | 007 | AUTH-MFA-002 |
| Expired session | expired/revoked | request Owner URL | GET protected URL | none valid | idle/absolute/authz checks | session→anonymous | Sign In safe return | no private output | SESSION_EXPIRED | 010/011 | AUTH-SESSION-003 |
| Onboarding start | verified eligible | open first step | GET /onboarding?step=1 | restricted account | eligibility, terms state, owner scope | ready→drafting | Step 1 of 5 | holding/denial | ONBOARD_START | 014/015 | ONBOARD-UI-001 |
| Autosave | drafting | save active step | POST /onboarding/draft | draft owner | CSRF, allow-list, version, throttle, idempotency | vN→vN+1 | Saved/version | validated error | DRAFT_SAVED | 016 | ONBOARD-DRAFT-001 |
| Back navigation | drafting | return previous | GET /onboarding?step=n | draft owner | authorized saved read | drafting→drafting | restored answers | safe retry | DRAFT_VIEW | 015/016 | ONBOARD-DRAFT-002 |
| Resume onboarding | verified incomplete | return | GET /onboarding | draft owner | account status and version | drafting→current step | saved values | holding/denial | DRAFT_RESUME | 016 | ONBOARD-DRAFT-002 |
| Stale conflict | draft version stale | write | POST /onboarding/draft | draft owner | compare expected version | unchanged | 409 and reload/merge | no lost write | DRAFT_CONFLICT | 016 | ONBOARD-DRAFT-003 |
| Private preview | drafting | preview | GET /onboarding/preview | draft owner | sanitize; no-store; no public slug | draft unchanged | private preview | deny anonymous | PREVIEW_VIEW | 016/020 | PRIVACY-001 |
| Final review | step 5 | review/change | GET /onboarding/review | draft owner | all answers visible only to owner | drafting→review | change links | validation summary | ONBOARD_REVIEW | 015/016 | ONBOARD-UI-002 |
| Dashboard arrival | review complete | confirm create | POST /onboarding/complete | draft owner | CSRF, full atomic validation, idempotency | review→completed private | Dashboard | rollback transaction | ONBOARD_COMPLETE | 016/020 | ONBOARD-DRAFT-004 |
| First project prompt | completed private | choose add project | GET /owner/projects/new | owner | current account/portfolio | completed→same | optional project editor | safe denial | PROJECT_PROMPT | 015 | ONBOARD-UI-003 |
| Publication | private portfolio/project | publish chosen scope | POST /account/publication | owner | CSRF, explicit intent, visibility predicates | private→selected public | authorized public route | remain private | PUBLISH | 020 | PRIVACY-002 |
| Evidence visibility | project owner | choose evidence public | POST /projects/{id}/evidence-visibility | owner | CSRF, project ownership, independent flag | Evidence private→public | filtered read | remain private | EVIDENCE_VISIBILITY | 021 | PRIVACY-003 |
| Slug change | profile owner | reserve new slug | POST /account/slug | owner | CSRF, reserved/history unique, cooldown | old→new/history | safe redirect | conflict no change | SLUG_CHANGE | 022 | PRIVACY-004 |
| Account deletion | signed in | request delete | POST /account/delete | owner + step-up | CSRF, fresh MFA, retention/legal check | active→restricted/deletion | reviewed workflow | safe denial | DELETE_REQUEST | 007/023 | PRIVACY-005 |
| Consent withdrawal | signed in | withdraw | POST /account/consent/withdraw | owner | CSRF, versioned event, restriction | accepted→withdrawn/restricted | rights workflow | safe denial | CONSENT_WITHDRAW | 023 | PRIVACY-006 |

**Wrong-account distinction:** Ather local session, Auth0 SSO session and upstream Google/Microsoft browser sessions differ. Standard logout clears the first two without federated provider logout. The switch route asks for a fresh chooser or reauthentication but cannot promise upstream session deletion. A privacy-safe remembered hint never grants authority.

**Linking distinction:** Link a newly proven issuer/subject to the currently authenticated local account only after step-up and fresh second authentication. If unique binding belongs elsewhere, reject with no portfolio or ownership move. Never use email equality. Auth0-side linking, if used, requires a separately reviewed authority and conflict model.

**Publication distinction:** Private preview is authenticated and never indexed or served via public slug. Portfolio, project, Evidence and contact-type visibility are independent; a project publish does not publish Evidence. Public HTML, JSON and media use the same predicates. Slug history normally redirects only for published content, with retirement for safety/privacy.

**Account rights:** Deletion and withdrawal routes represent request/controlled workflows pending counsel-approved retention and recovery details; do not implement destructive automatic deletion from this table alone.
