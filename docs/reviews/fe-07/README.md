# FE-07 Phase 2 — Evidence workspace

Built from `origin/main` **ef8613557547e01cd38317eb7122aa2ef7904abd**, following the [approved comparison](COMPARISON.md), [owner decisions D1–D5](https://github.com/stoers12/ather-career/pull/90#issuecomment-6528311439), [B-07 delivery note](https://github.com/stoers12/ather-career/pull/90#issuecomment-6528767603) and [view contract](../../contracts/evidence-hub-views.md). `COMPARISON.md` is the unchanged Phase 1 document from PR #90; its Phase 1 status text is historical. Phase 2 was explicitly approved by the owner.

## Delivered scope and boundaries

- **D1:** separate Evidence shell, final reference palette and local Ather fonts, with collapse/mobile/account/language behavior retained and working appearance controls. No-JS RTL navigation remains in document flow; system-dark works without scripts.
- **D2 / B1:** canonical Read has saved text and native source forms; Write has one exact three-field form, four steps and no source actions. All values remain in the same form when changing steps. Source operations save immediately after text save. Rejected text remains marked unsaved; the first invalid step/field opens and receives focus. Starters, examples and both live hints remain guidance, never scoring.
- **D3–D5:** visible unavailable Credentials; bounded keyboard record navigator retained; neutral folder icon/title without category or fabricated initials.
- Records retain recommendations, snooze/dismiss, global immediate Undo, real quality states/reasons, counts and filters. Empty recommendations never imply 100% coverage. Insights retain coverage/confidence detail, maturity, progress/publication counts, technology map and every review/proposal action, plus the complete independent B2 skill-gap list.
- Handlers, repositories, contracts/schemas v1–v10, mappers, rules, taxonomy, text evaluation, transaction/token/Undo logic, database and the live version switch are unchanged. All contract tests are byte-identical to the base. No new dependencies. No raw logs in Git. All writes used synthetic disposable owners/databases; no resident showcase data was used.

## Verification

Full logs and coverage files stay outside Git at `C:/Users/momen/Downloads/ather-fe-07-evidence/`. The PR verification comment includes the PASS transcript. These are local executions, independent of remote CI status.

| Check | Command / execution | Key output |
| --- | --- | --- |
| PHPUnit | Application PHP image, current worktree mounted read-only; `vendor/bin/phpunit --do-not-cache-result` | `OK (178 tests, 831 assertions)` |
| Phase 2 | `scripts/run-phase2-tests.php`, also rerun inside full gates | `Phase 2 test foundation passed.` |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress --error-format=raw` | Exactly the same **22** known unmatched baseline entries; zero new findings; baseline unchanged. Exit 1 is the existing unmatched-ignore condition. |
| Required gate registration | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -RegistrationChallenge` | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -EvidenceDirectory <external directory>` | `REQUIRED_GATES_PASSED` |
| Registered Evidence browsers | All six drivers actually invoked by full gate | English/LTR shell/sidebar, real actions and B-07 save/source journey, writing aids, technology review, skill evidence, text-rule hints: PASS |
| Standalone functional driver | `node tests/Browser/evidence-hub-functional-qa.cjs` | `PASS Evidence Hub functional metrics, ordering, recommendations, layers, navigator, forms, B-07 separation, retention, dirty guard, 422 focus, and native access` |
| Standalone interaction driver | `node tests/Browser/evidence-hub-interaction-visual.cjs` | `PASS Evidence Hub reference hover/focus, both layers, every editor part, read/source controls, touch and stable geometry` |
| Standalone editor actions | `node tests/Browser/evidence-hub-editor-action-visual.cjs` | JSON `result: PASS`; Save/Cancel contrast **>=4.5:1**, keyboard order, system/saved dark, 320px and no-JS |
| Standalone final UI / CDP | `node tests/Browser/evidence-hub-final-ui-visual.cjs` against dedicated Chromium CDP | JSON `result: PASS`; carousel endpoints, filters, fragments, every menu link/keyboard path, stable IDs, Escape/outside focus, feedback and no-JS |
| Standalone page/matrix | `node tests/Browser/evidence-hub-page-visual.cjs` | `PASS FE-07 all five widths, ar/en, light/dark, large text, every layer/view/step, 44px coarse targets and full reference/state screenshots` |
| Shared regressions | Required gate includes sidebar, theme resolver, form state, workspace and FE-01–FE-06 suites | `PASS OWNER-SIDEBAR-R1.1 focused visual contract`; all workspace/profile/projects/experience/sharing PASS lines retained |
| Evidence edit HTTP | `tests/Integration/check-evidence-edit-http.php` | `PASS Evidence edit HTTP auth, tenant, CSRF, validation, atomic save, PRG, unchanged timestamp, and writing aids`; `EVIDENCE_TEXT_RULES_110_HTTP_PASSED` |
| Technology HTTP | `tests/Integration/check-evidence-technology-review-http.php` | `PASS Evidence technology review HTTP mapping, proposals, CSRF, tenant denial, and MySQL constraints` |
| Source HTTP | `tests/Integration/check-evidence-sources-http.php` | `PASS Evidence sources HTTP add, limit, remove, CSRF, tenant denial, Evidence Hub summary, and MySQL constraints`; includes native text-save → Undo → source action and persisted-text proof |
| MySQL/action matrix | Full gate timestamp, skill/disposition lifecycle and two-owner HTTP action suite | `PASS Evidence Hub disposable MySQL UTC timestamp contract`; `PASS Evidence Hub skill evidence MySQL lifecycle`; disposition constraints PASS; `HTTP_ACTION_MATRIX_EXIT=0` |
| CI coverage | PCOV image; PHPUnit plus all eight self-contained CI runners, then `phpcov merge` | `CI_COVERAGE_PASSED`; merged coverage **63.87% lines (7041/11024)** at the coverage run; artifacts outside Git |
| CI isolated rehearsals | All **24** `rehearse` entries extracted from current `ci.yml`, using a generated disposable MySQL namespace; see table below | `CI_ISOLATED_REHEARSALS_PASSED` |
| Publication lifecycle E2E | `tests/Integration/run-phase2-publication-lifecycle-e2e.php`, separate fresh disposable DB | `PUBLICATION_LIFECYCLE_E2E_PASSED` |
| CI project publication browser | Actual `tests/Browser/run-project-publication-browser-tests.mjs` fixture, not just the CLI rehearsal | `CI_PROJECT_PUBLICATION_BROWSER_PASSED` |
| Production smoke | `pwsh -NoProfile -File scripts/ops/run-production-smoke.ps1` (default two runs) | Two fresh-stack runs, two upgrade rehearsals and failure-safe bootstrap PASS; `resident_portfolio_course=UNCHANGED` |
| Arabic confirmation | Browser spot check at 390/1440, light/dark | `PASS Arabic source confirmation Cancel is localized in both themes at 390/1440; cancel preserves the record` |
| Production security | Full required gate / isolated production checks | `Production security gate passed.` |
| Syntax/hygiene/frozen files | PHP syntax, JS syntax, `git diff --check`, LF/UTF-8, base comparison | PASS; no raw logs, secret files, contract-test edits or frozen backend edits |

The browser matrix checks 390/768/1280/1920/2560px, both locales/themes and normal/large text, every layer and editor part, plus existing 320px regressions. All 11 original Evidence drivers and their six fixtures remain exercised; the two new helper modules execute from the real-action and page-visual drivers. The standalone fixture server contains no authenticated database: the final CDP driver's one canonical record-navigation request uses an explicit synthetic response, while still checking the real href, request and completed navigation. Real route authorization/read/write/source behavior is separately verified by the authenticated browser and HTTP gates.

The eight coverage runners are account-entry, onboarding-editor, onboarding-presentation, owner-logout, owner-readiness-claim, owner-readiness-session, recovery-presentation and signin. The CI route-loading inventory was rescanned in `ci.yml`, `tests/` and transitive support files; the owner journey, owner flow, lifecycle, source/edit/technology HTTP checks and production smoke obligations are included above. No remote green result substitutes for a local execution.

### CI isolated rehearsal PASS lines

| Runner (`tests/Integration/`) | Key PASS output |
| --- | --- |
| `run-phase2-ownership-rehearsal.php` | `PASS ownership migration rehearsal` |
| `run-phase2-public-lifecycle-rehearsal.php` | `PASS T-PUBLIC-MIGRATION` |
| `run-phase2-tenant-authorization-rehearsal.php` | `PASS T-AUTHZ` |
| `run-phase2-experience-rehearsal.php` | `PASS T-EXPERIENCE-MIGRATION` |
| `run-phase2-auth0-rehearsal.php` | `PASS T-AUTH0-PKCE-CALLBACK-IDENTITY-SESSION-LIMITER` |
| `run-profile-version-tests.php` | `PASS profile additive populated upgrade, interrupted/repeated recovery, exact enforced check and data preservation` |
| `run-profile-version-route-tests.php` | `PASS profile real HTTP loaded tokens, two-tab conflict, retained text/visibility/original version, explicit recovery and same-value saves` |
| `run-final-profile-tests.php` | `PASS final-profile fresh/populated upgrade, interrupted/repeated 018, partial/collation rejection and exact preservation` |
| `run-final-profile-route-tests.php` | `PASS profile real HTTP loaded tokens, two-tab conflict, retained text/visibility/original version, explicit recovery and same-value saves` |
| `run-phase2-owner-flow-rehearsal.php` | `PASS T-OWNER-ONBOARDING` |
| `run-registration-core-rehearsal.php` | `PASS registration new creation, repeat resolution, exact primary compatibility, unset metadata, ordinary Sign In boundary` |
| `run-onboarding-attestation-tests.php` | `PASS 001–014 upgrade, interrupted CREATE recovery, repeat, partial/incompatible schema, preservation` |
| `run-onboarding-draft-tests.php` | `PASS draft 001–015 upgrade, repeat, interrupted recovery, incompatible schema, exact enforced checks and preservation` |
| `run-onboarding-draft-route-tests.php` | `PASS draft HTTP strict default-off gate and unsupported methods without database access` |
| `run-onboarding-editor-tests.php` | `PASS editor shared rendering, disabled boundaries and accessible AR/EN: 70 checks` |
| `run-onboarding-presentation-tests.php` | `PASS onboarding steps 1–5 presentation boundaries: 572 checks.` |
| `run-registration-routing-tests.php` | `PASS routing default-off GET/POST starts and AR/EN signup; strict switch values` |
| `run-onboarding-completion-tests.php` | `PASS completion fresh/populated 019, repeat/interrupted/partial recovery, exact checks/FKs and protected-data preservation` |
| `run-onboarding-attestation-route-tests.php` | `PASS attestation HTTP exact default-off gate and method admission before session/database access` |
| `run-onboarding-completion-route-tests.php` | `PASS completion HTTP exact default-off gate and GET/POST method admission before session/database work` |
| `run-owner-journey-route-tests.php` | `PASS journey additive populated/partial/repeated migration and enforced singleton without automatic capture` |
| `run-publication-mutation-tests.php` | `PASS publication completed and sealed legacy owners without readiness or available policies` |
| `run-project-publication-tests.php` | `PASS project-publication fresh migrated creation defaults private for published and private Portfolios` |
| `run-contact-visibility-tests.php` | `PASS contact-types populated migration preserves exposure and exact rows; interrupted add/backfill/finalize and lost ledger never replay initialized choices; partial ambiguous incompatible schemas deny` |

## Changed test assertions and preserved rules

No assertions in contract tests v1–v10 or protected rule/domain tests changed. Presentation assertions changed only as follows; added checks are stated separately.

| File / suite | Adaptation and rule retained |
| --- | --- |
| `EvidenceHubOwnerRoutePresenterTest.php` | Reference rail width 232→228; new one-column/reference component CSS strings replace retired accent/grid selectors. Numeric bidi, dark theme, local-only assets, focus, mobile/inert/scroll lock and sidebar semantics remain required. |
| `EvidenceHubOwnerPageModelTest.php` | Renderer call includes B-07 initial part; permanent canonical action label becomes Open record. Same route and safe link rule; model computation assertions unchanged. |
| `EvidenceHubLocalizationTest.php` | Reference hero title replaces the old marketing slogan in the Arabic assertion. All translations, placeholders, fallback, owner text and RTL rules remain. |
| `EvidenceHubSkillEvidenceTest.php` | The exactly-one secondary skill link assertion is scoped to Records because B2 adds a real Insights link. Same per-card link and canonical/escaped/private-identity assertions. |
| `OwnerWorkspacePresentationTest.php` | Replace absence of confirmation-copy attributes with the actual localized Arabic Cancel label. Evidence still uses its own shell/CSS/locale and does not inherit workspace globals. |
| `owner-sidebar-visual.cjs` | Overview/Projects now assert their existing real routes; Credentials alone remains disabled/focusable/native. Rail/header/wordmark and theme constants reflect the reference. Same navigation order, labels, accessible decorative branding, focus, tooltip, drawer/account/CSRF behavior and short viewport access. |
| `evidence-hub-owner-visual.cjs` | Reference 72px toolbar and named theme controls replace old 52–58px/absent-control assertions; exact soft recommendation surface and category/title order replace the old accent/icon geometry. Same shell, skip/focus, no-overflow, local theme, modal/inert/Escape, safe error and reduced-motion checks. |
| `evidence-hub-editor-writing-visual.cjs` | Enter B-07 Write/Outcome before operating the same textarea/starter; no-JS uses Write too. Same exact starter, slot selection, no overwrite, empty/Unicode number hints and described-by rules. |
| `evidence-hub-technology-review-visual.cjs` | Open Insights after the mapping PRG before inspecting/unmapping it. Same real mutations, counts, owner-mapped tag, localized native controls and publication caveats. |
| `evidence-hub-skill-evidence-visual.cjs` | Existing React card/actions/privacy/reflow assertions retained; add the complete four-gap Insights list, identity non-disclosure and empty-gaps case independent of the single recommendation. |
| `evidence-hub-text-rules-visual.cjs` | Open each native quality disclosure only when closed (cards initially expose their states). Same three localized 1.1.0 reasons, no raw suspicious text, project count and no-JS reflow checks. |
| `evidence-hub-page-visual.cjs` | Obsolete Show more becomes actual navigator reachability of the eighth record and hidden navigation when three fit. Filters use data selectors so real counts can be in labels; enter Insights/Write before captures. Same deterministic fixtures, all-record native access and target-fragment reachability. |
| `evidence-hub-functional-qa.cjs` | Reconcile historical Show more/old field-link locations with current navigator and B-07 read/write links. Preserve exact 2917/7-of-24 metrics, project order, seven complete fields, recommendation destinations/form fields, technology/project counts, qualified progress dates, real 100% fixture, native access and safe copy. Add layer/no-request, per-part links, dirty Cancel, first-error focus and source-view separation checks. Old single-line toolbar pixels become visible, unobscured, working section anchors. |
| `evidence-hub-final-ui-visual.cjs` | Recommendation columns become reference rows, with preserved count/width/reachability; navigator controls sit below the row. Retain all endpoint/filter/touch/keyboard/menu-ID/one-open/Escape/outside-focus/fragment/Undo/native assertions. Extend keyboard checks to the new third menu link. Close CDP sessions even on an assertion failure; synthetic link-response transport replaces accidental calls to an unauthenticated app from the guarded fixture server. |
| `evidence-hub-interaction-visual.cjs` | Replace obsolete selectors, universal mint-hover and old 3px constants with a type-aware audit using exact reference surfaces and 2px visible focus. Audit both layers, all four steps, Read/Sources, native menus and touch. Preserve stable geometry, semantic destructive color, no blue leakage, focus and reflow rules. |
| `evidence-hub-editor-action-visual.cjs` | Enter Write/Outcome; walk the real Previous/Next controls between textarea and Save. Same Save→Cancel→Shift+Tab order, return to textarea, hover stability, AA measurements, system/saved dark and 320px/native actions. Surface/focus colors now match the reference; footer Cancel belongs to the canvas. |
| PHP visual fixtures | Retain deterministic project IDs/order/evaluations and metrics. Supply explicit locales and complete B-07 model shapes; final fixture uses the unchanged live mapper plus current SQLite test schema. Project fixture renders the actual mode-aware presenter with synthetic sources, error/empty/10-source cases. Text-rule fixture evaluator inputs remain unchanged. |
| `check-evidence-edit-http.php` | GETs that discover writing controls explicitly enter `?mode=write`; canonical/read authorization and all request fields, retention, denials, timestamps, redirects and rules remain unchanged. |
| `check-evidence-sources-http.php` | Existing native source requests and negative/constraint cases unchanged. Add real save-text→hub/Undo→read/source sequence and database proof that source mutation retains saved text. |
| New `EvidenceHubViewsPresentationTest.php` | Four tests enforce no editable text in Read, exact one text form/no source actions in Write, retained 422 values/part and the ten-source limit. |
| New `evidence-hub-views-browser.cjs` | Invoked by registered real-actions gate: save from step 2 retains other fields, saved text survives real source add/remove, tabs make no request/retain immediate Undo, 422 retains text/focus and cannot expose source forms. Added explicit empty-recommendations/incomplete-coverage truthfulness assertions in its caller. |
| New `evidence-hub-views-visual.cjs` | Invoked by standalone page driver: every width/locale/theme/large-text/layer/view/step, native 44px coarse targets, desktop paper geometry, all reference/result and required extra-state captures. |

## Differences from the prototype, with reasons

1. **Two layers and retained chrome:** Records/Insights, existing section anchors, locale and appearance controls add navigation absent from the prototype. This preserves analysis, deep links and established workspace behavior. Mobile retains these real utility controls instead of a decorative wordmark row; the Ather brand remains in the drawer.
2. **Truthful records:** three labelled server-assessed states/reasons replace prototype “written” segments/ticks. Cards are taller and use a 3/2/1 bounded navigator rather than the reference static two-column grid (D4). Source counts remain separate from the three-field quality denominator.
3. **Recommendations:** up to three real next-step rows, native snooze/dismiss menus, zero-state onboarding and factual empty copy replace the prototype's single suggested step/completion message. No invented readiness or 100% ring.
4. **Project identity/account:** neutral folder/title, no unsupplied category/monogram (D5); generic Portfolio owner rather than the prototype's Sami Ahmad/avatar. Captures use synthetic project titles/data and actual fixture counts, never showcase content or fabricated prototype facts.
5. **Writing density:** current guidance, rule reasons, examples, starters and live hints remain visible. The paper is consequently taller than the sparse prototype; its questions/palette/step layout follow the reference. Quality status is explicitly the last server assessment, and editing never generates completion ticks.
6. **Sources after save:** Write step 4 explains and submits text save; source list/add/remove are in canonical Read/Sources, using the reference source styling and unchanged B-06 forms. No in-memory Add to draft, combined form, automatic save, silent replay or source Undo claim. Cancel returns to Read under B-07.
7. **Native access:** without JavaScript both layers/all records/all text steps are stacked, filters and JS-only controls are hidden, native disclosures/forms remain. This intentionally differs from the enhanced reference tab/step view. System-dark is available without scripts.
8. **Preserved original capability:** Credentials remains visibly unavailable (D3); Overview/Projects use real existing routes. The legacy shell's account/CSRF/mobile/collapse behavior and progress/confidence limitations remain. Insights has no dedicated supplied reference; it uses the exact same palette, typography and surfaces.
9. **Server truth/privacy:** real saved/Undo feedback, unavailable counts, confidence/sample detail and the exact owner disclosure replace prototype success/demo/verification implications. B2 has only empty/populated gaps; a database failure still makes the whole hub unavailable (503), per the merged contract.

## Reference next to result

All source references below are byte-identical copies of the named handoff screenshots. Open an image for its full resolution; result captures include the entire page.

| Screen | Reference | Result |
| --- | --- | --- |
| desktop-ar-light-10-evidence | <img src="screenshots/ref-desktop-ar-light-10-evidence.png" width="420" alt="Reference desktop-ar-light-10-evidence"> | <img src="screenshots/result-desktop-ar-light-10-evidence.png" width="420" alt="Result desktop-ar-light-10-evidence"> |
| desktop-ar-light-11-evidence-read | <img src="screenshots/ref-desktop-ar-light-11-evidence-read.png" width="420" alt="Reference desktop-ar-light-11-evidence-read"> | <img src="screenshots/result-desktop-ar-light-11-evidence-read.png" width="420" alt="Result desktop-ar-light-11-evidence-read"> |
| desktop-ar-light-12-evidence-write | <img src="screenshots/ref-desktop-ar-light-12-evidence-write.png" width="420" alt="Reference desktop-ar-light-12-evidence-write"> | <img src="screenshots/result-desktop-ar-light-12-evidence-write.png" width="420" alt="Result desktop-ar-light-12-evidence-write"> |
| desktop-en-light-02-evidence | <img src="screenshots/ref-desktop-en-light-02-evidence.png" width="420" alt="Reference desktop-en-light-02-evidence"> | <img src="screenshots/result-desktop-en-light-02-evidence.png" width="420" alt="Result desktop-en-light-02-evidence"> |
| mobile-ar-light-04-evidence | <img src="screenshots/ref-mobile-ar-light-04-evidence.png" width="420" alt="Reference mobile-ar-light-04-evidence"> | <img src="screenshots/result-mobile-ar-light-04-evidence.png" width="420" alt="Result mobile-ar-light-04-evidence"> |
| mobile-ar-light-05-evidence-write | <img src="screenshots/ref-mobile-ar-light-05-evidence-write.png" width="420" alt="Reference mobile-ar-light-05-evidence-write"> | <img src="screenshots/result-mobile-ar-light-05-evidence-write.png" width="420" alt="Result mobile-ar-light-05-evidence-write"> |
| desktop-ar-dark-10-evidence | <img src="screenshots/ref-desktop-ar-dark-10-evidence.png" width="420" alt="Reference desktop-ar-dark-10-evidence"> | <img src="screenshots/result-desktop-ar-dark-10-evidence.png" width="420" alt="Result desktop-ar-dark-10-evidence"> |
| desktop-ar-dark-11-evidence-read | <img src="screenshots/ref-desktop-ar-dark-11-evidence-read.png" width="420" alt="Reference desktop-ar-dark-11-evidence-read"> | <img src="screenshots/result-desktop-ar-dark-11-evidence-read.png" width="420" alt="Result desktop-ar-dark-11-evidence-read"> |
| desktop-ar-dark-12-evidence-write | <img src="screenshots/ref-desktop-ar-dark-12-evidence-write.png" width="420" alt="Reference desktop-ar-dark-12-evidence-write"> | <img src="screenshots/result-desktop-ar-dark-12-evidence-write.png" width="420" alt="Result desktop-ar-dark-12-evidence-write"> |
| desktop-en-dark-02-evidence | <img src="screenshots/ref-desktop-en-dark-02-evidence.png" width="420" alt="Reference desktop-en-dark-02-evidence"> | <img src="screenshots/result-desktop-en-dark-02-evidence.png" width="420" alt="Result desktop-en-dark-02-evidence"> |
| mobile-ar-dark-04-evidence | <img src="screenshots/ref-mobile-ar-dark-04-evidence.png" width="420" alt="Reference mobile-ar-dark-04-evidence"> | <img src="screenshots/result-mobile-ar-dark-04-evidence.png" width="420" alt="Result mobile-ar-dark-04-evidence"> |
| mobile-ar-dark-05-evidence-write | <img src="screenshots/ref-mobile-ar-dark-05-evidence-write.png" width="420" alt="Reference mobile-ar-dark-05-evidence-write"> | <img src="screenshots/result-mobile-ar-dark-05-evidence-write.png" width="420" alt="Result mobile-ar-dark-05-evidence-write"> |

## Extra states

Each row supplies **1440 Arabic light/dark, 1440 English and 390 Arabic**. No-JS renders both layers natively; Sources and a validation rejection are supplied in addition to the six required extra states.

| State | 1440 ar light | 1440 ar dark | 1440 en light | 390 ar |
| --- | --- | --- | --- | --- |
| insights | [Open](screenshots/insights-ar-light.png) | [Open](screenshots/insights-ar-dark.png) | [Open](screenshots/insights-en-light.png) | [Open](screenshots/insights-mobile-ar.png) |
| empty | [Open](screenshots/empty-ar-light.png) | [Open](screenshots/empty-ar-dark.png) | [Open](screenshots/empty-en-light.png) | [Open](screenshots/empty-mobile-ar.png) |
| attention | [Open](screenshots/attention-ar-light.png) | [Open](screenshots/attention-ar-dark.png) | [Open](screenshots/attention-en-light.png) | [Open](screenshots/attention-mobile-ar.png) |
| technology-review | [Open](screenshots/technology-review-ar-light.png) | [Open](screenshots/technology-review-ar-dark.png) | [Open](screenshots/technology-review-en-light.png) | [Open](screenshots/technology-review-mobile-ar.png) |
| skills-without-evidence | [Open](screenshots/skills-without-evidence-ar-light.png) | [Open](screenshots/skills-without-evidence-ar-dark.png) | [Open](screenshots/skills-without-evidence-en-light.png) | [Open](screenshots/skills-without-evidence-mobile-ar.png) |
| no-js | [Open](screenshots/no-js-ar-light.png) | [Open](screenshots/no-js-ar-dark.png) | [Open](screenshots/no-js-en-light.png) | [Open](screenshots/no-js-mobile-ar.png) |
| sources | [Open](screenshots/sources-ar-light.png) | [Open](screenshots/sources-ar-dark.png) | [Open](screenshots/sources-en-light.png) | [Open](screenshots/sources-mobile-ar.png) |
| validation | [Open](screenshots/validation-ar-light.png) | [Open](screenshots/validation-ar-dark.png) | [Open](screenshots/validation-en-light.png) | [Open](screenshots/validation-mobile-ar.png) |

No merge or deployment was performed. The draft is for Claude review and owner merge.
