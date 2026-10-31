# Public portfolio: stable scrolling and portrait framing

Base: `49a5e22e0817f2af1b7b5190c4e6ce26e3085ba2` (`origin/main`, PR #95). Branch: `fix/public-scroll-portrait`.

## Findings and changes

The existing header is sticky. There is no scroll-direction hide/show implementation in either the baseline or this change. Ordinary scrolling after resources settle did not change document height or the Contact/Footer document coordinates. Keeping the existing sticky behavior avoids adding a new hide/show state, threshold, animation or occupied-space change.

The reproducible jump occurs with **native scrollbars** when the mobile drawer locks body scrolling. Removing the scrollbar widens the available content by 15px, changes wrapping and document height, and moves the bottom scroll boundary. Scroll anchoring/clamping then changes the scroll position. Playwright normally hides scrollbars, concealing this defect; the public browser driver now leaves them enabled.

At 390×720, baseline page height changes **4,044 → 4,055 → 4,044px**, and scroll position changes **3,324 → 3,335 → 3,324px** during open/close at the footer. At 768px, the height change is 5px. With `scrollbar-gutter: stable`, the final 390px fixture holds page height at **4,187px**, scroll position at **3,467px**, and Contact's document position at **3,090.6875px** throughout the same menu cycle. The taller resting document comes from the intentional portrait frame change, not scrolling or loading.

The old 110px anchor margin does not follow a wrapping or enlarged header. `ResizeObserver` now measures the header plus a 16px clearance and updates a variable on the **existing external stylesheet through CSSOM**. Content sections, the skip target and focusable main content use that margin. Header and fixed drawer controls are deliberately excluded: applying the offset to the whole scrolling viewport made native focus scroll the page, which the new menu-position assertions caught during development. No inline style or CSP exception is used. Reduced-motion scrolling remains native and immediate. Without JavaScript, the complete wrapping navigation stays in normal flow, so an unmeasured sticky header cannot obscure a heading.

The original portrait was inspected read-only: **1,280×1,920**, with its existing **640×960** presentation derivative preserving the same 2:3 proportions. Its face and shoulders extend below the old wide, top-aligned 4:3 crop. The frame is now **4:5**, capped at **360px** wide, with `object-fit: cover` and `object-position: 50% 20%`. This preserves the image's proportions, headroom, face and shoulders, while keeping the existing green card, frame, typography and initials fallback. Absolute positioning inside the reserved aspect-ratio frame prevents late image loading from changing layout. The existing escaped name-based portrait alt text and eager/high-priority loading remain intact.

Only two production files change:

- `public/assets/css/portfolio.css`: stable gutter, content target margins, native no-JS header fallback and portrait frame/crop.
- `public/assets/js/portfolio.js`: header measurement and responsive offset updates.

No handlers, routes, data, original/derived media files, media URLs, links, visibility/privacy rules, contact behavior, security policy or dependencies change. Both public and owner-preview pages use these shared assets. The running stack and resident portfolio were not modified or used for test writes/screenshots.

## Verification

All browser screenshots and recordings use a synthetic account and an explicitly synthetic framing diagram. Its source ratio and head/shoulder positions reproduce the inspected portrait's framing requirements without publishing owner imagery. The original recording mentioned in the request was not attached or available; the exact recorded flicker cannot be matched. The scrollbar/menu jump and native heading overlap above were reproduced directly from the baseline assets.

| Check | Result |
|---|---|
| PHPUnit | `OK (182 tests, 878 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | Exactly the same 22 known unmatched-ignore baseline entries; no new diagnostics. Existing exit 1 retained; baseline unchanged. |
| Full required gates | `REQUIRED_GATES_PASSED` |
| All 24 isolated CI rehearsals, parsed from `ci.yml` | `CI_ISOLATED_REHEARSALS_PASSED` |
| Publication lifecycle E2E | `PUBLICATION_LIFECYCLE_E2E_PASSED` |
| Actual public/preview/contact/publication browser checks | `CI_PROJECT_PUBLICATION_BROWSER_PASSED`; repeated after final CSS correction |
| New scrolling/portrait browser coverage | Public + Arabic-owner preview, light/dark, 390/768/1280/1920px, 360/900px heights, bottom micro-reversals, page height and Contact/Footer coordinates, keyboard anchors and focus, drawer/Escape scroll preservation, portrait headroom/shoulders, delayed image loading, 200% text and no horizontal overflow |
| Native no-JS | Public/preview and both themes, all four widths at 360px height, 200% text and native section navigation |
| Normal motion recordings | `PASS scroll/portrait: 390px normal-motion keyboard/wheel/scroll-lock recording; every frame stable` and the same at `1280px` |
| Reduced motion | Computed scroll behavior is `auto`; all navigation, focus and content checks pass |
| Existing public browser suites | `public-portfolio`, `professional-overview`, `projects-skills`, `experience-contact`, `profile-contact`, `footer`, `publication-ux`: PASS, including existing AA contrast, keyboard, coarse-pointer 44px, privacy and native fallback checks |
| Strict CSP check | Header measurement follows four responsive widths with self-only script/style/font/image policy, zero policy violations and zero inline script/style markup |
| Production smoke, disposable stacks only | Two fresh installs, two upgrade rehearsals and failure-safe bootstrap; `PASS environment gate Owner=PRESENT_PRESERVED Protected=NOT_PRESENT_PRESERVED resident_portfolio_course=UNCHANGED` |
| Syntax and repository hygiene | Edited JavaScript passes `node --check`; LF text files; no raw logs committed |

Full transcripts remain outside git in `C:/Users/momen/Downloads/ather-scroll-portrait-evidence/`. No credentials or resident owner data are included in the review evidence.

## Existing assertions adapted

Exactly three static assertions change; none is removed:

1. `FrontendPerformanceResilienceStaticTest.php`: the reserved portrait ratio literal changes from 4:3 to 4:5, while retaining the same pre-load space-reservation rule and the unchanged project image reservation.
2. `PortfolioPresentationStaticTest.php`, portrait assertion: the same image/fallback, reserved space and proportional image-fit rule now identifies the 4:5 frame and its absolute-positioned, 20% crop selector.
3. `PortfolioPresentationStaticTest.php`, navigation assertion: the fixed 110px margin literal becomes the variable content margin plus its `ResizeObserver` update. The existing native navigation and enhanced drawer requirements remain.

All other existing assertions, including the contact suite's Experience scrollspy and heading clearance assertions, are unchanged. `public-portfolio-visual.cjs` now includes `portfolio-scroll-portrait-checks.cjs`, retains native scrollbars, and serves the new synthetic portrait variants from the existing PHP fixture. The new checks exercise geometry and real browser navigation rather than reproducing implementation calculations alone.

## Before/after screenshots and recordings

Left is the base commit's assets; right is the result, using identical synthetic content and theme. Baseline/result media URLs are unique to prevent screenshot comparison cache reuse.

| State | Comparison |
|---|---|
| Public 1280, light | [Before / after](hero-1280-light.png) |
| Public 1280, dark | [Before / after](hero-1280-dark.png) |
| Public 390, light | [Before / after](hero-390-light.png) |
| Public 390, dark | [Before / after](hero-390-dark.png) |
| Owner preview 1280, Arabic bar and English page | [Before / after](preview-1280.png) |
| Native no-JS 390, 200% text, Contact heading | [Before / after](native-anchor-390.png) |
| Footer/menu scroll cycle, 390, measurements in captions | [Before / after](footer-scroll-390.png) |

Short normal-motion recordings include section navigation, downward/upward native wheel scrolling, bottom micro-reversals and returning home; the mobile recording also exercises drawer open/Escape at the footer:

- [390px recording](stable-scroll-390.webm)
- [1280px recording](stable-scroll-1280.webm)

No merge or deployment is part of this change.
