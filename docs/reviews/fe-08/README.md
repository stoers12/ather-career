# FE-08 — Public portfolio, owner preview and case study

Base: `0f1fa275e3231bc3771abf2b985f430e39e68bd1` (B-08 / PR #93). Branch: `feat/fe-08-public-portfolio`.

## Delivered behavior and boundaries

The public portfolio and case study use the handoff's final light/dark palette, local Inter and IBM Plex fonts, and Georgia editorial headings. Public documents remain English even with an Arabic workspace cookie. Only the private preview bar follows `ownerLocale()`, with the actual published/private/unavailable state. All public and case copy comes from `includes/portfolio_copy.php`.

The supplied profile, About narrative, independent contact visibility, social actions, ordered projects, full descriptions, skills, and five-entry Experience pagination remain. Project images use the supplied scoped media callback; absent images use one decorative CSS cover. Initials derive from the real owner name; an empty profile has no invented initial. Every card receives its case-study destination and safe live/code links. Case studies preserve all technologies and description line breaks, checked external destinations, scoped images, canonical/noindex boundaries and truthful draft/private notes.

The existing native contact POST, 422 retained values/errors, success PRG, rate limits and response codes remain authoritative. Preview contact stays truthfully inactive. Skills render as plain labels without JavaScript; the existing accessible selection behavior is added only when JavaScript is available. Native navigation and all projects/experiences remain available without scripts. The obsolete public stylesheet was replaced in place; no competing CSS system or dependency was introduced.

### Single permitted handler touch

`handlers/public_contact.php`: one `project_case_url` callable option in `publicContactValidationFailure()`, using its already computed `$encodedSlug`. B-08 wired the normal public and owner-preview renders but omitted this 422 re-render. The real browser test caught the missing card links after invalid contact submission. This is exactly the presentation-option exception allowed in FE-08. No request handling, query, validation, authority, redirect or status changed.

All other handlers, eligibility (`publicProjectEligibilitySql`), public data readers, authorization, contracts/schemas v1–v10, Evidence Hub, taxonomy, rules, routes, database and portfolio visibility behavior are unchanged. No resident portfolio data or running stack was used for writes or screenshots. No raw logs are committed.

## Verification

Full raw output and coverage remain outside Git in `C:/Users/momen/Downloads/ather-fe-08-evidence/`. The PR verification comments contain the complete PASS transcript and exit/result summaries. Runs use disposable synthetic databases/owners and the application PHP image.

| Check | Execution / key output |
| --- | --- |
| PHPUnit | `vendor/bin/phpunit --do-not-cache-result` with PCOV: `OK (181 tests, 870 assertions)` |
| Copy-only helper refactor | `OK (18 tests, 143 assertions)`; existing helper values remain identical |
| Phase 2 | `scripts/run-phase2-tests.php`: `Phase 2 test foundation passed.` |
| PHPStan | Exactly the same **22** unmatched baseline-ignore entries, byte-equivalent after path normalization; no new finding or baseline edit. Existing exit **1** is the unmatched-ignore condition. |
| Line coverage | PHPUnit plus all eight self-contained CI runners, merged with phpcov: **64.35% (7174/11148 lines)** |
| Full required gates | `scripts/run-evidence-hub-required-gates.ps1`: `REQUIRED_GATES_PASSED` (complete transcript in PR) |
| Public browser matrix | `public-portfolio-visual.cjs`: English/privacy/CSP/images/safe links, all **320/390/768/1280/1440/1920/2560px**, light/dark and **200% text**; AA text contrast, 44px coarse targets, keyboard drawer/Escape, theme controls, no-JS, retained focused errors and server success: PASS |
| Existing public browsers | `professional-overview-visual`, `projects-skills-visual`, `experience-contact-visual`, `profile-contact-visual`, `footer-visual`, `publication-ux-visual`: PASS |
| All 24 CI rehearsals | Exact script list extracted from `.github/workflows/ci.yml`; includes final profile, contact visibility, owner flow, owner journey, publication mutation and project publication. `CI_ISOLATED_REHEARSALS_PASSED`; individual PASS lines are in the PR transcript. |
| Real project/public/contact browser | `run-project-publication-browser-tests.mjs`: explicit publication controls plus real public/preview card navigation, scoped case media/canonical, Arabic owner/English page, draft 404, live URL, private-data exclusion, contact 422 retention/focus and real successful POST/303: `CI_PROJECT_PUBLICATION_BROWSER_PASSED` |
| Publication lifecycle E2E | `PUBLICATION_LIFECYCLE_E2E_PASSED`; exact link, image and canonical assertions unchanged |
| Production smoke | Default two fresh runs, two upgrades and failure-safe bootstrap; `PASS environment gate Owner=PRESENT_PRESERVED Protected=NOT_PRESENT_PRESERVED resident_portfolio_course=UNCHANGED` |
| Additional route-loader scan | `rg` across CI, tests and ops identified Stage-4C restore rehearsal: PASS, `ok:true`, 7 projects / 5 skills / 4 experiences / 1 message / 6 media files restored, all **16** negative cases |
| Syntax / hygiene | Changed PHP and JS syntax, `git diff --check`, LF source files; no raw logs, added dependencies or frozen-contract changes |

### Recovery and browser fixture repairs

The extra Stage-4C rehearsal predates the owner-journey cutover and FE-01 localization. Its synthetic factory now supplies the same explicit historical-owner marker and empty synthetic cohort seal used by isolated journey factories, within its existing guarded UUID namespace. Its owner probe still requires **HTTP 200** and the actual dashboard, identified by `owner-dashboard-page` instead of the retired English heading. Application authorization remains unchanged; the initial **303 onboarding** correctly rejected the unproven old fixture.

The project-publication HTTP fixture now dispatches the real case-study and contact routes, and distinguishes unmatched regex groups from a real media id. The browser waits for actual navigation/load before asserting metadata and resets its desktop viewport after the existing phone checks. Successful contact submission creates a real message, so teardown deletes only messages for the fixture's newly created recipient IDs before deleting those portfolios. The exact protected-data snapshot assertion remains unchanged. The pinned Chromium fixture reproduced Enter not opening its file input and Space opening it; its keyboard picker check arms native interception before focus preparation, then activates the browser page and Tabs from GitHub into the file input before using Space, while retaining the native chooser, focus, outline and exact-filename assertions.

## Reference deviations and reasons

1. Actual owner/project data replaces all prototype content. Real images or one neutral CSS cover replace the invented project artwork; real initials/photo replace `MO`. Empty profiles get no fictional initial.
2. About remains, with its own real navigation target, because it exists in the product. Home and the existing conditional section links remain usable. Insights and the unavailable Resume control are removed; the second hero action is a real Contact link.
3. Metrics retain the authoritative helper's actual project, skill and distinct-category counts; no prototype `AI · Software · Data` claim is inserted.
4. All existing safe hero/contact/footer social actions and visible email/phone remain. The identity card includes the real professional title, as in the reference. Actual portraits use a reserved 4:3 crop, because the reference shows initials rather than a real portrait.
5. Project descriptions remain complete, not truncated. Cards show three technologies plus the true remainder count and include safe live/code actions alongside the case link. The existing project navigator is retained for longer collections (four desktop / two intermediate / one phone); all projects show without JS.
6. Experience retains all five types, optional descriptions and five-entry pagination. The list uses the reference's row dividers and precedes Skills/Contact instead of the old side-by-side timeline/contact panels. Existing English month precision remains; no fabricated durations.
7. Contact is real on public pages, with retained errors, success feedback and `Send message`. Preview is inactive and says so; no demo-submit wording remains.
8. The footer retains real conditional section/social links, dynamic copyright and Back to top. Case header/footer show the supplied owner name/title; its Contact action returns to the portfolio. Case pages omit the invented About field and private-evidence note. Visitor pages never include the preview bar; draft/private notes appear only in preview.
9. A per-page appearance toggle follows system appearance initially and is hidden without JS; system dark still works without scripts. Larger touch targets, visible focus and safe text reflow take precedence over prototype control density. Existing decorative SVGs are reused.
10. There is no supplied case screenshot or mobile public screenshot. Case comparisons render the unchanged reference `caseStudy()` with a synthetic identity and the final light/dark palette. Phone layouts follow the established stack/reflow conventions.

## Every adapted existing assertion

No privacy, contact visibility, eligibility, authorization or frozen contract assertion was weakened. Runtime escaping, unsafe-link omission, tenant separation, response/status and exact link/image/canonical rules remain.

| File | Adapted assertion(s), preserving the rule |
| --- | --- |
| `PortfolioPresentationStaticTest.php` — header | Brand changes from PNG to reference wordmark; actual section anchors/copy references; Contact copy from shared source; the disabled Insights assertion becomes explicit absence. Favicon packaging, header-before-main and decorative icon rules remain. |
| Same — hero | Real headline/name/title card replaces the old ban on a repeated title; real Work/Contact replaces disabled Resume. Master geometry becomes reference 1.4:1 columns and serif heading; portrait reservation becomes 4:3. Accessible wrapping CTAs/social actions remain checked. |
| Same — overview | Copy is checked in `portfolio_copy.php`; real metrics and non-duplicated location remain. Responsive metrics/About assertions target the new palette/layout tokens and divider. |
| Same — projects | Neutral CSS cover replaces compact in-body fallback SVG; safe real image/alt/loading remain. Two desktop columns and a four-card initial window replace the former three-column window. Full descriptions, all eligible cards, deterministic order, bounded control placement and wrapping technologies remain. |
| Same — technology labels | First **three** escaped labels plus true `+N` replace first four. The malicious label is placed within the displayed three so escaping is still exercised; omitted labels and missing technology rows remain checked. |
| Same — skills | Static no-JS labels plus explicit DOM-created native buttons replace always-rendered buttons. Stored order/escaping, independent Skills rendering, full-width wrapping, visible focus, pressed-state/delegated/non-persistent enhancement remain checked. `<bdi>` is included in the stored-order selector. |
| Same — links | GitHub copy now references the shared source; the decorative SVG, safe URL, rel, escaped accessible name and absent/unsafe-link omission remain. No fake destination is introduced. |
| Same — closing/nav/preview | Experience precedes responsive two-column Contact; mobile native fallback, drawer semantics/Escape/scroll lock, active state and responsive breakpoints remain. The anchor offset is 110px. Preview assertions identify the actual bar, independent of its owner locale. |
| `HeroPersonalInfoCapabilityTest.php` | The real title is present in the reference identity card; the H1 assertion includes `dir="auto"`. Precedence, summary/About separation, escaping and unsupported-claim checks remain. |
| `ExperienceCapabilityTest.php` | The implemented Experience anchor uses the copy-file call. Owner/public queries, fields, validation and deterministic order assertions are unchanged. |
| `ExperiencePresentationStaticTest.php` — source | Copy checks include its new source; navigation points at the same section. Native pagination attributes carry copy templates rather than JS literals. Accurate range interpolation, page size, boundaries and live announcement remain. |
| Same — rows | Reference row dividers replace timeline marker/connector CSS. A single entry has no connector. Organization/location assertions allow the directional `<bdi>` while preserving conditional separators and month precision. All-record no-JS fallback and control relationships remain. |
| `FooterPresentationStaticTest.php` | ATHER wordmark replaces the PNG selector; one brand, real section/social destinations, privacy and dynamic escaped copyright assertions are unchanged. |
| `FrontendPerformanceResilienceStaticTest.php` | Initial reserve selector changes `n+4` to `n+5` (four desktop cards); intermediate/touch windows remain two/one. Profile media reserves 4:3 and covers reserve the reference's 170px height instead of 16:9. Canonical, loading priority and missing-IntersectionObserver resilience rules remain. |
| `PublicContactStaticTest.php` | Contact wording/form-heading assertions follow the shared copy file and reference wording. Native POST/action, exact fields, errors/retention and no recipient-authority fields remain unchanged. |
| `PublicProjectPageTest.php` | The baseline ban on every `<script` becomes a ban on **inline** scripts, permitting the external deferred theme enhancement while keeping CSP. Content escaping, private-contact exclusion, checked external links, line breaks, draft/noindex and no-canonical assertions remain. |
| `projects-skills-visual.cjs` — covers/window | All absent-image cards must have a CSS neutral cover rather than no cover. Desktop window is four; six-project fixtures exercise one-step movement/endpoints, keyboard and resize recalculation. Two columns are checked instead of equal heights across two different rows. |
| Same — Skills width | Compares the panel with its actual containing content width excluding container padding, preserving the full-width rule after moving Skills below Experience. All skill keyboard/scoping, image/link counts, unsafe URL, overflow and no-JS assertions remain. |
| `owner-projects-checks.cjs` | Native file-input activation brings the page to the foreground, Tabs from the preceding field and uses Space, reproduced against pinned Chromium 1208. An additional assertion requires document focus; dialog interception is armed before focus preparation and the native key press lasts 80ms. It still requires an actual file chooser and checks native focus, localized visible outline, exact escaped filename, clear state and no-JS behavior. No UI or assertion is removed. |
| `experience-contact-visual.cjs` | Dividers replace timeline connectors. At desktop, equal full width and natural content heights with Experience-before-Contact replace equal-height side-by-side panels. Pagination, keyboard, scrollspy/anchors, field errors, inactive preview and native fallback remain. |
| `run-stage4c-dr-rehearsal.ps1` | Restored owner must still reach the real Dashboard with HTTP 200; checks the locale-independent dashboard marker instead of retired English text. Public HTML/media/private-original/contact and restore integrity rules are unchanged. |

`professional-overview-visual`, `profile-contact-visual`, `footer-visual`, `publication-ux-visual`, contact-visibility/final-profile integrations, lifecycle E2E and frozen contract tests require no assertion edits.

## Screenshot evidence

All results use synthetic fixtures. Left is reference; right is result. The supplied public reference includes the preview bar; visitor output deliberately omits it.

| Required state | Evidence |
| --- | --- |
| Public 1440 light | [Reference / result](images/public-light-comparison.png) |
| Public 1440 dark | [Reference / result](images/public-dark-comparison.png) |
| Arabic owner preview, English page, 1440 | [Reference / result](images/preview-ar-comparison.png) |
| Case 1440 light/dark | [Reference function / result light](images/case-light-comparison.png), [dark](images/case-dark-comparison.png) |
| Public 390 light/dark | [Light](images/public-390-light.png), [dark](images/public-390-dark.png) |
| Case 390 light/dark | [Light](images/case-390-light.png), [dark](images/case-390-dark.png) |
| No image and no links | [Case](images/case-empty-1440-light.png) |
| Real contact error and success | [422](images/contact-error.png), [303 success](images/contact-success.png) |
| Draft case in preview | [1440](images/draft-1440-light.png), [390](images/draft-390-light.png) |
| No JavaScript | [Public 390](images/public-390-no-js.png), [case 390](images/case-390-no-js.png) |
