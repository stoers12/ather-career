# FE-03 — Profile and skills

Decision ق-33. Base `484a50f` (`origin/main` after FE-01 #81 and FE-02 #83). Visual authority: the owner's `ATHER-Agent-Handoff (2).zip`, extracted outside the repository. The two Arabic `02-profile.png` files determine the look; the prototype's three Profile sections determine the interaction. Captures and all write tests use synthetic data only.

## Implementation boundaries

The request-handling prefix of `handlers/owner_profile.php`, through its database failure handling, is unchanged byte-for-byte from `origin/main`. Its presentation now calls `renderOwnerProfilePage()` with the same already-computed variables. No query, validation, authorization, contract, route, field name, form action/method, version logic or completion calculation changed.

One `save_profile` form contains both Basic information and Bio & links, including all original fields, visibility markers and final-profile controls. Photo upload/removal retain their original multipart/type/size hints and version tokens; skills retain their own native forms. The native anchors show all three panels without JavaScript. JavaScript enhances those anchors with one visible panel, current-section indication and error revelation/focus; it uses no partial tab-widget roles. Native validation also reveals a required field in another panel before the browser focuses it.

The page loads the shared workspace CSS plus its Profile sheet/controller, with external assets only. Retired Profile-only rules were removed from `owner_workspace.css`. The shared `.profile-form` rules remain because Publication still uses that class.

## Differences from the screenshot and why

- Photo controls sit in a native **Profile photo** disclosure in the identity card. Upload/removal forms and the remove confirmation are retained. The completion indicator also sits in that card, using the original calculation; both are real product features absent from the reference.
- **Additional details** in Basic information is a native disclosure containing the Hero headline, primary/secondary phones, phone visibility and all four final-profile fields. It keeps these existing controls in the same save form while keeping the reference's four-field opening layout. The secondary-phone privacy hint remains truthful. A disclosure containing an error opens in server markup as well as with JavaScript.
- The main card grows slightly to fit that additional-details disclosure. Existing product labels and truthful optional/required status remain: only full name is required by the existing form, while the prototype also marks the professional title required. The original contact-visibility explanation and bilingual final-profile labels/hints remain intact.
- Bio & links includes both original narratives and all five social URLs. The prototype has fewer fields and puts its Hero headline here; the task explicitly assigns the real Hero field to Basic information.
- Skills use editable inputs with real Save/Remove forms and a separate Add form, rather than the prototype's temporary in-memory tags. Skills save independently; the global profile save area is shown for the first two panels only, where it saves real profile fields.
- The idle footer says that Basic information, bio and links save together. It never claims everything is saved. A saved message/check appears only when the handler supplies a success message. Cancel reloads `owner_profile.php`.
- The prototype's design-exploration/demo footer is omitted. The actual unread count is shown only when available; visual fixtures have no authorized count, so no synthetic unread badge is invented.
- No mobile, English, Bio/links, Skills, no-JS or error PNG exists for this page. Those views use FE-01/FE-02 responsive, LTR, theme and large-text conventions plus the prototype behavior. Their comparisons reuse the supplied Arabic Basic information reference as the visual baseline; images are not rescaled. Without JavaScript all panels are stacked, both native disclosures remain available, and all forms retain their native actions.

## Existing test assertions updated

No assertion was removed or weakened. The following static checks now inspect the extracted presentation together with its original handler:

| Test | Same rule, new source location |
| --- | --- |
| `OwnerFrontendExperienceStaticTest` | Profile form markers/pending labels, connected feedback, safe submitted skill values/IDs, confirmations/CSRF and skill empty state inspect handler + template. |
| `OwnerFlowStaticTest` | The `profile-form` unsaved-change check inspects handler + template; the material-action confirmation check adds the Profile template only for that route. Session/auth/scoped-access checks still inspect handlers unchanged. |
| `HeroPersonalInfoCapabilityTest` | Existing Hero/summary field and guidance assertions inspect handler + template; validators/public precedence assertions are unchanged. |
| `PrivateMediaStaticTest` | Existing Profile minimum/configured maximum/normalization guidance assertions inspect handler + template; media security and image-processing assertions are unchanged. |
| `OwnerWorkspacePresentationTest` | The duplicate-preview absence check also inspects the extracted Profile template, preserving coverage of the moved markup. |

`HttpValidationContractTest` remains unchanged and passes. The four locale-sensitive route-rehearsal assertions are updated as listed in the review follow-up below. New native PHPUnit coverage verifies one save form/all original fields, all three visible no-JS panels, original version tokens and IDs, visibility choices, multipart/CSRF forms, escaping including confirmation text, error associations, no-JS error disclosures and truthful saved state.

## Verification

| Check | Output |
| --- | --- |
| PHPUnit | `OK (125 tests, 423 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | Only the 22 known unmatched baseline-ignore entries; no new errors |
| JavaScript syntax | Profile controller and extended workspace/browser drivers passed |
| Full required gates | `REQUIRED_GATES_PASSED` |
| CI-only Profile route rehearsals (run separately) | `PROFILE_ROUTE_REHEARSALS_PASSED` |
| Authenticated Profile lifecycle | `OWNER_PROFILE_ACTIONS_PASSED` |
| Profile visuals | `OWNER_PROFILE_VISUAL_PASSED` |
| Existing workspace/Settings/unread checks | Passed |

The existing registered workspace driver invokes the new Profile helper; gate registration is unchanged. Visual checks cover all three anchors with native Tab/Enter navigation, JavaScript disabled, first-error panel/focus, native required-field revelation, ar/en × light/dark × default/large text × 390/768/1280/1920/2560px, including expanded additional details, and inherited AA contrast checks.

Authenticated writes run only when the full gate supplies its disposable server/session. The test saves from Bio & links and checks retained Basic information, visibility and private final-profile values; checks 422 focus and 409 retention/reload; adds, edits, cancels then confirms skill removal; uploads a generated 400×400 PNG and confirms photo removal. It restores the synthetic profile using its latest version and cleans up its own skill/photo on failure. It never uses the resident showcase portfolio.

Reproduce and export fresh raw captures outside the repository:

```powershell
pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -EvidenceDirectory '<new-directory-outside-repository>'
```

## Visual comparisons

Reference left; result right. The only supplied reference is 1440 Arabic Basic information, in light and dark. The extra views intentionally use that same baseline. Images retain original dimensions; extra space below a shorter image is comparison padding.

| View | Light | Dark |
| --- | --- | --- |
| 1440 Arabic Basic information | [Compare](profile-ar-light-basics-1440.png) | [Compare](profile-ar-dark-basics-1440.png) |
| 1440 Arabic Bio & links | [Compare](profile-ar-light-about-1440.png) | [Compare](profile-ar-dark-about-1440.png) |
| 1440 Arabic Skills | [Compare](profile-ar-light-skills-1440.png) | [Compare](profile-ar-dark-skills-1440.png) |
| 1440 English Basic information | [Compare](profile-en-light-basics-1440.png) | — |
| 390 Arabic Basic information | [Compare](profile-ar-light-390.png) | [Compare](profile-ar-dark-390.png) |
| 390 Arabic without JavaScript | [Compare](profile-ar-no-js-390.png) | Default light fallback |
| 1440 Arabic validation error | [Compare](profile-ar-validation-1440.png) | — |

## Review follow-up — locale-neutral recovery assertions

The CI-only route rehearsal clients omit a locale and receive Arabic. Keep the improved localized recovery copy; check the explicit saved-page link using `class="owner-profile-reload"><a href="owner_profile.php">` instead of English wording. These are the only four assertion changes:

| Rehearsal assertion | Preserved rule |
| --- | --- |
| `tests/Integration/run-profile-version-route-tests.php:88` | Conflict retains escaped typed input, original version `1`, checked email visibility and an explicit recovery link. |
| `tests/Integration/run-profile-version-route-tests.php:186` | Pre-handler upload rejection retains the original upload version and an explicit recovery link. |
| `tests/Integration/run-final-profile-route-tests.php:100` | Conflict retains escaped typed input, original version `1`, checked email visibility and an explicit recovery link. |
| `tests/Integration/run-final-profile-route-tests.php:203` | Pre-handler upload rejection retains the original upload version and an explicit recovery link. |

Every other condition in these assertions is unchanged. Both route rehearsals are run locally in a separate disposable MySQL 8.4 database, with a database-scoped user and synthetic fixtures. They are not part of the required gates, so their PASS output is reported separately alongside PHPUnit, Phase 2, PHPStan and the full gates. No application copy or runtime code changes in this follow-up.

Local rehearsal output (the shared cutover seal was initialized before the snapshots; both suites verified complete cleanup):

```text
# tests/Integration/run-profile-version-route-tests.php
PASS profile real HTTP loaded tokens, two-tab conflict, retained text/visibility/original version, explicit recovery and same-value saves
PASS profile HTTP omitted fields, explicit NULL clears, deliberate visibility, invalid/missing tokens, malformed patches and exhaustion
PASS contact-types real HTTP four-combination saves reloads public HTML mailto tel contact errors JSON and private preview; per-type off malformed old aggregate409 retention and CSRF
PASS profile HTTP account isolation, CSRF, idle/absolute expiration, revocation and disabled authorization without new readiness gates
PASS profile real multipart upload/remove, text preservation and confirmed SQL rollback media cleanup
PASS profile HTTP pre-handler upload rejection retains original token and explicit reload requirement
PASS profile HTTP private logs and complete process/session/file/database fixture cleanup
# tests/Integration/run-final-profile-route-tests.php
PASS profile real HTTP loaded tokens, two-tab conflict, retained text/visibility/original version, explicit recovery and same-value saves
PASS final-profile real HTTP save/clear/omit/reload, exact Unicode, malformed/forged input, retired preservation, stale retention and public exclusion
PASS profile HTTP omitted fields, explicit NULL clears, deliberate visibility, invalid/missing tokens, malformed patches and exhaustion
PASS profile HTTP account isolation, CSRF, idle/absolute expiration, revocation and disabled authorization without new readiness gates
PASS profile real multipart upload/remove, text preservation and confirmed SQL rollback media cleanup
PASS profile HTTP pre-handler upload rejection retains original token and explicit reload requirement
PASS final-profile HTTP private logs and complete process/session/file/database fixture cleanup
PROFILE_ROUTE_REHEARSALS_PASSED
```

Re-run results: PHPUnit `OK (125 tests, 423 assertions)`; `Phase 2 test foundation passed.`; PHPStan only the 22 known unmatched ignore patterns, no new errors; full required gates `REQUIRED_GATES_PASSED`.

## Second CI follow-up: final-profile browser job

CI `Private final-profile controls browser checks` failed in both runs with `FAIL final-profile browser exact values [select-country]: TimeoutError`. A local reproduction of that job reproduced it exactly. It used a disposable MySQL 8.4, `tests/Integration/run-final-profile-route-tests.php` in browser-fixture mode, and `tests/Browser/run-final-profile-browser-tests.mjs` with Playwright 1.58.0 sharing the fixture's loopback, as CI does.

**Root cause:** the country, language, status and specialization selects, both phones and the phone-visibility checkbox now sit in the collapsed **Additional details** disclosure. The browser test drove them as always-visible controls, so Playwright waited for visibility. The disclosure collapses again after every save or reload.

**Changes:**

| Change | Why |
| --- | --- |
| `run-final-profile-browser-tests.mjs`: a `revealed(page, id)` helper | It opens any closed ancestor `<details>` through its summary, as a user does, before every interaction (fill, select, check, focus, bounds) with a control inside it. Reads (`inputValue`, `isChecked`, attributes) are unchanged. No check was removed or weakened. |
| The same test: a new conflict assertion | After the two-tab 409, the retained country and specialization must be visible. |
| `owner_profile_presentation.php`: Additional details opens server-side after a failed profile save (409/422) | A 409/422 re-render carries retained, unsaved input in that disclosure. A collapsed disclosure hid the owner's own unsaved changes. Errors inside it already opened it; a failed save now does too. A normal load stays collapsed. |
| `OwnerProfilePresentationTest`: `testFailedSaveRevealsRetainedAdditionalDetails` | Locks both states. |

**Local evidence:**
- The reproduced CI browser job passes all six stages, including the new conflict assertion.
- Both profile route rehearsals pass with empty before/after data diffs.
- PHPUnit `OK (126 tests, 425 assertions)`.
- `Phase 2 test foundation passed.`
- PHPStan reports only the 22 known entries.
- Full required gates: `REQUIRED_GATES_PASSED`.

`run-onboarding-completion-browser-tests.mjs` only reads the visible `full_name` on this page, so it is unaffected.
