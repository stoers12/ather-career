# FE-04 — Projects list and editor (ق-33)

Projects now has a searchable grid/list, native card menus and a three-step editor with one complete multipart form. The list and editor work without JavaScript; the preview and filtering are enhancements. Request handling, validation, queries, publication mutations, rate limits and redirects are unchanged.

Base: `a1a8cd3` from `origin/main`. Visual authority: the owner's `ATHER-Agent-Handoff (2).zip`, extracted outside the repository. Every write and capture uses synthetic fixtures or a generated disposable gate database. The resident showcase and running stack were never used for writes.

## Verification

| Check | Key result |
| --- | --- |
| PHPUnit | `OK (169 tests, 679 assertions)` |
| `scripts/run-phase2-tests.php` | `Phase 2 test foundation passed.` |
| PHPStan | Exactly the same 22 known unmatched-baseline reports; baseline unchanged |
| Full required gates | `REQUIRED_GATES_PASSED`; `Production security gate passed.` |
| Projects browser checks | `OWNER_PROJECTS_VISUAL_PASSED`; `OWNER_PROJECTS_ACTIONS_PASSED` |
| CI isolated CLI rehearsals | All 24: `CI_ISOLATED_REHEARSALS_PASSED` |
| CI private project publication browser | `CI_PROJECT_PUBLICATION_BROWSER_PASSED` |
| JavaScript and PHP syntax | `node --check` and `php -l` pass |
| Patch hygiene | `git diff --check`; LF for changed text; no FE-04 raw logs in branch history |

The browser gate covers real search/filter counts (including technologies behind +N), grid/list, keyboard and no-JS menus, native stacked steps, next/back, native validation of hidden fields, first 422 error step/focus, escaped live preview, saving step 2 while preserving step 1, authenticated live URL save/keep/clear/reject, rejected upload retention, and real image upload/removal through the existing confirmation dialog. Layout and AA text contrast run in both locales and themes; default/large text has no horizontal overflow at 390/768/1280/1920/2560px. Server-side rendering tests additionally check escaped values/search/confirm attributes, exact fields, optionality, independent CSRF publication forms and native routes.

CI uses a generated MySQL 8.4 database and database-scoped account in a separate Docker network, PHP 8.3, writable disposable media/rate state, and Playwright 1.58.2. The full 24 CLI entry points from the workflow were run. The migration ledger checkpoints and required columns were also verified. The final publication browser test opens the native disclosure after each navigation before inspecting or activating its controls; all checks were rerun for the review revision.

The test inventory search included `.github/workflows/ci.yml`, `tests/`, and the included `tests/Support/integrated-journey-acceptance.php` and `project-publication-http.php` fixtures. All matches that load Projects are covered by Phase 2/PHPUnit, the registered workspace gate, the 24 CLI rehearsals or the publication browser test.

## Differences from the reference

- Actual images use `owner_media.php?type=project&id=...`; missing images use the same neutral CSS-only cover across projects. The reference's invented report, CV and branded project illustrations are intentionally absent.
- The fixture has three selected projects and one draft, rather than four selected projects. Counts, tags and text reflect the supplied rows; there are no invented evidence/readiness claims.
- The shared FE-01–FE-03 shell, ق-32 palette and AA contrast adjustments remain authoritative. It has real account navigation and no demo footer or fabricated notification badge. Small spacing/text differences follow those existing shared controls and 44px targets.
- Publication is a native disclosure with a short state and the unchanged `renderProjectPublicationControl()` status/help and independent CSRF POST inside. This preserves actual publication behavior instead of the prototype's in-memory toggle. A selected project in a private portfolio explicitly says the portfolio is private.
- The editor has a real image picker, current-image/removal controls and server error hints in step 2. Technologies remain one entry per line, matching the backend contract; the prototype uses comma-separated text.
- Step 3 is read-only, includes both real URLs, and explains independent project publication and private-portfolio visibility. No publication fields are added to the content form. It links to Publishing when the portfolio is private.
- “Save project” submits the complete form from any step. Previous/Next only move between steps. There is no automatic-save/saved claim before a POST succeeds; “New draft” only appears for new projects.
- Below 1000px, the preview stacks after the form so fields stay readable. On mobile, step labels wrap rather than truncate. Without JavaScript all three steps stack, filters/view controls and the live preview are hidden; the existing shared theme resolver falls back to light.
- The reference provides a static image only for editor step 1. Steps 2/3, English, validation and no-JS rows below compare against that baseline geometry; their actual behavior follows the supplied prototype and this slice's contracts.

The UI/UX Pro Max checker was used for error placement, focus, contrast, touch targets and responsive review only. No color/font/design-system recommendations were applied or persisted.

## Every changed existing test assertion

| File | Exact adaptation and preserved rule |
| --- | --- |
| `OwnerFrontendExperienceStaticTest.php` | Read the handler plus the extracted presentation/components for the same form, connected error, required, pending, confirmation, CSRF, image-removal and empty-state assertions. |
| `OwnerFlowStaticTest.php` | Follow the extracted presentation for delete confirmation; read presentation/components for GitHub checks. The optional-label source check follows `ownerProjectsField(... false, ...)` instead of the old inline `(optional)` call. Optional URL/validation assertions remain; rendered optionality is also tested in PHPUnit. |
| `PrivateMediaStaticTest.php` | Follow the extracted presentation/components for the same scoped owner image delivery/upload assertions. |
| `ProjectTechnologiesStaticTest.php` | Follow the extracted presentation/components and check the `ownerProjectsField('technologies', ...)` call instead of the old literal textarea. The parser, newline hint, limits and storage assertions remain; the rendered field is also checked in PHPUnit. |
| `OwnerWorkspacePresentationTest.php` | Include the extracted markup for the no-duplicate-preview check and check the add destination in its new presentation file. |
| `tests/Support/integrated-journey-acceptance.php` | Match the new H1 “Give your work a place to be seen.” instead of “Add New Project”. Check the empty invitation against the Projects list, because the separate add editor no longer renders that list. Blank add title, no inherited id, exact add action/CSRF, GET row preservation and the real Dashboard invitation remain asserted. |
| `run-project-publication-browser-tests.mjs` | Select `.owner-project-grid article`; open the native publication disclosure before keyboard hide/show and responsive checks; submit “Save project” instead of “Update Project”. All existing media200/private404, mixed JSON, saved publication preservation, keyboard and overflow assertions remain. |

`owner-workspace-visual.cjs` only gains registration of the Projects extension; its existing assertions are unchanged. No existing assertion was deleted to suppress a failure.

## Old CSS

Removed unused `.project-thumbnail`, `.project-edit`, `.project-github`, `.project-delete`, `.card-primary-actions` and `.file-picker` rules/selectors from the old stylesheets. Kept shared `.project-form-card`, `.project-form`, `.admin-project-grid`, `.project-card-content`, `.project-category` and `.card-actions` because Experience still uses them. Projects loads only the shared workspace stylesheet plus one page stylesheet.

## Reference next to result

All images retain their original dimensions. The reference column is the handoff screenshot, not a generated approximation. Step 2/3, validation, no-JS and English have no dedicated static handoff capture; their reference shows the corresponding base layout.

| View | Reference | Result |
| --- | --- | --- |
| List · 1440 ar light | <img src="reference-desktop-ar-light-03-projects.png" width="300" alt="Reference: List · 1440 ar light"> | <img src="projects-ar-light-1440.png" width="300" alt="Result: List · 1440 ar light"> |
| List · 1440 ar dark | <img src="reference-desktop-ar-dark-03-projects.png" width="300" alt="Reference: List · 1440 ar dark"> | <img src="projects-ar-dark-1440.png" width="300" alt="Result: List · 1440 ar dark"> |
| Editor step 1 · 1440 ar light | <img src="reference-desktop-ar-light-04-project-edit.png" width="300" alt="Reference: Editor step 1 · 1440 ar light"> | <img src="project-editor-ar-light-step-1-1440.png" width="300" alt="Result: Editor step 1 · 1440 ar light"> |
| Editor step 2 · 1440 ar light | <img src="reference-desktop-ar-light-04-project-edit.png" width="300" alt="Reference: Editor step 2 · 1440 ar light"> | <img src="project-editor-ar-light-step-2-1440.png" width="300" alt="Result: Editor step 2 · 1440 ar light"> |
| Editor step 3 · 1440 ar light | <img src="reference-desktop-ar-light-04-project-edit.png" width="300" alt="Reference: Editor step 3 · 1440 ar light"> | <img src="project-editor-ar-light-step-3-1440.png" width="300" alt="Result: Editor step 3 · 1440 ar light"> |
| New project · 1440 ar light | <img src="reference-desktop-ar-light-05-project-add.png" width="300" alt="Reference: New project · 1440 ar light"> | <img src="project-new-ar-light-1440.png" width="300" alt="Result: New project · 1440 ar light"> |
| List · 390 ar light | <img src="reference-mobile-ar-light-02-projects.png" width="300" alt="Reference: List · 390 ar light"> | <img src="projects-ar-light-390.png" width="300" alt="Result: List · 390 ar light"> |
| List · 390 ar dark | <img src="reference-mobile-ar-dark-02-projects.png" width="300" alt="Reference: List · 390 ar dark"> | <img src="projects-ar-dark-390.png" width="300" alt="Result: List · 390 ar dark"> |
| Editor · 390 ar light | <img src="reference-mobile-ar-light-03-project-edit.png" width="300" alt="Reference: Editor · 390 ar light"> | <img src="project-editor-ar-light-390.png" width="300" alt="Result: Editor · 390 ar light"> |
| Editor · 390 ar dark | <img src="reference-mobile-ar-dark-03-project-edit.png" width="300" alt="Reference: Editor · 390 ar dark"> | <img src="project-editor-ar-dark-390.png" width="300" alt="Result: Editor · 390 ar dark"> |
| List without JavaScript · 390 ar | <img src="reference-mobile-ar-light-02-projects.png" width="300" alt="Reference: List without JavaScript · 390 ar"> | <img src="projects-no-js-ar-light-390.png" width="300" alt="Result: List without JavaScript · 390 ar"> |
| Editor without JavaScript · 390 ar | <img src="reference-mobile-ar-light-03-project-edit.png" width="300" alt="Reference: Editor without JavaScript · 390 ar"> | <img src="project-editor-no-js-ar-light-390.png" width="300" alt="Result: Editor without JavaScript · 390 ar"> |
| Validation · 1440 ar light | <img src="reference-desktop-ar-light-04-project-edit.png" width="300" alt="Reference: Validation · 1440 ar light"> | <img src="project-validation-ar-light-1440.png" width="300" alt="Result: Validation · 1440 ar light"> |
| List · 1440 en light | <img src="reference-desktop-ar-light-03-projects.png" width="300" alt="Reference: List · 1440 en light"> | <img src="projects-en-light-1440.png" width="300" alt="Result: List · 1440 en light"> |
| Editor · 1440 en light | <img src="reference-desktop-ar-light-04-project-edit.png" width="300" alt="Reference: Editor · 1440 en light"> | <img src="project-editor-en-light-1440.png" width="300" alt="Result: Editor · 1440 en light"> |

## CI PASS lines

Full logs are kept outside the repository. The key CI PASS lines are:

```text
PASS T-OWNER-PROJECT-LIVE-URL
PASS owner flow rehearsal
PASS journey protected data and private source process session log file database fixture cleanup
PASS ownership migration checkpoint 001–004
PASS complete migration ledger and required project columns
PASS project-publication browser actual explicit keyboard hide/show save/reload public image200 private404 and mixed JSON
PASS project-publication browser ordinary content edit preserves separate publication choice
PASS project-publication browser responsive stored-state/help keyboard controls at 1440 1024 768 390 320px
PASS project-publication full preexisting row ledger seal media/session/process fixture preservation and cleanup
CI_ISOLATED_REHEARSALS_PASSED
CI_PROJECT_PUBLICATION_BROWSER_PASSED
```

## PR #87 review fixes

- Removed the FE-04 raw log files and rewrote the task branch so the files are absent from its commit history. This README retains verification results; full logs stay outside git.
- Kept the native image input labelled and keyboard focusable beneath a localized trigger label. Focus is visible on the trigger. The existing file-selection status shows the selected filename with localized copy and text-node escaping. Native file selection and multipart submission work without JavaScript.
- Textareas have six rows; technologies also has an 11em minimum height, including without JavaScript.
- Moved the inline bilingual Projects copy, step labels, review labels, filters and required/optional labels to `includes/owner_projects_copy.php`, using the existing keyed-copy pattern.
- The live URL field uses `PROJECT_LIVE_URL_MAX_LENGTH`.
- Extended the existing browser gate with localized picker labels, keyboard selection, visible focus, escaped filename status, no-JS multipart selection and textarea-height checks. The rendering test checks these native contracts in both languages. No existing assertion was weakened.
