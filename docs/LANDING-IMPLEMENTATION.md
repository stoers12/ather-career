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
