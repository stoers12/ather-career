# L-02 legal publication and consent review

Base: `a623e4feb531ebf2cb7c5e41572f88b50de54c06` (`origin/main`, merged backend PR #102). Branch: `feat/l02-legal-pages`.

The approved Privacy Policy and Terms are ready behind an **off-by-default publication gate**. `/privacy` and `/terms` return the application's exact normal 404 while unpublished. No legal links or signup checkbox are rendered while the gate is off. When published, the pages show the current document version and effective date, public caching/indexing, AR/EN text, section anchors and existing public-surface typography/colors. The signup checkbox has a native required value of `accept_legal=1`, linked documents and a localized announced `?legal=required` error. The contact comes exclusively from `legalPrivacyContactEmail()`.

## Publication controls and approved copy

- `LEGAL_PAGES_PUBLISHED = false` and `LEGAL_DOCUMENT_EFFECTIVE_DATE = null` are added only in `includes/legal_documents.php`.
- `legalPagesPublished()` reads the constant. There is no request, cookie or environment publication override.
- `LEGAL_CONSENT_REQUIRED = false` and `tests/Unit/LegalConsentTest.php` are unchanged. The owner enables publication, the date and required consent together in a separate launch change.
- Tests exercise publication using guarded disposable source/container copies. Only the publication/date literals are changed, and the fixture verifies the replacement is reversible without changing other source.
- The backend consent handler, Auth0 callback, registration transaction, database and legal contract are unchanged.

Four UTF-8/LF sources under `resources/legal/` each hold one document/locale. Import uses only the supplied drafts below their first separator and splits the language wrappers. It preserves all approved sentences and applies exactly the requested substitutions: removes the hourglass symbols; omits the email-provider row; replaces date/version/contact values with runtime tokens. When contact is null, only its contact-bearing lines are omitted; section headings/anchors remain stable. No platform contact address is hard-coded.

The browser check compares the complete rendered article text with the imported approved wording, including all headings, paragraphs, list entries, table cells and punctuation. These SHA-256 pins independently prevent later source drift:

| Source | SHA-256 |
| --- | --- |
| [privacy.ar.md](../../../resources/legal/privacy.ar.md) | `e714078a1b5951e2ea2497e1718b899dfa2c5e9536a1686f718f000ee694abdd` |
| [privacy.en.md](../../../resources/legal/privacy.en.md) | `675bdd5664a2351f43d2d221c1acbff3dc39e42a1c6c26829d68cfb905d305ae` |
| [terms.ar.md](../../../resources/legal/terms.ar.md) | `ecf2b2a09f56322c6de690623805c317dd0c80045aed946d4769bfd9826ee030` |
| [terms.en.md](../../../resources/legal/terms.en.md) | `58cfabba44e3a9e16273c20cd00134840d00ee6991c5d83287a7d89434955c4a` |

## Verification before push

| Check | Final result |
| --- | --- |
| PHPUnit / PHP 8.3.33 | `OK (207 tests, 1104 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan, PHP 8.3 image | Exactly the 22 known unmatched-baseline entries; no new entries; baseline unchanged |
| PHPStan, CI Composer runtime | `composer analyse`: `[OK] No errors` |
| Full required gates | `REQUIRED_GATES_PASSED`, including Settings/workspace/public UI checks and production security |
| Complete CI coverage step | Passed PHPUnit, all eight self-contained runners and merge; 64.26% lines (7308/11372) |
| Legal browser, production image | Passed exact AR/EN wording, version/effective date, configured/null contact, public caching/indexability, GET/HEAD/405, normal off-state 404, anchors, AA contrast, CSP and no overflow at 320/390/768/1280/1440px |
| Legal links and checkbox | Correct on/off behavior on signin, signup, public portfolio footer and Settings Help; open/invite native no-JS payloads include exactly `accept_legal=1`; required validation, associated 44px+ label/links, keyboard focus and announced error |
| Gate-off visual regression | Signup and public footer pixel-identical to baseline image revision `a623e4feb531ebf2cb7c5e41572f88b50de54c06` |
| Real MySQL/Auth0-mock registration | Both current policy rows recorded for open and invite registrations; invite consumed for that same account; complete data/fixture cleanup verified |
| Existing signup browser CI step | Invite/open/closed; AR/EN; light/dark; five widths; exact payloads and no-JS validation passed |
| Existing CI browser steps | Draft editor, final-profile, project-publication/public portfolio, attestation and completion/signed-in entry steps all passed in non-root Linux |
| Entire CI HTTP smoke step | Passed signup/signin, public portfolios, assets, redirects, cookies and other existing assertions |
| Source checks | 15 changed/new PHP files lint clean; new browser driver passes `node --check`; `git diff --check`; changed text sources LF only |

## Changed assertion inventory

Exactly one existing assertion changed:

- `tests/Unit/RouterTest.php::testEveryHandlerExistsAndIsRouted`: `assertCount(32, $handlers)` becomes `assertCount(34, $handlers)`, accounting for `handlers/privacy.php` and `handlers/terms.php`. The same rule still requires the exact handler count, all handlers to exist and every handler except readiness to be routed. No selector or safety rule is weakened.

All other coverage is additive:

- `RouterTest`: two new route data-provider cases, `/privacy` and `/terms`.
- `LegalPagesPresentationTest`: production defaults, absence of request/environment overrides, no unpublished markup, four approved-copy hashes, conditional contact lines with stable sections, safe formatting/URLs and path traversal denial.
- `legal-registration-acceptance.php`, invoked by the existing registration routing rehearsal: parse the real published form, POST its exact field set, check fresh remembered versions, complete the signed OIDC callback and query both MySQL acceptance rows; prove invite consumption belongs to the same account and restore the complete prior database snapshot.
- `run-legal-pages-browser-tests.mjs`: gate on/off, all requested link locations, exact visible text, version/date/contact, native checkbox value/validation/payload, invite compatibility, error semantics, keyboard, AA, five-width overflow, 33 screenshots and optional baseline pixel equality.
- `ci.yml`: register the new legal browser step using the existing pinned Playwright tools and tested image.

`LegalConsentTest.php` remains byte-for-byte unchanged. No other existing assertion was replaced, removed or weakened.

## CI scope and local execution adaptations

Audit: `rg -n 'signup|signin|portfolio|settings' .github/workflows/ci.yml`, followed by tracing its PHP/browser fixture calls. Executed steps that load the requested surfaces:

- `phpunit-coverage`: full PHPUnit/self-contained-runner coverage step.
- `production-smoke`: full Phase 2 runner list; full isolated CLI rehearsal list; existing signup browser; new legal browser; all five existing editor/profile/project/attestation/completion browser steps; entire HTTP smoke step.
- Full required-gates script: registered Settings, public/owner workspace and security checks.

The full CLI run includes the new MySQL-backed consent acceptance and passed. Its initial replay then stopped before browser tests because the local non-root image could not run the CI dependency installer's `su` escalation. The resumed replay validated and reused the already-installed Playwright 1.58.0/browser/OS dependencies in the official Playwright image, restored only the disposable database prerequisites and passed every remaining step. No application code or assertions were changed for this local setup issue.

Other local verification adaptations:

- Separate Compose project `ather-l02-ci`, web port 18198 and private named evidence volume; all existing browser steps ran as UID 1001 in Linux.
- New legal/signup standalone drivers ran on Windows Chromium 1208 with CI-pinned Playwright 1.58.0 against disposable production-image Apache containers. The required-gates runner uses its pinned Playwright 1.58.2 runtime.
- The two `.php` entry aliases `/privacy.php` and `/terms.php` follow the existing router's script-map convention; both aliases obey the same publication gate as the requested pretty URLs.
- Early local driver checks were corrected for variable scope and semantic block-text extraction. An incidental encoding change in existing UTF-8 test/config text was restored before final verification; existing assertion text is intact.

**Functional deviations from the final prompt: none.** The original contract's launch-time consent flip is deliberately deferred as the final prompt requires. No new runtime dependencies were added. Automated checks cover markup, keyboard, contrast and layout; manual screen-reader speech, Safari and physical devices were not tested.

## Screenshots

All published captures use private test copies with a synthetic effective date of `2026-11-01` and a synthetic configured contact, never a production publication change.

| Page / locale / theme | 1440px | 390px |
| --- | --- | --- |
| privacy / AR / light | [1440](privacy-ar-light-1440.png) | [390](privacy-ar-light-390.png) |
| privacy / AR / dark | [1440](privacy-ar-dark-1440.png) | [390](privacy-ar-dark-390.png) |
| privacy / EN / light | [1440](privacy-en-light-1440.png) | [390](privacy-en-light-390.png) |
| privacy / EN / dark | [1440](privacy-en-dark-1440.png) | [390](privacy-en-dark-390.png) |
| terms / AR / light | [1440](terms-ar-light-1440.png) | [390](terms-ar-light-390.png) |
| terms / AR / dark | [1440](terms-ar-dark-1440.png) | [390](terms-ar-dark-390.png) |
| terms / EN / light | [1440](terms-en-light-1440.png) | [390](terms-en-light-390.png) |
| terms / EN / dark | [1440](terms-en-dark-1440.png) | [390](terms-en-dark-390.png) |
| signup / AR / light | [1440](signup-ar-light-1440.png) | [390](signup-ar-light-390.png) |
| signup / AR / dark | [1440](signup-ar-dark-1440.png) | [390](signup-ar-dark-390.png) |
| signup / EN / light | [1440](signup-en-light-1440.png) | [390](signup-en-light-390.png) |
| signup / EN / dark | [1440](signup-en-dark-1440.png) | [390](signup-en-dark-390.png) |
| signup-error / AR / light | [1440](signup-error-ar-light-1440.png) | [390](signup-error-ar-light-390.png) |
| signup-error / AR / dark | [1440](signup-error-ar-dark-1440.png) | [390](signup-error-ar-dark-390.png) |
| signup-error / EN / light | [1440](signup-error-en-light-1440.png) | [390](signup-error-en-light-390.png) |
| signup-error / EN / dark | [1440](signup-error-en-dark-1440.png) | [390](signup-error-en-dark-390.png) |

[Gate off: unchanged signup and public footer](gate-off-unchanged.png). The comparison used the exact current-main baseline image and verified pixel equality before composing this proof image.

Raw logs are not committed. Complete redacted verification output is kept outside Git at `C:\Users\momen\AppData\Local\Temp\ather-l02-frontend-20261101\verification-output.txt`. The disposable test stack and private carriers were cleaned; the owner's running stack was untouched. No merge, deployment or live migration was performed.
