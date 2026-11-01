# L-03 verification output

Latest: [PR #105 review fixes, verification and after-download screenshot](review-fixes.md).

Date: 2026-11-01. Base `ccbf5faf08a9` / PR #104. Runtime source was verified by the full gate source manifest before adding this documentation and the generated screenshots. All databases, processes, networks and containers used for testing were disposable. The running `portfolio_course` stack was not changed.

## Required checks

| Check | Command / runner | Exit | Result |
|---|---|---:|---|
| PHPUnit | `vendor/bin/phpunit --do-not-cache-result` | 0 | `OK (241 tests, 1805 assertions)` |
| Phase 2 | `php scripts/run-phase2-tests.php` | 0 | `Phase 2 test foundation passed.` |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=json` | 1 | Exactly 22 known `ignore.unmatched` entries; no new errors |
| Full required gates | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -EvidenceDirectory <external-new-directory>` | 0 | `REQUIRED_GATES_PASSED` |
| PHP syntax | PHP 8.3 `php -l` over includes, handlers and tests | 0 | All files passed |
| JavaScript syntax | `node --check` over all tracked JS/MJS/CJS and both new scripts | 0 | `JS_SYNTAX_PASSED` |
| Release script safety | `pwsh -NoProfile -File tests/Integration/test-release-local.ps1` | 0 | `RELEASE_LOCAL_SAFETY_PASSED` |
| Catalog | `python scripts/tools/build-profile-catalog.py --check` | 0 | Deterministic product catalog passed |
| Diff and text files | `git diff --check`; byte check of changed/new PHP/CSS/JS/CJS/MD | 0 | No whitespace errors; LF and UTF-8 |

Dependencies were installed with `docker run --rm -v <worktree>:/app -w /app composer:2 install --no-interaction --prefer-dist`. Fast checks used `ather-career-local:ccbf5faf08a9` with the worktree mounted read-only, `APP_ENV=test` and `ATHERCAR_TEST_MODE=1`. The full gates built and identity-checked fresh development and production images, ran PHPUnit/Phase 2 without host mounts, and passed the production image boundary/security checks.

## Required-gate result markers

Selected outcome lines below exclude raw logs, generated identifiers, HTTP bodies and telemetry.

```text
PASS public boundary (production image)
PHP_CAPABILITIES_OK\nPASS shared Owner workspace locale, layout and account-form contract
PASS Evidence Hub deterministic Unicode text evaluation
PASS Evidence Hub private storage and migration contract
PASS Evidence Hub Owner aggregation and technology mapping core
PASS Evidence Hub tenant-scoped recommendation disposition repository
PASS Evidence Hub frozen contract mapper
PASS Evidence Hub stable HMAC configuration boundary
PASS Evidence Hub Owner recommendation orchestration and full candidate resolution
PASS Evidence Hub protected Owner route and read-only presenter
PASS Evidence Hub private page model, progress, and evidence persistence
PASS Evidence Hub protected recommendation disposition actions
PASS Evidence Hub required finalization-gate registration
PASS Evidence Hub immediate one-level Undo
PASS Evidence Hub Owner localization and English fail-safe
PASS Evidence editor writing guidance, starters, and non-blocking hints
PASS Evidence Hub Owner technology review, contract v5, and scoped actions
PASS Evidence Hub contract v7 evidence details and authorized publication
PASS Evidence Hub canonical skill evidence recommendation
PASS Evidence Hub contract v8 skill evidence isolation
PASS Evidence Hub contract v9 and explicit text rule versions
PASS Evidence Hub contract v10 evidence sources
PASS Evidence Hub view modes and skill gaps
PASS Evidence Hub text rules localized Owner hints
Phase 2 test foundation passed.
OK (241 tests, 1805 assertions)
PASS Evidence Hub disposable MySQL UTC timestamp contract
PASS Evidence Hub skill evidence MySQL lifecycle
PASS Evidence Hub R3 disposable MySQL disposition repository and constraints
PASS public boundary (development image)
HTTP_ACTION_MATRIX_EXIT=0
OWNER_UNREAD_BADGE_LIFECYCLE_PASSED: zero -> one unread -> opened/read; real Owner routes with large text.
L03_DATA_RIGHTS_BROWSER_PASSED: ar/en, light/dark, 320/390/768/1280/1440, keyboard, AA contrast, 44px, local dates, strict notices, all native POST field sets without JavaScript.
OWNER_SETTINGS_VISUAL_PASSED: three sections; appearance persistence/system; preferences before paint; no-JS language; ar/en themes and large-text layouts.
OWNER_PROFILE_ACTIONS_PASSED: one save preserves both panels; 422 focus; 409 retention; skills CRUD; photo upload/remove.
OWNER_PROFILE_VISUAL_PASSED: anchors and keyboard; native no-JS panels; connected errors; ar/en themes and large text at all five widths.
OWNER_PROJECTS_VISUAL_PASSED: real filter/search counts; +N search; grid/list; keyboard menu; native no-JS; steps; escaped preview; localized keyboard/no-JS picker and filename status; six-row technologies; 422 focus; AA contrast; ar/en themes and large text at 390 768 1280 1920 2560.
OWNER_PROJECTS_ACTIONS_PASSED: step 2 saves step 1; live_url save/keep/clear/reject; 422 field retention and correct step; upload rejection retention; real image upload/remove.
OWNER_EXPERIENCES_VISUAL_PASSED: ordered list; all five types; ar/en light/dark; AA contrast; large text at all five widths; 44px targets; native no-JS current/end controls; retained conflict and 422 focus; honest saved status.
OWNER_EXPERIENCES_ACTIONS_PASSED: authenticated native add/edit/delete with and without JS; optional description; current/ended transitions; 422 retained values and error focus/announcement; keyboard delete/cancel from card and editor; missing id; synthetic cleanup.
OWNER_SHARING_VISUAL_PASSED: five publication states; retained slug error; localized keyboard copy success/failure; messages selected/unread/empty; encoded mailto; no reply form; ar/en light/dark AA; all widths and large text; 44px targets; mobile list first; no-JS.
OWNER_SHARING_ACTIONS_PASSED: real forms with/without JS; slug reject/retain/reserve; publish/unpublish; messages GET read and POST unread; missing-message 404; escaped content and encoded mailto; synthetic state restored.
OWNER_WORKSPACE_VISUAL_PASSED: 20 layout combinations; compact persistence; mobile keyboard; native no-JS forms; B-01 states; AA contrast; coarse targets; short viewport; denied storage; locale arrow direction; single-line Evidence Hub.
PASS Evidence Hub R4 English/LTR visual contract
PASS Evidence Hub B-07 browser save from step 2, preserved values after source add/remove, immediate Undo across tabs, and retained 422 focus
PASS Evidence Hub R4 real Edge actions
PASS Evidence editor writing aids
PASS Evidence Hub Owner technology review
PASS Evidence Hub skill evidence responsive Owner card
PASS Evidence Hub text rules localized responsive hints
Production security gate passed.
REQUIRED_GATES_PASSED
```

## ci.yml PHP and HTTP rehearsals

All eight self-contained runners in the PHPUnit coverage loop / Phase 2 foundation step were run with test mode in the PHP image. Coverage collection itself is CI-owned; this local check validates the same runner behavior.

| Runner | Exit | Outcome |
|---|---:|---|
| `run-account-entry-tests.php` | 0 | PASS account entry presentation boundaries: 545 checks. |
| `run-onboarding-editor-tests.php` | 0 | PASS editor private script and production packaging contracts<br>PASS editor exact shared country/status/language catalogs and localized labels with native controls |
| `run-onboarding-presentation-tests.php` | 0 | PASS onboarding steps 1–5 presentation boundaries: 572 checks. |
| `run-owner-logout-tests.php` | 0 | PASS logout private HTTP logs exclude session CSRF and secret values<br>PASS logout complete private session log process and fixture cleanup; no provider request performed |
| `run-owner-readiness-claim-tests.php` | 0 | Owner readiness claim integration: PASS |
| `run-owner-readiness-session-tests.php` | 0 | Owner readiness session lifecycle: PASS |
| `run-recovery-presentation-tests.php` | 0 | PASS recovery presentation boundaries: 77 checks. |
| `run-signin-tests.php` | 0 | PASS Sign In contracts: 62 checks; scoped teardown passed. |

The Owner-readiness creation route and **all 25** scripts in the isolated CLI-rehearsal step were run in workflow order from a fresh V1 MySQL 8.4 database. The rehearsal migrated the database, used a database-scoped user with the same privileges as CI, and configured private media storage. The same runtime source as the full required-gates development image was used.

| Runner | Exit |
|---|---:|
| `tests/Integration/run-owner-readiness-creation-route-tests.php` | 0 |
| `tests/Integration/run-phase2-ownership-rehearsal.php` | 0 |
| `tests/Integration/run-phase2-public-lifecycle-rehearsal.php` | 0 |
| `tests/Integration/run-phase2-tenant-authorization-rehearsal.php` | 0 |
| `tests/Integration/run-phase2-experience-rehearsal.php` | 0 |
| `tests/Integration/run-phase2-auth0-rehearsal.php` | 0 |
| `tests/Integration/run-profile-version-tests.php` | 0 |
| `tests/Integration/run-profile-version-route-tests.php` | 0 |
| `tests/Integration/run-final-profile-tests.php` | 0 |
| `tests/Integration/run-final-profile-route-tests.php` | 0 |
| `tests/Integration/run-phase2-owner-flow-rehearsal.php` | 0 |
| `tests/Integration/run-registration-core-rehearsal.php` | 0 |
| `tests/Integration/run-onboarding-attestation-tests.php` | 0 |
| `tests/Integration/run-onboarding-draft-tests.php` | 0 |
| `tests/Integration/run-onboarding-draft-route-tests.php` | 0 |
| `tests/Integration/run-onboarding-editor-tests.php` | 0 |
| `tests/Integration/run-onboarding-presentation-tests.php` | 0 |
| `tests/Integration/run-registration-routing-tests.php` | 0 |
| `tests/Integration/run-onboarding-completion-tests.php` | 0 |
| `tests/Integration/run-onboarding-attestation-route-tests.php` | 0 |
| `tests/Integration/run-onboarding-completion-route-tests.php` | 0 |
| `tests/Integration/run-owner-journey-route-tests.php` | 0 |
| `tests/Integration/run-publication-mutation-tests.php` | 0 |
| `tests/Integration/run-project-publication-tests.php` | 0 |
| `tests/Integration/run-contact-visibility-tests.php` | 0 |
| `tests/Integration/run-account-data-rights-tests.php` | 0 |

```text
L03_CI_CLI_REHEARSALS_PASSED
```

The L-03 runner reported:

```text
PASS every table that references an account is exported and purged, or explicitly excluded
PASS export holds every row and original image of the account and nothing of another account
PASS deletion needs a sign-in from the last 10 minutes and the typed page name
PASS a request hides the page at once, signs out every session and keeps export available
PASS cancelling within the grace period restores everything
PASS the purge deletes the Auth0 users, every row and file, keeps only id and dates, and retries after a failure
```

## ci.yml browser steps that load Settings, sign-in or Dashboard

| Step / runner | Exit | Output |
|---|---:|---|
| Legal publication and consent / `run-legal-pages-browser-tests.mjs` | 0 | Privacy, Terms, signup/signin/footer/Settings links, exact native consent POSTs, AR/EN, keyboard, AA, CSP, widths and screenshots passed |
| Private final-profile controls / `run-final-profile-browser-tests.mjs` | 0 | Ordered catalogs, exact saves/reload, contact controls, Unicode validation, conflict retention and 1440/1024/768/390/320px passed |
| Authenticated completion editor / `run-onboarding-completion-browser-tests.mjs` | 0 | Review/confirm/receipt, Dashboard navigation and current owner, rejected/lost acknowledgements, pause, signed-in entry/Resume, AR/EN themes and widths passed |

The profile and completion fixture processes exited 0 after the browser steps. Their private carriers, sessions, files, source copies, synthetic rows and processes were cleaned up. Browser logs reported:

```text
PASS legal privacy: gate-off normal 404; gate-on exact approved AR/EN wording, version/date, configured/null contact, anchors, AA, CSP and five widths
PASS legal terms: gate-off normal 404; gate-on exact approved AR/EN wording, version/date, configured/null contact, anchors, AA, CSP and five widths
PASS legal links on/off at signup, signin, public footer and Settings Help; invite/open exact native consent POSTs; AR/EN checkbox/error, keyboard, AA and no overflow
PASS legal screenshots and publication-off proof
PASS final-profile browser ordered 13-country launch catalog, AR/EN labels/help and unchanged retired choices
PASS final-profile browser real saves/reload preserve exact mixed Unicode and distinct legacy fields
PASS contact-types browser private defaults independent labels keyboard four combinations and exact save reload without secondary control
PASS final-profile browser Unicode boundary, bilingual validation, original token and explicit clear
PASS final-profile browser two-tab conflict retains new choices and original version without replay
PASS final-profile browser synthetic screenshots and keyboard/bilingual controls at 1440/1024/768/390/320px
PASS completion browser original null policies and endpoint off preserve independent saved draft and attestation boundaries
PASS completion browser exact fourteen saved field values deferred disposition plain text and unchecked explicit actions
PASS completion browser acknowledged saves immutable pending draft retry and delayed-review invalidation preserve newer local input
PASS completion browser invalid saved headline retired country and reserved slug require deliberate correction and acknowledged saves
PASS completion browser language documents edits and changed policies invalidate review without mislabeling POST access denial as expiry
PASS completion browser explicit private creation double-click suppression original receipt and later-session recovery preserve authoritative Profile edits
PASS completion browser receipt found in another tab preserves visible unacknowledged input and blocks new draft mutations
PASS completion browser uncertain COMMIT response recovers original receipt explicitly without completion replay
PASS completion browser null recovery remains uncertain and immutable dispatched intent survives language replacement and a later outcome
PASS completion browser lost confirmation response reload and unavailable draft or policies recover durable receipt without browser storage
PASS completion browser newer edit save and review retire obsolete receipt responses without replacing input
PASS completion browser delayed reads cross-account CSRF mismatch and invalid CSRF stop every protected boundary
PASS completion browser obsolete complete responses expiry and persisted-page restoration require fresh authenticated context
PASS completion browser honest absent values AR-EN RTL-LTR themes 1440-1024-768-390-320 keyboard focus and live status
PASS journey browser explicit keyboard Dashboard after policy-independent receipt recovery preserves later Profile and never redirects automatically
PASS journey browser acknowledged Save and exit includes newer edits then pauses and explicitly resumes exact saved values
PASS journey browser rejected and lost-acknowledgement exits retain input immutable retries and editor location
PASS journey browser null-policy gate-off pause AR-EN RTL-LTR mobile account controls and revoked-session navigation boundary
PASS journey D1 browser three private signed-in entries fixed Dashboard AR-EN RTL-LTR themes desktop mobile without automatic authentication or writes
PASS journey D1 browser incomplete Resume and null-policy or gate-off unavailable account controls without mutation
PASS journey D1 browser explicit keyboard Dashboard and server-routed Resume preserve current Profile and saved values
PASS final-profile browser fixture private logs/data/process/session cleanup pending final snapshot
PASS final-profile HTTP private logs and complete process/session/file/database fixture cleanup
PASS editor fixture unrepresented fields and private log boundaries
PASS editor fixture existing data and process/session cleanup
PASS completion browser fixture protected data and private source/session/file/process/database cleanup
L03_CI_BROWSER_STEPS_PASSED
```

## HTTP smoke and focused L-03 checks

The sign-in requests from the HTTP smoke step and the new query states were checked through the real front controller on the disposable Apache server:

```text
PASS HTTP 200 /signin.php; locale/direction and exact account notice
PASS HTTP 200 /signin.php?lang=en; locale/direction and exact account notice
PASS HTTP 200 /signin.php?account=deletion_scheduled; locale/direction and exact account notice
PASS HTTP 200 /signin.php?lang=en&account=deletion_scheduled; locale/direction and exact account notice
PASS HTTP 200 /signin.php?account=unknown; locale/direction and exact account notice
PASS HTTP 200 /signin.php?account[]=deletion_scheduled; locale/direction and exact account notice
PASS new Settings data section: large text, visible keyboard focus, AR/EN light/dark at 320/390/768/1280
```

The new registered browser module also checked exact native POST bodies with JavaScript disabled for Settings reauthentication, export, deletion request, scheduled cancellation, scheduled reauthentication, scheduled export and logout, in both languages. It verified native keyboard disclosure, server-open error disclosure, alert/field association, strict query notices, first/primary cancellation, no sidebar, browser-local date conversion across midnight, text contrast, 44px controls, CSP markup, reduced motion and no horizontal overflow at 320/390/768/1280/1440px.

## PHPStan: the 22 known entries

Exit 1 is retained and reported, rather than hiding or changing baseline entries. Every reported identifier is `ignore.unmatched`; there are no errors in new or changed code.

| File | Known unmatched entries |
|---|---:|
| `database/backfill-v1-ownership.php` | 1 |
| `database/capture-owner-journey-cutover.php` | 2 |
| `scripts/ops/migrate-phase2-private-media.php` | 1 |
| `scripts/ops/stage4c-recovery.php` | 1 |
| `tests/Browser/evidence-hub-final-ui-visual.php` | 1 |
| `tests/Integration/run-account-entry-tests.php` | 1 |
| `tests/Integration/run-evidence-hub-http-action-test.php` | 1 |
| `tests/Integration/run-onboarding-attestation-race-worker.php` | 1 |
| `tests/Integration/run-onboarding-completion-race-worker.php` | 1 |
| `tests/Integration/run-onboarding-draft-race-worker.php` | 1 |
| `tests/Integration/run-phase2-auth0-identity-worker.php` | 1 |
| `tests/Integration/run-phase2-ownership-race-worker.php` | 2 |
| `tests/Integration/run-phase2-public-slug-race-worker.php` | 2 |
| `tests/Integration/run-phase2-quota-worker.php` | 1 |
| `tests/Integration/run-phase2-rate-worker.php` | 1 |
| `tests/Integration/run-profile-version-race-worker.php` | 1 |
| `tests/Integration/run-registration-core-race-worker.php` | 1 |
| `tests/Support/publication-http-client.php` | 1 |
| `tests/Support/registration-oidc-mock.php` | 1 |

## Resolved verification failures

- The first full gate exposed an accidental Windows-default decoding change to Arabic in two existing test fixtures. Both files were restored from the base as UTF-8 and only the intended fixture wiring/count edits reapplied. The final full gate passed with the original Arabic expectations.
- Initial PHPStan found five new test-helper DOM typing errors. Explicit element assertions resolved them; the final result has only the 22 known entries.
- Local CI harness setup initially gave its generated user fewer permissions than `ci.yml`, omitted the storage configuration supplied by the production image, and briefly raced MySQL initial startup. The harness was corrected to match the workflow environment and wait for TCP readiness. All 26 rehearsal runners and both held browser fixtures subsequently passed. No application code or assertions changed for these setup issues.

## Scope

No merge, deployment, live migration, running-stack change, new dependency, backend behavior change or contract deviation. One existing assertion changed (Settings navigation count 2 → 3); see [README.md](README.md). Raw logs remain outside the repository; this document contains only sanitized verification outcomes.
