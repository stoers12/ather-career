# 07 — Test and acceptance matrix

**2026-10-09 UTC acceptance update:** Phase 2A is merged, deployed and accepted after two intentional signed Owner callbacks. Phase 2B account switching is permanently finalized through PR #7. The owner switched to another known account with no projects and returned to Momen with four projects. Main CI run 37979181209 passed at exact merge `82353dc2d2a709858d0b56852182acce20f06009`. The saved agent finalization reports matching runtime hashes, three healthy services, unchanged tables 10/10 and storage files 13/13; four project originals match historical recovery hashes and the fifth reference is a profile original with no older profile baseline claimed. See [evidence, review exception and limits](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md#finalization-evidence-2026-10-09-utc).

This is scoped callback and switch/return acceptance, not a blanket pass of the matrix. AUTH-SESSION-002 has accepted owner evidence for the tested known-account journey, not a complete Google/Microsoft provider matrix; AUTH-SESSION-001's Auth0 logout, the full registration/verification/recovery journeys, MFA, linking and five-step onboarding remain open. Browser History corroborates `prompt=login` but is not a complete Network trace. Copilot failed before analysis with HTTP 402; Sourcery was skipped; neither passed. The owner exception applies solely to PR #7 at head `d00198329c7443ea613f4f1f2ed59ff71d8b834e`, not this or future PRs. Historical test evidence below is preserved; no application/database rehearsals were rerun for this documentation update.

**Status:** requirements with scoped completion evidence above and historical Phase 1 evidence below, not a blanket pass. Stable IDs must not be reused for different behavior. “Blocker” means the row must pass before the listed phase can be accepted or before public beta where the phase is 7. Existing tests in tests/phase2 include OIDC static/session contract, tenant authorization, ownership migration, public lifecycle and Evidence checks; these characterize parts of current behavior but do not satisfy new gates. Use synthetic fixtures and redact result artifacts.

| ID | Requirement | Layer | Fixture/environment | Expected result | Phase | Blocking status | Evidence required |
| --- | --- | --- | --- | --- | --- | --- | --- |
| AUTH-OIDC-001 | Google/Microsoft code+PKCE, state, nonce, signature, issuer, audience, expiry and purpose | Integration | mocked OIDC provider | Only valid one-time transaction authenticates | 1/2 | Blocker | callback assertions and traces without tokens |
| AUTH-OIDC-002 | New identity stays gated; hosted signup; no email auto-link | Integration | synthetic new identities | Private account; verified/eligibility gates | 2/7 | Blocker | route and row assertions |
| AUTH-OIDC-003 | Returning verified completed user and old Owner routes | HTTP | synthetic completed owner | Dashboard only when all gates pass | 2 | Blocker | HTTP matrix |
| AUTH-OIDC-004 | Canceled, replayed, expired and mismatched callback | Integration | mocked errors | No session or account mutation | 1/2 | Blocker | negative assertions |
| AUTH-EMAIL-001 | Unverified holding, trusted refresh and resend limit | HTTP | synthetic unverified account | No Dashboard or old-route bypass | 2 | Blocker | route and throttle results |
| AUTH-EMAIL-002 | Disabled/restricted account bypass attempts | HTTP | synthetic disabled owner | All Owner reads/writes denied | 2 | Blocker | direct-route matrix |
| AUTH-LINK-001 | Current account, step-up and fresh second identity | Integration | two disposable identities | Only proven pair links | 5 | Blocker | transaction/audit assertions |
| AUTH-LINK-002 | Identity already linked elsewhere, email equality, duplicate race | DB/integration | two accounts, concurrent callbacks | Conflict; no ownership move | 1/5 | Blocker | unique index and row fingerprints |
| AUTH-LINK-003 | Unlink last usable method denied | Integration | one/multiple methods | Access retained; audit safe | 5 | Blocker | state assertions |
| AUTH-SESSION-001 | CSRF-protected local+Auth0 standard logout and allowlist | HTTP/tenant | mock SSO then non-prod tenant | Anonymous Sign In; no federated default | 2 | Blocker | cookie/redirect trace |
| AUTH-SESSION-002 | Use another account with both social providers | Tenant/HTTP | disposable Google/Microsoft identities | Fresh chooser intent; upstream limitation stated | 2 | Blocker | sanitized journey record |
| AUTH-SESSION-003 | Idle/absolute expiry, standard/opt-in/privileged limits and revocation | Unit/HTTP | controlled clock | Expired/old sessions denied | 2/5 | Blocker | clock/cookie assertions |
| AUTH-SESSION-004 | Secure/HttpOnly/SameSite, rotation, no browser auth storage | HTTP/browser | HTTPS test app | Flags and new ID; no local/sessionStorage secrets | 2 | Blocker | header/storage inspection |
| AUTH-SESSION-005 | CSRF on draft, logout, link, security and publication POST | HTTP | cross-site/missing tokens | State unchanged | 2–6 | Blocker | negative HTTP matrix |
| AUTH-MFA-001 | Admin/support MFA and normal optional policy | Tenant/HTTP | role fixtures | Privileged denied without factor | 2 | Blocker | role assurance matrix |
| AUTH-MFA-002 | Recent step-up on sensitive actions and session rotation | Integration | controlled freshness clock | Stale evidence denied; fresh allowed | 2/5 | Blocker | time and rotation assertions |
| AUTH-MFA-003 | WebAuthn/TOTP enrollment and recovery | Tenant | disposable credentials | Supported factors usable; no SMS-primary fallback | 2/5 | Blocker | non-prod tenant results |
| AUTH-ABUSE-001 | Generic errors and account-enumeration resistance | HTTP | known/unknown synthetic accounts | Equivalent safe feedback | 2/7 | Blocker | response/timing review |
| AUTH-ABUSE-002 | Switch, resend, link, recovery, draft-write rate limits | HTTP | controlled burst fixtures | Bounded requests; normal use succeeds | 2/3/5 | Blocker | limit counters |
| ONBOARD-DRAFT-001 | Active-step allow-list, validation, CSRF and debounce | HTTP/browser | synthetic draft | Only allowed fields saved once | 3/4 | Blocker | request/body-free assertions |
| ONBOARD-DRAFT-002 | Back/resume restores saved values | HTTP | synthetic five-step draft | Owner sees correct saved step | 3 | Blocker | draft state assertions |
| ONBOARD-DRAFT-003 | Two-tab stale-version and optimistic concurrency | DB/HTTP | concurrent draft versions | 409; no lost update | 3 | Blocker | version/row proof |
| ONBOARD-DRAFT-004 | Final full validation and repeated completion | DB/HTTP | synthetic valid/invalid drafts | One atomic private profile/portfolio | 3 | Blocker | transaction/count proof |
| ONBOARD-DRAFT-005 | Cross-owner draft read/write and mass assignment | HTTP | two-owner fixtures | 403/404; role/publication untouched | 3 | Blocker | ownership diff |
| ONBOARD-UI-001 | Five logical sections, visible progress and minimal fields | Browser | Arabic/English mobile fixtures | Correct position and no forbidden required fields | 4 | Blocker | screens and DOM checks |
| ONBOARD-UI-002 | Review/change, saving/saved/stale/failure feedback | Browser | network failure and stale tab | Actionable feedback and focus | 4 | Blocker | interaction recording |
| ONBOARD-UI-003 | Dashboard first-project prompt only after completion | HTTP/browser | new owner fixture | No project created in onboarding | 3/4 | Blocker | row and route checks |
| PRIVACY-001 | Private sanitized preview, no-store, no public index | HTTP/browser | draft and anonymous clients | Only owner sees sanitized output | 3/4 | Blocker | headers/HTML matrix |
| PRIVACY-002 | Private default and explicit portfolio/project publish | DB/HTTP | new and existing portfolios | No implicit public read; legacy rule preserved | 6 | Blocker | visibility/row fingerprints |
| PRIVACY-003 | Project and Evidence choices independent across HTML/JSON/media | HTTP | project visibility combinations | Evidence private unless separately chosen | 6 | Blocker | three-surface matrix |
| PRIVACY-004 | Atomic slug reserve, history, redirect and retirement | DB/HTTP | concurrent slugs | No duplicate or draft publication | 6 | Blocker | race and redirect matrix |
| PRIVACY-005 | Deletion/export/retention workflow | Integration | synthetic dependencies | Reviewed rights outcome; recoverable | 7 | Blocker | runbook and row inventory |
| PRIVACY-006 | Consent version, withdrawal and restricted state | DB/HTTP | synthetic policy versions | Audit event and access restriction | 3/7 | Blocker | event/state checks |
| PRIVACY-007 | Phone and each contact type private default | HTTP | synthetic contact records | No public phone without explicit choice | 6 | Blocker | public projection matrix |
| PRIVACY-008 | No secrets, raw subjects, private bodies or Evidence in logs | Log scan | synthetic markers | Only opaque IDs/safe codes | 2–7 | Blocker | redacted log scan |
| PRIVACY-009 | Project image/media ownership and publication | HTTP | two portfolios and anonymous | Private bytes inaccessible | 1/6 | Blocker | media matrix |
| TENANT-001 | Three Universal Login connections and password policy | Non-prod Auth0 | disposable identities | 15-char single-factor rule and manager/paste support | 2 | Blocker | safe configuration record |
| TENANT-002 | Verified claim semantics per connection | Non-prod Auth0 | disposable identities | Trusted signal and refresh behavior known | 2 | Blocker | sanitized tenant record |
| TENANT-003 | Attack/breach/bot protections, adaptive/link plan entitlements | Non-prod Auth0 | plan capability review | Required protections available or decision re-opened | 2/5/7 | Blocker | safe capability matrix |
| MIGRATION-001 | 014 fresh/upgraded backfill, unique pair, FK/collation | Isolated DB | fresh and 001–013 copy | Exact one-binding-per-user; no merge | 1 | Blocker | schema/count fingerprints |
| MIGRATION-002 | 015/016/017 fresh/upgraded and repeat-run no-op | Isolated DB | copies and partial-DDL fixtures | Correct schema and unchanged protected data | 2/3/6 | Blocker | ledger/schema diffs |
| MIGRATION-003 | Protected Evidence 12/12, projects 4/4, technology 27/24/0 | SELECT-only/isolated DB | canonical read and copy | Counts/fingerprints unchanged | 1–7 | Blocker | sanitized aggregate manifest |
| ROLLBACK-001 | Protected backup and isolated restore before live migration | Operations rehearsal | isolated target | Restore proven; no canonical test writes | 1–7 | Blocker | manifest/checksum/restore proof |
| ROLLBACK-002 | Partial MySQL DDL and app/schema compatibility | Isolated DB | interrupted migration | Reviewed forward recovery; feature off | 1–7 | Blocker | incident rehearsal |
| A11Y-001 | Keyboard, visible focus, labels, focused error summary | Browser/assistive tech | English and Arabic fixtures | No inaccessible path | 4/7 | Blocker | manual and automated evidence |
| A11Y-002 | Reduced motion and contrast in green/gold theme | Browser | motion and contrast modes | Usable without motion/contrast failure | 4/7 | Blocker | measured report |
| I18N-001 | Translation coverage and document lang/dir | Browser | Arabic/English fixtures | No untranslated critical step; correct direction | 4/7 | Blocker | DOM/screenshot record |
| I18N-002 | Mixed-direction user text and slug isolation | Browser | bilingual synthetic values | Readable and non-spoofed controls | 4/7 | Blocker | bidi visual/screen-reader proof |
| SMOKE-001 | Responsive 320px through desktop and form reflow | Browser | multiple viewports | No clipped mandatory control | 4/7 | Blocker | viewport matrix |
| SMOKE-002 | Production-like auth→draft→Dashboard→publication and privacy | Staging | disposable accounts | All gates and public projections pass | 7 | Blocker | sanitized smoke report |
| SMOKE-003 | Migration ledger, container/port/volume and protected data reconciliation | Operations | read-only before/after | Expected identities and fingerprints preserved | 7 | Blocker | sanitized manifests |

## Acceptance rules

### Phase 1 isolated evidence, 2026-10-04

Commit `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e` added the guarded 014 migration, read-only compatibility lookup and disposable rehearsal harness. The Phase 2 foundation suite, static architecture checks, PHP and PowerShell syntax, and Git whitespace check passed locally. `scripts/run-phase1-isolated-rehearsals.ps1` passed:

| Stable ID / scope | Verified result | Remaining boundary |
| --- | --- | --- |
| MIGRATION-001 | Fresh 001–014 and protected 001–013-copy upgrade; exact original-user binding, binary unique pair, FK, malformed and conflicting identity rejection; second runner invocation no pending migration | At Phase 1 review the live ledger was 001–013; it is now 001–014 after the protected deployment |
| MIGRATION-003 | Five user-to-portfolio relationships and protected row fingerprints unchanged in upgraded copy; Projects 18–21, Evidence 12/12, complete projects 4/4, technologies 27/24/0 and image hashes reconciled against read-only canonical baseline | Repeat live SELECT reconciliation before deployment |
| ROLLBACK-002, isolated 014 part | Deliberate post-DDL/empty-table state with no 014 ledger entry; normal runner completed backfill, repository lookup then passed, repeat run was a no-op | Incomplete state is not application-serving; arbitrary corruption and live recovery unproven |
| AUTH-LINK-002, database constraint part | Duplicate issuer/subject pair rejected; conflicting preexisting binding stopped without ledger advancement | No account-linking flow or concurrent provider test exists |
| Existing Phase 2 suite | Existing owner/OIDC/ownership/public lifecycle/Evidence characterization passed without changes | Does not establish new Auth0 tenant capabilities or future auth gates |

At Phase 1 review, `ROLLBACK-001` remained open because the isolated copy and Git bundle did not prove a protected live database/media backup and restore. A later protected Migration 014 deployment used verified recovery artifacts; review those separately before any new live operation. At Phase 1 review, `AUTH-OIDC-001/004` were only characterized by existing tests and the repository was not wired to callbacks. The 2026-10-05 local Phase 2A candidate later became the merged, deployed and accepted callback; the original local-only boundary is superseded as of 2026-10-09 UTC. `TENANT-001/002/003`, registration, verification, linking, UI and accessibility gates remain open. At this Phase 1 review point, no live migration, Auth0 capability test or callback cutover had occurred. Migration 014 and the intentional Phase 2A callback rollout were completed later. Broad Auth0 capability testing remains open; scoped owner acceptance is recorded above.

Characterization tests precede behavior changes. Every migration runs against isolated fresh and upgraded databases, including repeat-run and partial-DDL cases. Tenant tests use non-production Auth0 and disposable identities. Accessibility needs measured Ather-page and customization verification; hosted-login claims alone are insufficient. Security and privacy cases are negative tests as well as happy paths. Production-like smoke never submits an Owner form against the canonical runtime without a separately approved operation. Do not print tokens, subjects, real emails, private Evidence or phone in test evidence.
