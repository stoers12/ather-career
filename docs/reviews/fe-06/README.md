# FE-06 — Publishing and Messages (ق-33)

Publishing and Messages now follow the handoff within the established workspace shell. Each page has one copy file and its own presentation stylesheet. Both handler request prefixes are byte-identical to `origin/main` (28a4b80); validation, queries, authorization, redirects, response codes, and public portfolio rendering are unchanged.

The ZIP references match the extraction outside the repository byte for byte. UI/UX Pro Max was used as a checker for accessible feedback, keyboard, contrast, touch and responsive layouts. No design system or dependency was added. All test writes used generated disposable databases and synthetic owners; the resident showcase and running stack were untouched.

## Verification

| Check | Result |
| --- | --- |
| PHPUnit | `OK (173 tests, 807 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | Exactly the same 22 known unmatched-baseline entries as FE-05; no new findings or baseline changes |
| Full required gates | `REQUIRED_GATES_PASSED`; `Production security gate passed.` |
| Registration challenge | `REGISTRATION_CHALLENGES_PASSED` |
| New workspace browser checks | `OWNER_SHARING_VISUAL_PASSED`; `OWNER_SHARING_ACTIONS_PASSED` |
| Isolated CI rehearsals | `CI_ISOLATED_REHEARSALS_PASSED`: all 24 current workflow entry points returned zero |
| Production-smoke lifecycle E2E | `PUBLICATION_LIFECYCLE_E2E_PASSED`; `PASS publication lifecycle E2E teardown` |
| Existing project publication browser | `CI_PROJECT_PUBLICATION_BROWSER_PASSED` |
| Existing publication URL browser | `PASS publication URL browser contract` |
| Existing form-state browser | `PASS 11-A menu and form-state regressions; actual browser history results recorded.` |
| Syntax and hygiene | PHP syntax checks, Node syntax checks, `git diff --check`, LF text; no raw logs committed |

The workspace checks cover all five publication states, retained slug errors, localized keyboard copy success/failure, real reserve/publish/unpublish forms with and without JavaScript, GET-read/POST-unread transitions, a missing-message 404, escaped bodies with line breaks, exact encoded mailto links, empty inbox, mobile ordering and absence of a reply form. Both languages and themes pass AA text contrast, 44px targets and no horizontal overflow at 390/768/1280/1920/2560px with default and large text.

Full final command output is provided in PR comments. Raw logs stay outside git. The lifecycle E2E ran in a separate empty database with the production-smoke wrapper's explicit provisioning flag and HTTPS public origin. Initial local harness launches lacked those settings; after correcting the runner, the unchanged E2E passed. All temporary containers, databases and networks were removed.

## CI rehearsal PASS lines

The inventory came from `ci.yml` and searches of `tests/` for both routes and their renderers. All 24 scripts in “Run isolated CLI rehearsals” ran, plus the publication browser job, lifecycle E2E, required workspace gate, and the two standalone presentation/form regression drivers.

| Workflow entry point | Key PASS line |
| --- | --- |
| `run-phase2-ownership-rehearsal.php` | `PASS ownership migration rehearsal` |
| `run-phase2-public-lifecycle-rehearsal.php` | `PASS public lifecycle rehearsal` |
| `run-phase2-tenant-authorization-rehearsal.php` | `PASS tenant authorization rehearsal` |
| `run-phase2-experience-rehearsal.php` | `PASS Experience rehearsal` |
| `run-phase2-auth0-rehearsal.php` | `PASS T-AUTH0-PKCE-CALLBACK-IDENTITY-SESSION-LIMITER` |
| `run-profile-version-tests.php` | `PASS profile preexisting data preservation and private fixture cleanup` |
| `run-profile-version-route-tests.php` | `PASS profile HTTP private logs and complete process/session/file/database fixture cleanup` |
| `run-final-profile-tests.php` | `PASS final-profile all preexisting data and private fixture cleanup` |
| `run-final-profile-route-tests.php` | `PASS final-profile HTTP private logs and complete process/session/file/database fixture cleanup` |
| `run-phase2-owner-flow-rehearsal.php` | `PASS owner flow rehearsal` |
| `run-registration-core-rehearsal.php` | `PASS registration missing foundation denies, preexisting Users/bindings and all Portfolio/content rows preserved, no session, unknown Sign In still denied` |
| `run-onboarding-attestation-tests.php` | `PASS complete existing-row preservation and fixture cleanup` |
| `run-onboarding-draft-tests.php` | `PASS draft existing Users/bindings/attestations/ownership/content preserved and private fixture cleanup` |
| `run-onboarding-draft-route-tests.php` | `PASS draft HTTP preexisting data preservation and process/session/fixture cleanup` |
| `run-onboarding-editor-tests.php` | `PASS editor exact shared country/status/language catalogs and localized labels with native controls` |
| `run-onboarding-presentation-tests.php` | `PASS onboarding steps 1–5 presentation boundaries: 572 checks.` |
| `run-registration-routing-tests.php` | `PASS routing private session/key/log/process/hostname cleanup` |
| `run-onboarding-completion-tests.php` | `PASS completion all preexisting data, private synthetic source/session files and database fixtures preserved/cleaned` |
| `run-onboarding-attestation-route-tests.php` | `PASS attestation HTTP all preexisting data and private process/session/source/file/database fixture cleanup` |
| `run-onboarding-completion-route-tests.php` | `PASS completion HTTP protected data preservation and process session source file database fixture cleanup` |
| `run-owner-journey-route-tests.php` | `PASS journey protected data and private source process session log file database fixture cleanup` |
| `run-publication-mutation-tests.php` | `PASS publication complete database seal process session private-file cleanup and protected-data snapshot` |
| `run-project-publication-tests.php` | `PASS project-publication full preexisting row ledger seal media/session/process fixture preservation and cleanup` |
| `run-contact-visibility-tests.php` | `PASS contact-types complete protected-data ledger session process and fixture cleanup` |

## Deviations from the prototype

- Publishing displays the real configured URL and all backend states, including reserved/private, offline and configuration errors. Slug reservation/update forms and the permanent-link hint remain available only before first publication. The prototype has only a demo toggle.
- The existing public “View Portfolio” action is retained alongside private Preview, and appears only for a live URL. Copy remains a progressive enhancement of the existing published-state control; without JavaScript the URL is selectable. Offline and reserved states have no misleading View action.
- The fake project counts and “example only” URL label are omitted. The remaining privacy note accurately covers evidence and sources.
- Messages show the real sender name, email and content because the backend has no subject field. Unread/read labels and the existing Mark as unread form are visible. The list includes the selected row; its state comes from the refreshed handler list.
- The unsupported reply box and all prototype demo copy are omitted. Reply by email opens an encoded `mailto:` link with a localized subject.
- Screenshot messages and URLs are explicitly synthetic fixtures, never resident data. The selected-unread screenshot demonstrates the visual state requested; opening a real message by GET marks it read, as independently verified by the authenticated gate.
- Mobile and English follow FE-01–FE-05 conventions; no separate supplied reference exists. Mobile puts the list before the message with a native Back link. Native no-JS flows keep every real form/action available and hide Copy.
- The shared shell, scalable text, AA contrast and 44px controls are retained from earlier slices. The prototype-only exploration footer is omitted. Text wraps and additional real actions change panel heights; the desktop publishing card keeps the reference's 640px width.

No skill recommendation overrode the reference. Old page-only message/publication rules were retired from `admin.css` and `owner_workspace.css`; the shared publication status rules still used by the project editor remain.

## Changed existing test assertions and fixtures

1. `OwnerFrontendExperienceStaticTest`: the Messages empty-state source assertion now reads the handler plus its extracted presentation and copy files. The same required empty-state copy is checked.
2. `PublicUrlConfigurationTest`: the wrap/44px/destructive-section assertion reads `owner_publication.css` instead of `admin.css`. The clipboard success assertion follows the localized data-attribute fallback; the same English success text, exact clipboard value, fallback/error behavior and hidden control rules remain checked. Every state assertion is unchanged.
3. `publication-ux-visual.php` / `.cjs`: the standalone fixture loads the current shared workspace and page styles, and the route fixture serves those assets from `public/assets`. View Portfolio role lookups now use `exact: true` to distinguish the retained public View link from the new Preview portfolio link. All URL, rel/target, copy, state, no-JS, overflow and touch assertions remain.
4. `owner-form-state-browser.cjs`: its synthetic server resolves versioned assets beneath `public/assets` and serves WOFF2 fonts. Tests open the native account disclosure before focusing/submitting its controls. Escape first closes that disclosure and restores focus to its summary, then closes the mobile navigation. The no-JS navigation count follows all nine current destinations (previously seven); it still requires the full set to be present. All duplicate-submit, CSRF, cancel/confirm, DOM identity and actual browser-back assertions remain.

New coverage: two native PHPUnit presentation tests (58 assertions), a guarded synthetic presentation fixture, a scoped disposable-state seed/restore fixture and the registered workspace sharing checks. No production request behavior or test baseline was changed.

## Reference next to result

The supplied references are 1440px Arabic desktop. For new/private/offline/configuration/error states, English and mobile/no-JS, the nearest desktop baseline below is a visual comparison, not a separate supplied target.

| View | Supplied reference | Result |
| --- | --- | --- |
| No link reserved · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: No link reserved · 1440 ar light"> | <img src="publication-no_slug-ar-light-1440.png" width="300" alt="Result: No link reserved · 1440 ar light"> |
| Reserved and private · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Reserved and private · 1440 ar light"> | <img src="publication-reserved-ar-light-1440.png" width="300" alt="Result: Reserved and private · 1440 ar light"> |
| Published · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Published · 1440 ar light"> | <img src="publication-published-ar-light-1440.png" width="300" alt="Result: Published · 1440 ar light"> |
| Offline after publishing · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Offline after publishing · 1440 ar light"> | <img src="publication-offline-ar-light-1440.png" width="300" alt="Result: Offline after publishing · 1440 ar light"> |
| PUBLIC_BASE_URL unavailable (503) · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: PUBLIC_BASE_URL unavailable (503) · 1440 ar light"> | <img src="publication-configuration-ar-light-1440.png" width="300" alt="Result: PUBLIC_BASE_URL unavailable (503) · 1440 ar light"> |
| Retained slug validation error · 1440 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Retained slug validation error · 1440 ar light"> | <img src="publication-error-ar-light-1440.png" width="300" alt="Result: Retained slug validation error · 1440 ar light"> |
| Published · 1440 ar dark | <img src="reference-dark-08-publication.png" width="300" alt="Reference: Published · 1440 ar dark"> | <img src="publication-published-ar-dark-1440.png" width="300" alt="Result: Published · 1440 ar dark"> |
| Publishing · 1440 en light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Publishing · 1440 en light"> | <img src="publication-published-en-light-1440.png" width="300" alt="Result: Publishing · 1440 en light"> |
| Publishing · 390 ar light | <img src="reference-light-08-publication.png" width="300" alt="Reference: Publishing · 390 ar light"> | <img src="publication-published-ar-light-390.png" width="300" alt="Result: Publishing · 390 ar light"> |
| Publishing · 390 ar dark | <img src="reference-dark-08-publication.png" width="300" alt="Reference: Publishing · 390 ar dark"> | <img src="publication-published-ar-dark-390.png" width="300" alt="Result: Publishing · 390 ar dark"> |
| Publishing · 390 ar without JavaScript | <img src="reference-light-08-publication.png" width="300" alt="Reference: Publishing · 390 ar without JavaScript"> | <img src="publication-published-no-js-ar-390.png" width="300" alt="Result: Publishing · 390 ar without JavaScript"> |
| Selected message · 1440 ar light | <img src="reference-light-09-messages.png" width="300" alt="Reference: Selected message · 1440 ar light"> | <img src="messages-selected-ar-light-1440.png" width="300" alt="Result: Selected message · 1440 ar light"> |
| Selected message · 1440 ar dark | <img src="reference-dark-09-messages.png" width="300" alt="Reference: Selected message · 1440 ar dark"> | <img src="messages-selected-ar-dark-1440.png" width="300" alt="Result: Selected message · 1440 ar dark"> |
| Selected message · 1440 en light | <img src="reference-light-09-messages.png" width="300" alt="Reference: Selected message · 1440 en light"> | <img src="messages-selected-en-light-1440.png" width="300" alt="Result: Selected message · 1440 en light"> |
| Selected message · 390 ar light | <img src="reference-light-09-messages.png" width="300" alt="Reference: Selected message · 390 ar light"> | <img src="messages-selected-ar-light-390.png" width="300" alt="Result: Selected message · 390 ar light"> |
| Selected message · 390 ar dark | <img src="reference-dark-09-messages.png" width="300" alt="Reference: Selected message · 390 ar dark"> | <img src="messages-selected-ar-dark-390.png" width="300" alt="Result: Selected message · 390 ar dark"> |
| Selected message · 390 ar without JavaScript | <img src="reference-light-09-messages.png" width="300" alt="Reference: Selected message · 390 ar without JavaScript"> | <img src="messages-selected-no-js-ar-390.png" width="300" alt="Result: Selected message · 390 ar without JavaScript"> |
