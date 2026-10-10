# Approved Landing implementation — review record

This slice replaces the generic root Landing with the approved green/gold product design. It is isolated from approved main `82353dc2d2a709858d0b56852182acce20f06009`; no merge or deployment is authorized. PR #8's documentation follow-up is a separate branch/commit, not a dependency of this feature branch.

## Source and scope

Read the owner ZIP's README.md and approval-evidence.json. All four recovered originals passed the recorded size/SHA-256 checks and remain unchanged outside Git. The exact Landing is `ather-landing-high-fidelity.html`, approved 2026-10-04 20:01:49.505 Asia/Amman; SHA-256 `1932223d7353286dfaf0883bc84b5182c2e4d0e0a00e5476397d095c74c818d1`, 86,569 bytes. The earlier young-man/falling-stars concept is excluded.

Retained product order: navigation; headline/hero and illustrative CareerFit AI profile; three principles; three steps; Evidence/problem/personal-role/result story; three value cards; privacy panel; final action; footer. Product tokens, proportions, spacing, typography and SVG icon geometry derive directly from the original. Original Arabic copy remains, except the free profile-creation CTA described below. The example is static illustrative content, not a live account or a new AI recommendation capability.

## Files and production integration

- `index.php`: public GET/HEAD Landing, existing HTTP boundary and versioned asset helper, allowlisted `lang=en` / default Arabic, document lang/dir, honest start actions and unchanged `owner_login.php` destination.
- `landing.css`: extracted product styles, natural responsive layout, original light/dark tokens, keyboard focus and reduced-motion/forced-colors rules.
- `landing.js`: existing theme preference key `ather.evidenceHub.theme`, system fallback, theme controls, progressive mobile menu, Escape and section/resize focus handling. No third-party runtime script.
- `includes/landing_copy.php`: Arabic/English catalog and escaped static output; no query value is reflected into markup.
- `includes/landing_icons.php`: 21 static SVGs extracted from the reference's exact Lucide 1.17.0 source; no icon library runtime.
- `assets/fonts/`: original Noto Sans Arabic 400/500 weights served locally; OFL license included. `assets/licenses/LUCIDE.txt` retains the icon license.
- `Dockerfile.production`: copy only the new stylesheet, script and fonts into the existing public asset tree. This is a source change; no image build, container mutation or deployment was performed.

No framework, package/dependency manifest, workflow, security-header configuration, auth handler, database, storage or public portfolio route changed. Working sign-in uses the same existing handler; no provider login was initiated in this task.

Font sources: [Google Fonts stylesheet](https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500&display=swap), [Noto license](https://github.com/google/fonts/blob/main/ofl/notosansarabic/OFL.txt). Icon source/license: [exact Lucide UMD](https://unpkg.com/lucide@1.17.0/dist/umd/lucide.js), [ISC license](https://unpkg.com/lucide@1.17.0/LICENSE). Runtime needs no access to those sites, so existing `script-src/style-src/font-src 'self'` remain sufficient.

## Deliberate differences and reference gaps

1. Removed the presentation toolbar, stage/device simulator, inspector/host integration, overflow lock, and external demo scripts. The product page still has the approved desktop 1,160px maximum width and rounded frame. Mobile uses the full viewport instead of a simulated device frame; original forced-mobile product rules now apply naturally below 721px. The 320px project heading/badge may wrap, and its h1 is 34px; these adaptations await visual review.
2. The prototype's desktop navigation buttons were inert. They are now real section links. The mobile menu also exposes language/theme controls; Escape restores focus, section links move focus to content, and desktop resize clears the menu safely. Without JavaScript, mobile navigation remains visible; the language switch and sign-in links still work.
3. The removed theme toolbar is replaced with a product theme button. This adds a control to navigation, so exact header spacing must be compared. Theme uses the existing preference key and responds to system preference when no choice is saved.
4. The original “ابدأ بناء ملفك مجانًا” CTA becomes “اكتشف كيف تبني أثرك” and leads to the existing explanatory steps. Start links lead to the final availability notice; the final button signs into an existing account. A bilingual notice states public registration is unavailable. No registration, Auth0 customization, Onboarding or Dashboard redesign was implemented.
5. Small light-theme gold labels use `#8a590c` for readable contrast. Dark-theme labels/icons on the original pale-green story/principles panels use `#69501a`; the prototype's pale gold was low contrast there. The other original color tokens and large headline gradient remain. Focus outlines and reduced-motion hover handling were added.
6. The reference contains Arabic RTL copy and an inert EN control, with no approved English copy or LTR rendering. English translates the same structure and uses `lang=en dir=ltr`; it does not introduce sections or capabilities. Its wording, wrapping and visual acceptance require owner review. Footer Privacy/Terms/Security/Contact remain informational labels as supplied; no approved policy/contact destinations were recovered, so no routes or legal text were invented.

## Verification and limits

- PHP syntax passed for the Landing and both new helpers; JavaScript syntax and `git diff --check` passed.
- 93 focused checks passed: actual localhost GET/HEAD and 405 method behavior; locale fallback including array/malformed values; unique IDs and local anchor destinations; one h1/main; existing sign-in path; availability notice; bilingual output and CareerFit bidi isolation; no inline/external production resources; font/CSS/JS responses; production asset-copy mapping; exact originals; and unchanged protected auth/public-route/CSP/Caddy source.
- 40 Node checks exercised theme persistence/storage-denial/system fallback, synchronized pressed state, menu open/close, Escape/focus, section focus and resize focus. These use DOM stubs and do not prove real keyboard or screen-reader behavior.
- 19 fixed-token contrast calculations passed their applicable WCAG text thresholds (4.5:1 for normal text, 3:1 for the large headline gradient endpoint). This is not a full rendered contrast audit; gradients, compositing, focus and every responsive state need a browser.
- Native PHP Phase 2 suite with existing bundled GD/intl/mbstring/pdo_sqlite/fileinfo enabled per process: **45 cases and scoped teardown passed; two image-processing cases failed**. MediaNormalizationTest could not generate high-resolution derivatives; LibvipsImageProcessorTest reports missing libvips CLI. Both identical failures reproduced on a Git archive of the approved baseline. The initial run without those bundled extensions had eight environment-related failures; none were counted as passes. No dependency installation, container use or live database/storage rehearsal occurred.
- Static preservation checks cover current authentication handlers, Owner routes, public portfolio/media/contact/JSON sources, root public wrapper, Apache route rules/CSP and Owner HTTPS configuration. Full Apache/container and live sign-in acceptance were not rerun. They remain later gates.

Browser inventory returned no browser; starting an isolated in-app tab reported “Browser is not available: iab.” No screenshots were captured, and no fidelity or browser accessibility acceptance is claimed.

| Viewport | Light original/implementation | Dark original/implementation |
| --- | --- | --- |
| 1440px | Pending; no screenshots | Pending; no screenshots |
| 390px | Pending; no screenshots | Pending; no screenshots |
| 320px | Pending; no screenshots | Pending; no screenshots |

## Isolated preview and next action

A loopback-only PHP preview serves Landing and its asset allowlist. It deliberately blocks sign-in execution and application/internal routes, and uses a synthetic issuer in the existing CSP template. It has no tenant/database configuration and is not the live endpoint. The byte-exact original is separately available at `/reference.html`; its preview wrapper retains its own external scripts, as supplied. Local preview and evidence locations, restart instructions and commit/bundle checksums are in the durable handoff outside Git.

Next: use that original and isolated implementation side by side at all six viewport/theme combinations; capture matching full-page and section screenshots after fonts load. Check horizontal overflow, typography, all section spacing and content, menu open/closed states, Tab/Shift+Tab/Enter/Space/Escape and visible focus, contrast/forced-colors, reduced motion, and Arabic RTL/English LTR. Record remaining visual differences and secure owner visual acceptance. Resolve the two image-test runtime limitations in an appropriate isolated environment before treating the full regression gate as passed. Then make a separate publication/merge/deployment decision; none is authorized here.

## Review finalization — 2026-10-10

This entry supersedes earlier next-action statements about authorization to publish a Draft PR; historical test and browser evidence above is retained. Publication as a Draft PR is now authorized. Merge and deployment remain unauthorized.

Verified starting branch: `feat/approved-landing-20261010`; clean starting HEAD `c0016d395e0b454648c73c787c002bb5257454ef`, following implementation `d76929907cf5230d11655b420c72fcff8cc85514`, from approved main `82353dc2d2a709858d0b56852182acce20f06009`. The final publication SHA, PR and exact-head CI results are recorded in the durable finalization handoff after committing; a document cannot embed its own commit SHA.

Owner acceptance covers the appearance and button arrangement of the displayed version. The Arabic text review found no necessary correction. Retain the bilingual registration-unavailable disclosure, existing-account sign-in CTA, and automatic responsiveness. No phone/desktop preview toggle was added. The approval does not establish all viewport/theme, keyboard, Console or mobile behavior. No approved English visual reference has been established.

Clean Code review: rendering remains in `index.php`, escaped static copy in `includes/landing_copy.php`, static icons in `includes/landing_icons.php`, theme/menu behavior in `landing.js`, and presentation in `landing.css`. Existing HTTP, asset-versioning and theme-preference conventions are retained. No new framework, dependency, general abstraction or unrelated refactor was warranted. The intentional desktop/mobile navigation markup supports progressive enhancement and different control placement.

One CSS cleanup removes the ineffective `#ather-hi-fi h1 { font-size: 34px; }` rule under 350px. The more-specific existing `#ather-hi-fi .hf-page h1 { font-size: 38px; }` mobile rule already won the cascade. Thus the effective declared size remains 38px; the earlier 34px note was inaccurate. The project-card wrapping rule remains. This correction removes dead code without changing intended or accepted appearance; rendered wrapping is still browser-unverified. No copy, button order, CTA destination, rendering or interaction logic changed.

Fresh checks against the finalization working tree (durable evidence under `finalization-20261010/`):

| Check | Actual result and limit |
| --- | --- |
| Focused HTTP/source/contrast validation | 93 passed, including both locales, section targets, methods, disclosures, same-origin assets, original hashes, production asset-copy mapping and protected source preservation. Token contrast checks are not rendered contrast acceptance. |
| Additional finalization checks | 25 passed: both desktop/mobile language link destinations and HTTP locales; versioned CSS/JS and font responses byte-identical to source; font 400/500 weight and required TrueType tables; all 222 repository PHP files; JavaScript syntax for admin/portfolio/Landing; working diff whitespace. |
| Theme/menu interaction logic | 40 Node DOM-stub checks passed again, covering persistence/storage denial/system fallback, pressed state, open/close, Escape/focus, section focus and desktop resize focus. These are not browser keyboard/focus tests. |
| Phase 2 static architecture guard | Passed again. |
| Native Phase 2 regression | Reused unchanged valid evidence: 45 cases plus teardown passed; MediaNormalizationTest and LibvipsImageProcessorTest failed identically on approved main. PHP/JS/auth/database sources and test runtime inputs are unchanged by the dead CSS cleanup. These failures remain failures, not exceptions or passes. |
| Production container/Apache/security/sign-in gates | Not run locally: no local container, database, storage or authentication changes permitted. Remote CI status must be read on the exact final PR head; failed, skipped or absent checks are not passed. |

The bounded supported browser check returned `Browser is not available: edge`. No further recovery investigation was attempted. Prior native Computer Use attempts had separately failed URL detection or input geometry, as recorded outside Git. Owner approval/videos are not substitutes for browser evidence.

| Language/direction | Widths | Light | Dark |
| --- | --- | --- | --- |
| Arabic / RTL | 320, 390, 1440 | Browser-unverified at each width | Browser-unverified at each width |
| English / LTR | 320, 390, 1440 | Browser-unverified at each width | Browser-unverified at each width |

Outstanding browser checks: loaded-font rendering; text/control wrapping and horizontal overflow; actual section navigation and language/theme controls; mobile menu open/close and resize; Tab/Shift+Tab/Enter/Space/Escape; visible focus and rendered contrast; reduced motion/forced colors; Console errors; and matching Arabic reference/implementation screenshots. No new screenshots exist. English output and destinations passed HTTP checks, but exact English visual parity is not claimed. Footer policy/contact labels remain informational because approved destinations are absent.

The approved original SHA-256 remains `1932223d7353286dfaf0883bc84b5182c2e4d0e0a00e5476397d095c74c818d1`. `/reference-scrollable.html` removes only the reference host's document overflow lock in a separate preview-only copy. That derivative, router change, recovery artifacts, evidence scripts and logs remain outside the production branch. `/reference.html` remains byte-exact. Product preview remains `http://127.0.0.1:8174/` and English `/?lang=en`; `owner_login.php` intentionally returns 503 there without executing authentication. Production keeps the unchanged sign-in destination and public portfolio/security sources.

Smallest next acceptance action: complete real browser keyboard/menu/overflow and Arabic comparison evidence, starting with 1440px light before the other Arabic configurations; check English/LTR behavior separately. Review exact-head CI and its blockers before any later readiness/merge decision. This Draft PR is reviewable with disclosed gaps, not fully accepted or ready to deploy.

## Sign In slice entry-link follow-up — 2026-10-10

From approved main `d942cf143522b4a76889c9ff31cc4f2b4bdb38b4`, the separate Sign In
slice changes only Landing's three existing-account destinations to
`signin.php?lang=<current locale>`. Accepted appearance, button arrangement,
Arabic copy and registration disclosure are unchanged. The underlying
`owner_login.php` entry remains compatible. Historical checks above describe
their recorded revisions; current Sign In evidence and pending acceptance
are in [SIGNIN-IMPLEMENTATION.md](SIGNIN-IMPLEMENTATION.md).
