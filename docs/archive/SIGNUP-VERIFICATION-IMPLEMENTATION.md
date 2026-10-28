# Sign Up and email verification presentation — 2026-10-11

## Scope and baseline

Isolated branch `feat/signup-verification-20261011`, based on `5b96e85e95e99033042f05839c58afe900de95fb`. Read-only GitHub checks confirmed main at that revision and [Main CI 38076150391](https://github.com/stoers12/ather-career/actions/runs/38076150391) successful on that exact merge. PR #10's merged presentation is the baseline. No deployment of Landing or Sign In to E: is inferred. The canonical E: checkout remains at `82353dc2d2a709858d0b56852182acce20f06009`, with its existing script modification preserved.

This batch adds public, inactive Sign Up presentation and **preview-only** pending/success verification fixtures. It does not activate registration or verification. The accepted Sign In, Landing, Google routing, ordinary login and `prompt=login` switch/retry source are unchanged. No Auth0, identity-binding, session/authorization, database, migration, deployment, Notion or dependency change. Shared Git administrative metadata is necessarily updated to create the worktree and commit; E: working files are untouched.

## Action map established before implementation

| Presentation action | Existing capability or outstanding prerequisite | This batch |
| --- | --- | --- |
| Create with Google | Existing Google **Sign In** accepts exact pre-existing bindings; unknown-user provisioning/public registration is not established | Native disabled registration button. It does not use the login route or request `screen_hint=signup`. |
| Create with Microsoft | Microsoft unavailable under current approved policy; no configured/accepted connection established | Native disabled button with associated policy notice. |
| Create with email/password | Sign In email UI remains unavailable; existing recorded issuer mismatch/eligibility and acceptance remain unresolved. Public registration is separately unavailable | Native disabled button. No credentials collected in Ather. |
| Existing-account Sign In | Merged `/signin.php` provides Google and ordinary login, with disabled Microsoft/email | Safe localized navigation to that page. The isolated preview blocks all authentication at `503`. |
| “I have verified my email” | No trusted verification-refresh endpoint/status contract established | Native disabled button; no refresh, polling or query-state promotion. |
| Resend verification email | No established resend endpoint, delivery integration or server-owned cooldown/rate-limit contract | Native disabled button; no simulated timer or sent-message promise. |
| Use another account in the prototype | Existing switch/retry is a protected POST+CSRF/local-session flow with `prompt=login`; the unauthenticated fixture has no such context | Clearly relabelled ordinary “Return to sign in” link. Existing switching behavior untouched. |
| Start portfolio setup after success | Existing Owner Onboarding has its own authenticated-user/ownership gates; no server-trusted verification-to-onboarding transition established | Native disabled button; fixture success never grants access. |

Future integration must implement the already documented architecture's prerequisites in separately authorized work. It must use trusted server/provider evidence and existing authorization boundaries; displaying the fixture or passing `verified=1` is never evidence of verification. No architecture redesign is proposed here.

## Reference provenance and preservation

Existing originals located both in the supplied Temp paths and the recovered `Temp/ather-approved-designs-20261004/` directory; all four files passed SHA-256 verification. The prior Sign In evidence copy of the auth original also matches. Recovered `approval-evidence.json` was read. Additional byte-exact copies and provenance are saved in the new local review evidence directory, outside Git; originals remain untouched.

| Original | SHA-256 | Approved screen |
| --- | --- | --- |
| `ather-auth-entry-flow.html` | `e98a321e7c46bd2ae40fe86743c4e789964ee6cc5e52b384093a424bf2ed2ea0` | `data-screen="signup"`; approval 2026-10-04 20:10:08 Asia/Amman |
| `ather-email-account-states.html` | `744f0202c09229d273e39196536924287a2e4268d583692931433197eb6762b9` | `pending` and `verified`; approval 2026-10-04 20:20:04 Asia/Amman |

## Presentation and adaptations for owner review

Sign Up reuses merged `signin.css`, local Noto Sans Arabic 400/500, bilingual copy conventions, static SVG and `landing.js` theme preference behavior. Email states retain their own original `.88fr/1.12fr` grid, 435px card, 29px padding, story panel and color tokens. Both use the 1050px/690px original desktop frame. Icon shapes absent from existing helpers were extracted from the already saved reference's Lucide 1.17.0 bundle, with the existing ISC license; no remote script or new dependency is added.

| Adaptation | Reason |
| --- | --- |
| All create/resend/refresh/onboarding controls disabled, with visible associated explanations | Honest unavailable actions; disabled colors follow merged Sign In conventions, including neutralizing the prototype gold/primary fills. |
| Registration disclosure retained; signup story copy no longer instructs users to sign in | Current approved availability policy and correct screen context. Original signup headline, provider labels, subtitle, age notice and existing-account footer retained in Arabic. |
| Prominent preview notice precedes verification headings; pending/success descriptions explicitly refer to fixtures/planned behavior | No email was sent, no account/address verified, no access granted. The hard-coded masked `example.com` address is fixture data and cannot be supplied by a query. |
| Resend label has no countdown | No backend cooldown exists to support an apparent active timer. |
| Prototype account switch becomes localized Return to sign in | Avoids implying session termination or reproducing protected switch behavior in an unauthenticated fixture. |
| English translation/LTR, real language links, theme control beside language, skip link, native links/focus and reduced-motion CSS | Matches merged presentation conventions. English and these adaptations require owner review. No device selector, inspector, state toolbar or simulated navigation. |
| Automatic <=720px layout uses original mobile card padding/radius; page scroll remains available | Replaces prototype device controls/overflow lock; adopts existing auth mobile convention. No physical-device evidence. |
| Dark story kicker/icons use `#69501a` on the original light-green story surface | Reuses the accepted Sign In contrast adaptation. No full rendered contrast acceptance claimed. |
| No new Sign Up link inserted into Landing or Sign In | Preserves their accepted copy/availability policy during this presentation-only batch. Direct preview/review links are supplied separately. |

## Files and routing

- `signup.php`, `public/signup.php`, `includes/signup_copy.php`, `signup.css`: inactive public `GET`/`HEAD` Sign Up, Arabic default or exact scalar `lang=en`; other/structured language input safely falls back. Unsupported methods receive shared `405`/Allow behavior. Static copy escaped, no request content reflected.
- `includes/account_entry_icons.php`: six static reference icon shapes, falling back to existing icon helpers.
- `includes/email_verification_copy.php`, `email_verification.css`, `tests/phase2/support/email-verification-preview.php`: bilingual preview presentation. Fixture requires an explicit isolated-router/CLI constant, CLI/CLI-server SAPI and a server-selected fixed fixture. No public shim or endpoint exists. Direct fixture requests are denied; query/cookie/local-storage values cannot establish status. Theme storage only changes appearance.
- `scripts/test/run-account-entry-tests.php`: meaningful no-auth/mutation/session/client-state contracts. CI invokes it alongside unchanged Sign In tests.
- `Dockerfile.production`: static stylesheet copy mapping only; `public/signup.php` uses the established production shim convention. Verification fixture routes are absent from the public document root.
- `.github/workflows/ci.yml`: adds contracts and production smoke assertions for inactive Sign Up and missing verification fixture routes; no existing gate removed or exception reused.
- This record and `HTTP_CONTRACTS.md`: scope, contracts, action map, provenance, adaptations and acceptance limits.

## Initial verification snapshot — 2026-10-11 (historical)

This snapshot records the initial implementation at `5ca9688e76bbe594c1fbd2bb7b72ac5dc1c92ec1`, before the copy correction, rendered review, focus fix and PR publication. Its unavailable-browser and no-remote-CI observations describe that point in time, not the current batch status. The completion section below supersedes those outstanding checks while retaining their history.

| Check | Result |
| --- | --- |
| Changed PHP syntax | Seven PHP files passed native PHP 8.2 lint. |
| Presentation boundary contracts | 142 checks passed: both languages, disabled actions/explanations, no forms/credentials/handlers/auth destinations, ignored client-selected claims, unchanged session/no session start, strict fixture and locale fallback. |
| Existing Sign In suite | 62 checks passed with scoped teardown, including Google/ordinary method contracts and `prompt=login` compatibility. No provider network requests. |
| Relevant regression cases | IdentitySessionContractTest, Auth0OidcStaticTest, Auth0CallbackBoundaryTest, AccountSelectionStaticTest and StaticArchitectureGuardTest passed with scoped teardown. |
| Local HTTP/source/syntax checks | 116 checks passed: localized GET/HEAD, 405 rejection (including the page independently of the preview router), ignored client state, no cookie/redirect, preview authentication 503/protected routes 404, fixture inclusion guard, exact asset bytes, original hashes, CI YAML parse and preservation of 15 relevant baseline source files. HTTP/source tests are not browser tests. |
| CI additions at the initial snapshot | Wired for future CI; no remote CI or local production-container run had been performed at this point. Subsequent remote CI is recorded below. |
| Rendered review at the initial snapshot | **HISTORICALLY UNPERFORMED**. Browser inventory returned `apps=[]`, `browsers=[]`; one `createBrowserTab('edge', …)` returned `Browser is not available: edge`. No repeated recovery attempts. Subsequent Playwright evidence is recorded below. |

At that initial snapshot, the **36-case** rendered matrix and reference/keyboard/Console checks were outstanding. They were subsequently completed as described below. Owner visual acceptance remains pending; test completion does not supply that acceptance. No security-scan exception or provider acceptance is inferred from PR #10.

## Rendered and CI completion — 2026-10-11

Final reviewed **presentation/code revision**: `e9bf6280b9e69f75ec225aaf56d7b894982fcee7`, on `feat/signup-verification-20261011`, base `5b96e85e95e99033042f05839c58afe900de95fb`; [Draft PR #11](https://github.com/stoers12/ather-career/pull/11). This completion record is a later documentation-only follow-up. Its publication head and automatic CI are recorded separately in the PR and durable batch handoff; rendered evidence retains its tested code revision and is not represented as a new browser run.

Durable owner-local evidence: `D:/2- Ather Portfolio Project Planing SaaS/ملفات Temp/ather-signup-review-20261011/`. The [batch handoff](<D:/2- Ather Portfolio Project Planing SaaS/ملفات Temp/ather-signup-review-20261011/BATCH-HANDOFF.md>) indexes results, screenshot matrices and comparison images by revision. These local artifacts are not GitHub attachments. The verified original copy comprises 275 files / 16,774,531 bytes; original count/size/SHA-256 verification is reused, source files retained, and only changed/new archive entries require manifest updates.

| Performed evidence | Tested revision and scope | Durable archive record |
| --- | --- | --- |
| Initial local syntax/contracts/regressions/116 HTTP-source checks | `5ca9688e76bbe594c1fbd2bb7b72ac5dc1c92ec1`; historical checks above, not browser evidence | `php-lint.txt`, `http-source-results.json`, historical handoffs; the initial standalone auth-regression stdout was not available, so only its script/reported result is retained |
| Signup-only bilingual availability correction; two PHP lints, 142 presentation/62 Sign In contracts, 34 focused copy/HTTP checks | `71cd7f6838b140ddeb2d0e690061a49de5603224`; Sign In notice and behavior unchanged | `copy-video-followup/` test records and exact diff |
| 36 rendered combinations and actual interactions | `71cd7f6838b140ddeb2d0e690061a49de5603224`; three screens × 1280×900 desktop / 320×645 / 390×645 × Arabic/RTL / English/LTR × light/dark | `rendered-playwright/RESULTS.md`, `browser.json`, `final-matrix.json`, `desktop-focus-boundary.json`, full-page screenshots and desktop/narrow review sheets |
| Six corrected approved-reference comparisons | Product captures at `71cd7f6838b140ddeb2d0e690061a49de5603224`; each screen in Arabic desktop/light and 390px/dark; originals retain the hashes above | `rendered-playwright/reference-fonts.json`, `review-sheets/*-reference-desktop.png`, `*-reference-390.png`, preserved reference derivatives/originals |
| Twelve affected pending focus cases after the fix | CSS committed unchanged as `e9bf6280b9e69f75ec225aaf56d7b894982fcee7`; all three widths × both languages/themes | `pending-focus-clearance-after.json`, `screenshots/pending-clearance-after-*`, `review-sheets/pending-focus-correction-final.png`, focus-only `exact.diff`, all under `rendered-playwright/` |
| Successful exact-head remote CI | `e9bf6280b9e69f75ec225aaf56d7b894982fcee7`; first-attempt [push CI 38165438321](https://github.com/stoers12/ather-career/actions/runs/38165438321) and [PR CI 38165478248](https://github.com/stoers12/ather-career/actions/runs/38165478248) | `review-finalization/WORKFLOW-RUNS.json`, `CHECK-RUNS.json`, CI job metadata and scoped logs; production syntax, Sign In/presentation contracts and signup405/verification404 smoke checks passed |

Installed Playwright 1.58.0 / headless Chromium 145.0.7632.6 used temporary profiles and isolated contexts against the existing authentication-disabled loopback preview. No main profile, credentials, provider experiment or new dependency was needed. Wrapping, vertical reachability, horizontal overflow, local fonts, actual Tab/Shift+Tab/skip/no trap, visible focus, disabled pointer/nonfocusability behavior, localized navigation, theme/language/persistence and application Console/network checks passed. No application Console errors or authentication/mutation requests were observed in completed cases.

Screenshot inspection found a real pending-page focus-outline collision with the availability notice. The sole presentation delta after the 36-case review is `#ather-email-states .es-text-action:focus-visible { outline-offset: -6px; }`. The 12 affected cases then passed with a visible 3px outline and +1px minimum clearance from the preceding text-range bounds. Other rendered evidence is reused because that focus-only change leaves other presentation and behavior unchanged. Earlier pending focused images show the now-fixed collision and are superseded by the final images identified above.

Initial invalid harness assertions (vertical font-range clipping metric/mobile focus reset) and the first reference derivative's font-import parsing error remain preserved, explicitly superseded in `RESULTS.md`. They are not passed checks or product defects. Comparison is against adapted static derivatives of the approved originals, not pixel equivalence. Chromium viewport emulation does not certify physical devices, other browsers or comprehensive accessibility/contrast/reduced-motion behavior. No browser tests are repeated for owner approval or for this documentation-only change.

### Owner observations, adaptation checklist and current decisions

Owner-supplied desktop recording `Recording 2026-10-11 204826.mp4` shows, as attributed reviewed observations: success in Arabic/English and light/dark; pending in Arabic/English with visible vertical scrolling and navigation back to English Sign In; signup language/theme changes without obvious desktop overlap. Its exact code revision/viewport were not independently established. The actual video was unavailable in known evidence locations and **was not archived, replayed or hashed**. `copy-video-followup/VIDEO-AND-BROWSER-EVIDENCE.md` preserves the observations and limits; they are not complete responsive/keyboard/Console/reference or functional acceptance.

Owner checklist (all pending; direct comparison-image links are in the durable batch handoff):

- **Sign Up:** retained reference hierarchy/fonts/icons; neutral disabled creation controls/full-width email button; correct signup-specific Arabic/English availability, registration and age notices; taller card and existing-account footer.
- **Pending:** fixture/no-send/no-account/no-access disclosure, masked fixture address, unavailable refresh/resend with no simulated countdown, ordinary Return to sign in and its corrected visible focus.
- **Success:** explicit fixture success/planned-flow wording, disabled onboarding and no access promise; disclosure and taller card.
- **Across all three:** English translation/LTR, Arabic/RTL, both themes including dark-story kicker/icon adaptation, language/theme/skip controls and automatic narrow scrolling/frame behavior; no prototype device/inspection tools.

No concrete unresolved presentation defect is recorded after the focus fix. The intentional reference adaptations and English translation remain owner decisions, not accepted equivalence. **Owner visual acceptance remains pending for all three screens together.** Functional registration, resend, verification refresh and onboarding remain unavailable; fixtures never establish verified status, create accounts, send email or grant access. The functional prerequisites below are unchanged.

At the code-head inspection, no Copilot check/run was reported; none was passed or waived. For this follow-up, Copilot is unavailable due to **owner-reported quota exhaustion**. No scan/rerun is requested, no quota failure is relabelled passed, and no workflow weakening or prior exception applies. Formal review/scan disposition and any merge/deployment authorization remain separate decisions.

## Isolated preview and remaining functional prerequisites

Local allowlisted host on `127.0.0.1:8176`; runtime router/server remain at `C:/Users/momen/AppData/Local/Temp/ather-signup-review-20261011/`. Preserved results and current evidence links are in the durable D: batch handoff above; copying evidence did not relocate or restart the preview. Router is outside Git and never copied into production. It blocks mutation methods, all real authentication and protected/internal routes. No tenant/database configuration is used.

| Screen | Arabic | English |
| --- | --- | --- |
| Sign Up | http://127.0.0.1:8176/signup.php | http://127.0.0.1:8176/signup.php?lang=en |
| Pending fixture | http://127.0.0.1:8176/verification-pending.php | http://127.0.0.1:8176/verification-pending.php?lang=en |
| Success fixture | http://127.0.0.1:8176/verification-success.php | http://127.0.0.1:8176/verification-success.php?lang=en |

Functional integration remains outstanding: separately authorized public-registration/provisioning and eligibility/terms/privacy/age requirements; accepted supported provider availability; trusted verification state/delivery/refresh/resend with server protections; verified-state transition into existing Owner authorization/onboarding gates. Email eligibility and Microsoft availability remain as recorded by the approved policy. Until those prerequisites are met and reviewed, all unsupported controls remain disabled. No merge, deployment, E: working-file change, Auth0/binding change, migration or Notion update is part of this batch.
