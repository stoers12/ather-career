# 07 — Test and acceptance matrix

Bounded local successor: [Card06 per-project publication](../archive/auth-onboarding/CARD06-PER-PROJECT-PUBLICATION.md). Focused guarded migration/CLI/HTTP and existing-style browser proofs cover defaults, mixed public/private HTML/JSON/media with a valid image, parent hide/republish, current authority/locks and uncertain COMMIT. Existing suites retained. Actual local commands/failures/gaps belong to the external handoff; no hosted success claim.


## Card 08 — bounded ONBOARD-UI-003 HTTP evidence

The existing integrated fixture checks the callback-authenticated incomplete User's Dashboard and direct `owner_projects.php?add=1` GETs: both must return 303 to the capable `/owner_onboarding_editor.php`, with all table fingerprints unchanged. After actual completion, it asserts zero owned projects, extracts and follows the rendered Dashboard Add Project href, and verifies the authorized form's add action, empty title, absent edit ID and CSRF control. It repeats with one private SQL-seeded disposable project, retaining the ordinary action while rejecting the Projects empty-state invitation. Every GET compares all table fingerprints; fixture removal restores the pre-seed snapshot. No project-creation POST is issued. Existing sealed-legacy routing coverage remains unchanged. This closes only the named row/route evidence gap when executed successfully, not all of Card 08; the original approved scope remains unresolved, and earlier CI is separate from new local execution.

## Card 08 — first bounded integrated journey coverage

Separate cleanup evidence: a disposable external fault probe obstructs only the original app-symlink restoration path. Before the correction it confirms fixture rows survive the reported restoration failure; afterward it requires the same reported failure, no app restart, and equality with all preexisting table fingerprints after independent fixture cleanup. Outer process/session/key/log/source/hostname cleanup is also checked. The probe is review evidence outside production and is not an additional application mode or a whole-card acceptance claim.

The original approved Card 08 card was not located in the bounded local review. The current owner-requested journey checklist is provisional; existing sources remain the purpose contract in [17](../archive/auth-onboarding/17-REGISTRATION-ROUTING-HANDOFF.md), accepted first-completion overlay in [22](22-ONBOARDING-COMPLETION-CONTRACT.md), transport [28](../archive/auth-onboarding/28-ATTESTATION-HTTP-HANDOFF.md)/[30](../archive/auth-onboarding/30-COMPLETION-HTTP-HANDOFF.md) and routing/navigation [32](../archive/auth-onboarding/32-REGISTRATION-JOURNEY-ROUTING-HANDOFF.md)/[33](../archive/auth-onboarding/33-JOURNEY-NAVIGATION-UI-HANDOFF.md). Stable requirements AUTH-OIDC-001/002, ONBOARD-DRAFT-002/004 and the accepted later-Profile authority are unchanged.

`run-registration-routing-tests.php` adds one integrated HTTP group via guarded `tests/Support/integrated-journey-acceptance.php`. One locally signed mock-provider registration supplies the actual callback-created User/readiness/session; no draft or completion is preseeded. The same cookie traverses draft saves, private pause and explicit resume, attestation/prerequisite denial, exact saved review, missing-confirmation denial, private completion, original receipt and Dashboard, real Profile HTTP edit, then null-policy receipt/exact retry and blocked new draft mutation. Whole-row fingerprints verify read/retry preservation and restoration of preexisting rows after fixture cleanup. Synthetic authentication and policies are explicit; no actual Auth0/Google assertion. Existing registration groups, CI invocation and independent editor/browser evidence remain; the new group does not replace their UI/uncertainty/concurrency coverage or establish Card 08 closure. Commands, results and retained failures belong to the external slice handoff.

D1 focused coverage: [34 — authenticated entry D1](../archive/auth-onboarding/34-AUTHENTICATED-ENTRY-D1-HANDOFF.md). Additive checks in the existing journey route and registration/OIDC suites exercise all three entries, four account classes, capability/null-policy states, malformed/null/expired/revoked/disabled/missing accounts, unreachable/missing-table dependencies, target reauthorization, preserved pending OIDC transaction, unchanged start-limit capacity with issuer stopped, actual entry-form switch/known-account return/logout and refusal boundaries. Existing completion browser fixture adds three D1 groups for private AR/EN responsive presentation, unavailable states and explicit keyboard navigation. CI retains its 21 CLI calls and existing browser assertions/cleanup. Local Apache packaging evidence and manual/provider/hosted gaps are recorded separately.

## Card 05 bounded navigation acceptance

[33 — pause/resume and explicit Dashboard navigation](../archive/auth-onboarding/33-JOURNEY-NAVIGATION-UI-HANDOFF.md): extend the existing 21st real-HTTP journey suite with exact pause query/method/private response checks, current legacy/completed/incomplete/bare-account actions, null/off safe holding and revoked/expired/switched authorization. Extend the existing completion/browser and editor suites with save→pause→explicit resume, newer input during acknowledgement, lost/rejected exit preservation, explicit Dashboard after receipt under null policies/later Profile edits, native unsaved-work protection after other-tab completion, null/uncertain/late results, RTL/LTR keyboard and narrow layouts. Preserve existing assertions and CI calls/credentials/ownership/0600/0700/cleanup. Renderer asserts exactly one initially hidden fixed Dashboard action; preview remains nonpersistent/Create-disabled. Local result details and unperformed manual device/screen-reader checks are in handoff 33.


## Card 05 focused integration

Add `run-owner-journey-route-tests.php` to the preserved twenty CLI calls. Exercise real HTTP/persisted synthetic sessions on disposable migrated MySQL: frozen owner without true readiness/receipt; incomplete strict gate combinations and production null policies; completed owner after Profile CAS edits/later-session receipt recovery; bare Portfolio denial across all Owner handlers and retired POST/helper; User/Portfolio/Profile binding, session expiry/revocation/logout and CSRF. Test additive 020 interruption, partial capture rollback/refusal, frozen retry, repeated/restored startup and whole-database/private fixture cleanup. Explicit synthetic legacy fixture grants replace the now-invalid assumption that all bare fixture rows authorize Owner access. No existing test is skipped; intentionally retired creation expectations are replaced with noncreation assertions. Actual commands/results/failures/gaps: [32 — server-side journey routing](../archive/auth-onboarding/32-REGISTRATION-JOURNEY-ROUTING-HANDOFF.md).

Completion UI acceptance: [31 — private final review and completion UI](../archive/auth-onboarding/31-COMPLETION-EDITOR-UI-HANDOFF.md) records real Chromium/HTTP/persisted-session/disposable MySQL tests for fourteen exact values/absence/disposition, saved versus unsaved/retry/conflict barriers, separate unchecked actions, no automatic completion, explicit private creation/receipt recovery, uncertainty/null reads/later sessions/Profile authority, context/obsolete replies and AR/EN/RTL/LTR/themes/responsive/keyboard/live status. CI preserves all twenty CLI calls and existing browser suites; adds a separate scoped fixture with verified non-root Node carrier handoff and orderly preservation/cleanup. Manual device/screen-reader and hosted gaps are explicitly distinguished.

Completion HTTP coverage: [30 — completion HTTP transport](../archive/auth-onboarding/30-COMPLETION-HTTP-HANDOFF.md) records persisted synthetic sessions, real HTTP and disposable MySQL tests of exact gate/method/CSRF/JSON bounds, private saved review/disposition/replacement, null production policies, original receipts/retries/later Profile authority, concurrent confirmations, rollback/uncertain/lost-response recovery, isolation and cleanup. Existing core tests retain draft-save/slug races and internal field/migration assertions. CI retains all former nineteen rehearsals and appends the focused twentieth with the same scoped credentials; production smoke adds default-off GET/POST. Results and unperformed checks are explicitly distinguished in the handoff.

Focused acceptance additions: [29 — private editor attestation UI](../archive/auth-onboarding/29-ATTESTATION-EDITOR-UI-HANDOFF.md) records real-browser persisted-session/disposable-MySQL coverage for original null policies/off endpoint, exact hostile plain text, server AR/EN document language versus UI language, fresh actions/no automatic writes, original evidence timestamps, stale/uncertain/lost outcomes, obsolete responses/account/expiry/bfcache boundaries, unsaved/pending draft preservation, responsive themes/keyboard/live regions and redacted synthetic screenshots. Existing HTTP/renderer/preview/editor assertions remain. Guarded private source copies alone supply successful synthetic documents. Commands, final results, retained failures and explicit manual-device/screen-reader/hosted-CI gaps belong to the handoff.

**Card 04 HTTP tests (2026-10-19):** The focused guarded CLI suite uses real HTTP,
persisted sessions and disposable migrated MySQL, first exercising unmodified
production null resolvers and then private test-source copies with synthetic bytes.
It checks exact digests/languages, missing/changed documents, bounded session binding,
auth/CSRF/JSON/media/size/override denials, old/cross-session tokens, concurrency under
the real User lock, original evidence time, rollback/possible-commit recovery, safe
logs and full cleanup. CI adds a nineteenth rehearsal and off-gate production HTTP
smoke assertions while retaining all existing assertions/cleanup. Actual local
commands, failures/corrections and gaps are recorded in [28](../archive/auth-onboarding/28-ATTESTATION-HTTP-HANDOFF.md).

**2026-10-19 bounded first-completion core:** Guarded completion CLI/MySQL tests cover migration preservation/recovery, strict fields/review/session/claim/exact-policy authority, atomic private creation, durable retries/recovery, competing User/slug/draft saves, rollback at every write, uncertain COMMIT and later Profile preservation. Production resolver stays null; synthetic policies are confined to a guarded temporary test-source copy, never a production injection or environment-selected bundle. See [handoff 27](../archive/auth-onboarding/27-FIRST-COMPLETION-CORE-HANDOFF.md). No HTTP/UI/routing/publication or live change.

**2026-10-19 optional final fields:** guarded `run-final-profile-tests.php` and
`run-final-profile-route-tests.php` cover 018 preservation/recovery, independent pinned
ISO membership/CLDR labels, exact Unicode/tokens, private transport, retired choices,
conflicts/account/CSRF and public-output exclusion. Existing writer-race fixtures retain
non-null final facts. New `run-final-profile-browser-tests.mjs` covers existing Profile
controls and synthetic screenshots at 1440/1024/768/390/320px, reusing Playwright.
CI checks deterministic generation, all 17 rehearsals and the new browser step without
skipping previous gates. Evidence, failed attempts and limits: [handoff 24](../archive/auth-onboarding/24-FINAL-PROFILE-FIELDS-HANDOFF.md).

**2026-10-18 bounded profile slice:** two focused guarded MySQL/HTTP suites cover
loaded-version CAS, omission/explicit visibility, real parallel saves/creation,
photo and direct migration races, rollback/uncertain media outcomes and migration
preservation. CI invokes them with its generated database-scoped application user.
Exact commands/results and limits are in [handoff 23](../archive/auth-onboarding/23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md);
these checks do not establish completion/publication readiness or launch approval.

**2026-10-15 step-2 visual follow-up:** A scoped Public identity CSS change aligns the country select and preferred-language cards. Twelve affected desktop/320px/390px × Arabic/English × light/dark states, native select arrow and keyboard focus, long country labels, mobile stacking, and transient selection preservation were checked. The owner reports prior behavior working; acceptance of this visual correction remains pending. Functional gates remain open.

**2026-10-15 focused correction:** A runnable Chromium regression reproduces the reported step-5 → top-step-1 → Continue sequence and the rapid duplicate activation that skips step 4 at the prior head. It verifies ordinary 1→2→3→4→5 navigation, all Review Edit returns, clearing the Edit return on top-step/Back navigation, Arabic/English value and theme preservation, and step-3 alignment at desktop/320px/390px in both languages and themes. Before/after screenshots and machine results are in the existing D: batch handoff. Functional gates below remain open.

**2026-10-15 local steps 3–5 extension:** The presentation fixture now contains five connected sections and a disabled Create action. The focused bilingual boundary suite passes 482 checks; 36 new rendered states cover steps 3–5 × desktop/320px/390px × Arabic/English × light/dark, with focused five-step value/navigation and same-page language checks. Production docroot excludes the fixture and assets. These are presentation results only; ONBOARD-UI-001 and all persistence, consent, link, visibility, authenticated-preview, and completion gates remain blocked.

**2026-10-15 presentation subset:** The isolated steps 1–2 preview passed focused content/boundary checks and 24 rendered Arabic/English × light/dark × desktop/320px/390px states. A same-day coordinator correction passed 151 focused assertions and 24 rendered Arabic/English language transitions across those widths and themes, preserving transient controls and private preview without transmitting entered values. The PR review follow-up passed 152 focused assertions; a temporary deliberate `window.name` write failed the boundary check and was removed. Focused rendered checks covered an actually pending translation request, pending response-body read, localized timeout and manual retry, late-body protection, and four successful switches across affected widths and themes. This is evidence for only the first two visual sections; ONBOARD-UI-001, draft, consent, private authenticated preview and completion gates remain blockers until their full scoped implementations are tested. The fixture is excluded from production routes.

**2026-10-05 status update:** Phase 2A locally characterizes the existing callback and exercises exact identity binding, denial, session rotation and foreign-Portfolio isolation in a disposable 001–014 database; see [10](../archive/auth-onboarding/10-PHASE2A-CALLBACK-RESOLUTION.md). The existing Phase 2 suite, disposable production smoke and isolated 014 fresh/upgraded rehearsals passed. Historical Phase 1 evidence below remains scoped to its date. No live Auth0/provider acceptance is claimed.

**Status:** requirements with scoped Phase 1 evidence below, not a blanket pass. Stable IDs must not be reused for different behavior. “Blocker” means the row must pass before the listed phase can be accepted or before public beta where the phase is 7. Existing tests in tests/Legacy include OIDC static/session contract, tenant authorization, ownership migration, public lifecycle and Evidence checks; these characterize parts of current behavior but do not satisfy new gates. Use synthetic fixtures and redact result artifacts.

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

Commit `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e` added the guarded 014 migration, read-only compatibility lookup and disposable rehearsal harness. The Phase 2 foundation suite, static architecture checks, PHP and PowerShell syntax, and Git whitespace check passed locally. `tests/Integration/run-phase1-isolated-rehearsals.ps1` passed:

| Stable ID / scope | Verified result | Remaining boundary |
| --- | --- | --- |
| MIGRATION-001 | Fresh 001–014 and protected 001–013-copy upgrade; exact original-user binding, binary unique pair, FK, malformed and conflicting identity rejection; second runner invocation no pending migration | At Phase 1 review the live ledger was 001–013; it is now 001–014 after the protected deployment |
| MIGRATION-003 | Five user-to-portfolio relationships and protected row fingerprints unchanged in upgraded copy; Projects 18–21, Evidence 12/12, complete projects 4/4, technologies 27/24/0 and image hashes reconciled against read-only canonical baseline | Repeat live SELECT reconciliation before deployment |
| ROLLBACK-002, isolated 014 part | Deliberate post-DDL/empty-table state with no 014 ledger entry; normal runner completed backfill, repository lookup then passed, repeat run was a no-op | Incomplete state is not application-serving; arbitrary corruption and live recovery unproven |
| AUTH-LINK-002, database constraint part | Duplicate issuer/subject pair rejected; conflicting preexisting binding stopped without ledger advancement | No account-linking flow or concurrent provider test exists |
| Existing Phase 2 suite | Existing owner/OIDC/ownership/public lifecycle/Evidence characterization passed without changes | Does not establish new Auth0 tenant capabilities or future auth gates |

At Phase 1 review, `ROLLBACK-001` remained open because the isolated copy and Git bundle did not prove a protected live database/media backup and restore. A later protected Migration 014 deployment used verified recovery artifacts; review those separately before any new live operation. At Phase 1 review, `AUTH-OIDC-001/004` were only characterized by existing tests and the repository was not wired to callbacks. Local Phase 2A now wires the candidate callback; canonical main remains on the legacy resolver. `TENANT-001/002/003`, registration, verification, linking, UI and accessibility gates remain open. At this Phase 1 review point, no live migration, Auth0 capability test or callback cutover had occurred. Migration 014 was deployed later; Auth0 capability testing and an intentional callback rollout remain pending.

Characterization tests precede behavior changes. Every migration runs against isolated fresh and upgraded databases, including repeat-run and partial-DDL cases. Tenant tests use non-production Auth0 and disposable identities. Accessibility needs measured Ather-page and customization verification; hosted-login claims alone are insufficient. Security and privacy cases are negative tests as well as happy paths. Production-like smoke never submits an Owner form against the canonical runtime without a separately approved operation. Do not print tokens, subjects, real emails, private Evidence or phone in test evidence.
