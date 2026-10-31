# Evidence Hub navigation alignment

Base: `c12b27ec00f3e23fc9a623a7e9f490e7ffdd6daf` (`origin/main`). Branch: `fix/evidence-hub-navigation`.

## Scope and reference reuse

Only Evidence Hub navigation changes. Its separate shell, destinations, logical order, private boundary, two layers, record navigator, cards, analysis, writing aids and forms remain.

The top bar follows the current [Portfolio header rules](../../../public/assets/css/portfolio.css): 94px desktop inner height, centered links, 24px column gaps, 12px navigation text, 18px link gaps, gold current/hover underline and visible green keyboard focus. Evidence Hub retains **Next steps → Overview → Project evidence → Technologies → Progress**, its context label, both appearance controls and language link. No public section names or “Let’s talk” action are introduced. Small screens retain all five links in a focus-scrollable second row. A measured content-target offset keeps section headings clear of the actual responsive bar; the native no-JS bar remains in flow.

The sidebar adapts the existing [Dashboard workspace rules](../../../public/assets/css/owner_workspace.css) inside Evidence Hub's own stylesheet: expanded **228px**, compact **72px**, full **ATHER** wordmark / **A•**, forest surface, 13px links, 18px icons, 44px minimum navigation rows, 6px corners, the same selected background and a **3px gold logical-side indicator**. The account row follows navigation, with the Dashboard's avatar treatment, 58px minimum row and forest account panel. Its safe existing generic owner context remains; no initials or owner data are invented.

The existing sidebar elements remain **Overview → Projects → Evidence Hub → Credentials**, along with the brand/home link, explicit back link and original account actions. Credentials is still focusable and unavailable, with no route; its enhanced tooltip now includes both its name and “Coming soon”. The original native title remains. Switch account and Log out keep their exact POST actions and sole CSRF fields.

Collapse persistence remains Evidence-specific. The existing 1100px modal-drawer boundary, background inertness, focus trap, Escape/backdrop dismissal, native no-JS navigation and reduced-motion behavior are retained rather than importing the Dashboard shell. Tooltip and compact-account positioning now choose the content side in Arabic as well as English, clamp to the viewport, and preserve focus restoration. Collapse/expand icons mirror with the workspace direction. The full wordmark and compact mark have separate display states, so neither is squeezed into the other's width.

The Dashboard and Portfolio templates, assets and navigation items are unchanged. No handler, database, route, validation, contract/schema, mapper, taxonomy, text/recommendation rule, live contract version or opaque-reference behavior changes. No library, inline script/style markup or CSP exception is added. The original checkout and running stack are untouched.

## Modified files

| File | Purpose |
|---|---|
| `includes/owner_layout.php` | Evidence-only compact mark, decorative account avatar/indicator and localized unavailable-icon description; all destinations/forms retained |
| `public/assets/css/evidence_hub.css` | Scoped header/sidebar styling, responsive rail/wordmarks/account states and navigation target clearance |
| `public/assets/js/evidence_hub.js` | Direction-aware bounded tooltip/account coordinates and measured header offset; content interactions unchanged |
| `tests/Browser/evidence-hub-navigation-checks.cjs` | New reference comparisons, unchanged destination/form assertions, Arabic/English themes, collapse persistence, RTL descriptions, keyboard/current state, short windows, no-JS and large text |
| `tests/Browser/evidence-hub-views-visual.cjs` | Invokes the new checks from the existing standalone browser matrix |
| `tests/Browser/evidence-hub-owner-visual.cjs`, `owner-sidebar-visual.cjs`, `evidence-hub-interaction-visual.cjs` | Adapt only the requested visual expectations, detailed below |
| `tests/Legacy/cases/EvidenceHubOwnerRoutePresenterTest.php` | Retain the CSS geometry rule with the requested 72px compact width |

## Verification

Synthetic fixtures and disposable databases only; no resident portfolio was used for writes or screenshots. The baseline capture server mounts the original layout/CSS/JS read-only. Arabic/English and light/dark comparisons confirm the **exact native content DOM** is unchanged, as are representative hero/card/analysis widths, heights, typography, colors, padding and margins. Navigation height and compact-rail width intentionally change the available viewport and content origin.

| Check | Result |
|---|---|
| PHPUnit | `OK (184 tests, 894 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | Exactly the same 22 known unmatched-ignore entries, no new diagnostics; existing exit 1 and baseline retained |
| Gate registration | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates | `REQUIRED_GATES_PASSED`, including registered Evidence Hub browser, HTTP/MySQL, real actions and security checks |
| Five standalone Evidence drivers | Page/views matrix, functional QA, final UI/CDP, interaction and editor actions: PASS |
| New navigation checks | `PASS Evidence navigation ar/light`, `ar/dark`, `en/light`, `en/dark`; 320/390/768/1100/1101/1280/1920/2560px; actual Portfolio/Dashboard style comparisons; unchanged links/order/account CSRF; compact persistence and unclipped branding; bounded RTL tooltips/account panel; keyboard section links and current state; 200% root text plus large workspace text |
| Short window / native fallback | 1440×360 compact rail focus/scroll/account access; 390×500 native Arabic/English links and account disclosure without JavaScript |
| Existing sidebar contract | `PASS OWNER-SIDEBAR-R1.1 focused visual contract`; route/privacy, unavailable Credentials, original account POST/CSRF, focus/tooltip, expanded/compact themes, drawer trap/inertness/Escape, all mobile widths, short windows and reduced motion retained |
| Contrast | `PASS AA navigation contrast` in all four locale/theme combinations, including open account actions; 18 visible labels per combination |
| All 24 isolated CI rehearsals | `CI_ISOLATED_REHEARSALS_PASSED` |
| Lifecycle / actual public and preview browser | `PUBLICATION_LIFECYCLE_E2E_PASSED`; `CI_PROJECT_PUBLICATION_BROWSER_PASSED` |
| Disposable production smoke | Two fresh installs, two upgrades and failure-safe bootstrap; `PASS environment gate Owner=PRESENT_PRESERVED Protected=NOT_PRESENT_PRESERVED resident_portfolio_course=UNCHANGED` |
| Content invariance | `PASS Arabic/English light/dark baseline versus result: exact native content DOM, card/hero/analysis typography, colors and geometry unchanged` |
| Syntax / hygiene | Edited JavaScript passes `node --check`; LF; no raw logs committed |

Full transcripts stay outside git at `C:/Users/momen/Downloads/ather-evidence-navigation-evidence/`.

## Existing assertions adapted — same rules retained

1. **`EvidenceHubOwnerRoutePresenterTest.php`:** required compact-width CSS literal `78px` becomes `72px`. The expanded width, rail direction, access, protected contracts and all other assertions remain.
2. **`evidence-hub-owner-visual.cjs`:** its compact-width wait and exact width assertion become 72px. Collapse, accessible state, theme, native fallback and responsive authority remain.
3. **`owner-sidebar-visual.cjs`:** hover and compact Credentials tooltips now require `Credentials — Coming soon`; unavailable/no-route/Enter/Space/native-title assertions remain. Header space becomes 116–120px for the full brand and retained back control, with 24px navigation clearance. Rows become 44–46px. The collapse/back non-overlap assertion now checks the header-row control above the retained back link. Compact width becomes 72px. All remaining geometry, privacy, account, keyboard, scrolling, motion and drawer assertions remain.
4. **`evidence-hub-interaction-visual.cjs`:** section links now require a transparent hover surface, normal ink and gold underline from the Portfolio pattern. Every other secondary control keeps the original soft-surface/green hover assertions. Existing focus, stable geometry, no-blue-leakage and overflow checks still cover all controls.

No contract tests or fixtures change. The new matrix additionally compares computed style values with the actual Dashboard and Portfolio stylesheets, rather than merely accepting hard-coded new values.

## Before / after

Baseline is left; result is right. All captures use synthetic content. Mobile boards also show the open drawer. Compact boards show both destination descriptions and the open account panel.

| State | Screenshot |
|---|---|
| Arabic desktop, light / dark | [Light](ar-light-1440.png) · [Dark](ar-dark-1440.png) |
| English desktop, light / dark | [Light](en-light-1440.png) · [Dark](en-dark-1440.png) |
| Arabic mobile, light / dark | [Light](ar-light-390.png) · [Dark](ar-dark-390.png) |
| English mobile, light / dark | [Light](en-light-390.png) · [Dark](en-dark-390.png) |
| Compact, tooltip and account | [Arabic](compact-account-ar.png) · [English](compact-account-en.png) |

Draft PR only. No merge or deployment.
