# L-01 invitation field review evidence

Base: `38651e8495b8cd02c114543188419971c379bfb0` (`origin/main`), including backend PR #99. Branch: `feat/l01-invitation-field`. Verified 2026-10-31.

The invite-only Google registration form adds a required, visibly labelled invitation field, a format hint, and the catalogued AR/EN invalid-code alert. The field stays empty after an error. Its 48px input, keyboard outline, text/border contrast and wrapping use the existing auth design tokens. There is no new script or inline style. All new copy is escaped by `signUpCopy()`.

The only request-logic addition is the permitted read-only `auth0RegistrationRequiresInvitation()` call in `handlers/signup.php`. The registration contract, backend registration handler, authorization, validation, database and PHPStan baseline are unchanged.

## Verification

| Check | Result |
| --- | --- |
| PHPUnit 12.5.42 / PHP 8.3.33 | `OK (192 tests, 985 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| Account-entry static runner | `PASS account entry presentation boundaries: 545 checks.` |
| PHPStan, CI Composer runtime | `composer analyse`: `[OK] No errors` |
| PHPStan, PHP 8.3 application image | Exactly the 22 known unmatched-baseline diagnostics; no new diagnostics or baseline changes |
| Full required gates, final executable source | `REQUIRED_GATES_PASSED`; production security passed; HTTP action matrix exit 0 (24 positive, 38 negative, 24 disclosure assertions) |
| New signup browser driver | All three modes, AR/EN, light/dark, 320/390/768/1280/1440px; native no-JS required validation and exact actual POST fields; keyboard focus, 48px input, AA text/UI contrast, announced error and no echo |
| Open/closed visual regression | Pixel-identical to `origin/main` in all 40 language/theme/width combinations |
| Existing registration HTTP rehearsal | Real invite-mode form POST returns the contracted 303; AR/EN errors announced; exact field set and no code echo; all existing routing assertions pass |
| Existing authenticated completion browser gate | Passed, including D1 sign-up/account controls and the added invitation-field absence assertion; fixture preservation/cleanup verified |
| CI coverage step | Passed PHPUnit, all eight self-contained runners and merge; 64.57% lines (7258/11241) |
| CI production HTTP smoke step | Entire step passed, including AR/EN signup GET, signup CSS, signup POST 405 and closed owner-register POST 404 |
| Syntax / whitespace / line endings | Four changed PHP files lint clean; both edited/new JS drivers pass `node --check`; `git diff --check`; LF only |

## Assertion inventory

**Changed, removed or weakened existing assertions: none.** No old-markup assertion required replacement.

Additions:

- `tests/Integration/run-account-entry-tests.php`: test all three modes in AR/EN with absent, exact-invalid and structured-invalid query states; exact field names; invite-only field; empty value/no echo; visible translated label; required/64-character/LTR/input attributes; format hint; connected hint/error and `aria-invalid`; `role="alert"`; CSP-safe markup; open/closed HTML unchanged by invitation queries. Every existing closed-mode and preview boundary remains intact.
- `tests/Integration/run-registration-routing-tests.php`: inspect the real invite form's exact field set and required field in both locales; submit that field set to the real backend; require 303 and the exact contract redirect; require the localized announced error and no code echo. Existing open/closed, CSRF, allow-list, callback, tenancy and data-preservation assertions remain intact.
- `tests/Browser/run-onboarding-completion-browser-tests.mjs`: additionally assert that authenticated entries contain no invitation field. The existing exact protected forms assertion is unchanged.
- `tests/Browser/run-signup-browser-tests.mjs`: new mode/locale/theme/width matrix; exact actual no-JS POST payloads and native required validation; visible label/format, required attributes, empty error field, linked/announced error, keyboard focus, 44px minimum, AA text/border contrast, overflow, optional baseline pixel equality, and unchanged closed 404/signup 405 behavior.
- `.github/workflows/ci.yml`: register the new signup browser gate using the existing Playwright tools and tested production image.

## CI signup-route inventory

Literal audit: `rg -n 'signup.php|owner_register.php' .github/workflows/ci.yml`, then trace the runner/fixture calls. These steps were executed before push:

| CI job / step | Evidence |
| --- | --- |
| `phpunit-coverage` / PHPUnit and self-contained runners with line coverage | Full step and eight runners passed, including account-entry |
| `production-smoke` / Phase 2 test foundation | Entire runner list passed, including account-entry |
| `production-smoke` / Run isolated CLI rehearsals | Entire step passed, including registration routing and owner journey |
| `production-smoke` / Authenticated completion editor browser checks | Full non-root Linux browser step passed, including real signup entry visits |
| `production-smoke` / Sign-up invitation browser checks | Production image with CI-pinned Playwright 1.58.0 passed |
| `production-smoke` / HTTP smoke test | Entire step passed, including all literal signup/owner-register checks |

Local reproduction used a disposable `ather-l01-ci` Compose project and port 18098. Its browser evidence used a private named volume. Registration/entry browser fixtures ran as UID 1001 in the Linux CI image. The new standalone signup matrix ran on Windows Chromium 1208 with CI-pinned Playwright 1.58.0; the local nested Linux runner could not reach its dynamically published loopback ports. Earlier Playwright 1.58.2 verification also passed the origin/main pixel comparison. Setup-only retries corrected the local Compose evidence-volume syntax and retained the mock's required literal `/tmp/ather-career-ci-oidc` path; no application code or assertions were changed for those retries.

All test databases/containers and private fixture carriers were cleaned. No live stack, live migration, merge or deployment was performed. Complete verification output is retained outside Git in the local `ather-l01-frontend-20261031/verification-output.txt` evidence folder, with generated credentials and CSRF values redacted. Raw logs are not committed.

## Screenshots

Production image; Chromium 1208 / Playwright 1.58.0. Invite captures include the keyboard focus outline. Error captures have an empty code field.

| Locale / theme | Invite 1440 | Invite 390 | Invalid 1440 | Invalid 390 |
| --- | --- | --- | --- | --- |
| AR / light | [invite 1440](invite-ar-light-1440.png) | [invite 390](invite-ar-light-390.png) | [invalid 1440](invalid-ar-light-1440.png) | [invalid 390](invalid-ar-light-390.png) |
| AR / dark | [invite 1440](invite-ar-dark-1440.png) | [invite 390](invite-ar-dark-390.png) | [invalid 1440](invalid-ar-dark-1440.png) | [invalid 390](invalid-ar-dark-390.png) |
| EN / light | [invite 1440](invite-en-light-1440.png) | [invite 390](invite-en-light-390.png) | [invalid 1440](invalid-en-light-1440.png) | [invalid 390](invalid-en-light-390.png) |
| EN / dark | [invite 1440](invite-en-dark-1440.png) | [invite 390](invite-en-dark-390.png) | [invalid 1440](invalid-en-dark-1440.png) | [invalid 390](invalid-en-dark-390.png) |

Automated alert semantics and keyboard/contrast checks are included; manual screen-reader speech, Safari and physical devices were not tested.
