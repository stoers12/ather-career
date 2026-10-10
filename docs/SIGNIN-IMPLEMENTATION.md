# Approved Sign In implementation — 2026-10-10

## Baseline and scope

Branch: `feat/approved-signin-20261010`. Selected baseline: `d942cf143522b4a76889c9ff31cc4f2b4bdb38b4`, the merged Landing PR #9 revision. Remote main matched this revision when the worktree was created and again before publication. The separate Draft documentation PR #8 was not merged or used as implemented behavior. Canonical E: files and their unrelated edits were preserved.

This slice implements only the approved `data-screen="signin"` presentation and shared story panel. Credentials remain in Auth0. No registration, remembered-account, account-switch presentation, recovery, verification, MFA, Onboarding or Dashboard UI is added. No dependency, authentication configuration, identity-linking or protected-data change is made. The owner approved Sign In appearance and element arrangement. Subsequent local Edge, focus-fix and Google A evidence is recorded in the durable handoff; this does not establish complete acceptance. The final full SHA and Draft PR/CI disposition are recorded in the durable handoff and PR description, avoiding a self-referential commit hash here.

## Reference provenance

Authority: recovered original `ather-auth-entry-flow.html` from the owner's `Ather_Approved_Designs_2026-10-04.zip`, together with its README and `approval-evidence.json`.

- ZIP SHA256: `7f673cf778bd6b356deadb66401f884b42ed3ba7a9e466cfbaf46b9629fba88c`.
- Original: 77,692 bytes; SHA256 `e98a321e7c46bd2ae40fe86743c4e789964ee6cc5e52b384093a424bf2ed2ea0`.
- Approval: `2026-10-04T20:10:08.400000+03:00` (Asia/Amman).
- Design message: `66a47f25-c7ac-5ab0-b7ee-da668645207b`; approval message: `d20f16bf-d2c9-4932-a97c-aa48741cdc6b`.
- [Owner-provided source conversation](https://chatgpt.com/share/6ac618c1-3b58-83ed-8874-fe9e8c34e744).

All four original archive entries were checked against the approval evidence. The Sign In original and evidence are preserved outside Git in `C:/Users/momen/Documents/Codex/ather-signin-review-20261010/references/`. They are unchanged. The separate preview derivative selects Sign In initially and removes the prototype's global document overflow lock; it does not replace the original. Its remaining prototype controls belong only to the reference preview.

## Presentation and necessary differences

The original Sign In/shared-story HTML and CSS were inspected alongside current PHP, routes, local fonts, icons and theme behavior. Product grid, 1050px maximum width, green/gold tokens, 690px desktop layout, 430px card, story text, icon shapes, provider order and email-button placement are derived from that reference. The owner subsequently approved temporarily disabling email/password with the existing native disabled-provider presentation, preserving its label and position. Local Noto Sans Arabic 400/500 and licensed static Lucide SVGs reuse existing assets; three missing SVGs were checked against the exact Lucide 1.17.0 UMD version used by the original. Existing `landing.js` supplies the shared saved/system theme behavior without changes; it safely returns when Landing menu elements are absent.

The following are deliberate implementation differences, not automatic visual approvals:

| Difference | Reason and acceptance status |
| --- | --- |
| Prototype toolbar, inspector, simulated screens and device toggle omitted | Only product Sign In UI is in scope. No user-facing phone/desktop toggle. |
| Automatic <=720px layout uses the original simulated-mobile card padding/radius and 700px minimum auth-panel height | Reference natural media rules differ (680px/padding inherited). Device simulation is removed, so mobile rules are applied automatically. Recorded Edge emulation covers desktop, 320px and 390px; physical-device review remains deferred to the pre-public-launch acceptance gate. |
| Theme control added beside the language link | Provides the original supported themes without a presentation toolbar, using the current shared preference and 44px control. |
| Microsoft button disabled with a visible explanation | No Microsoft connection was published for the inspected configured application. No guessed identifier or wrong-provider fallback. |
| Subtitle says to choose the method used for the existing account | Removes the prototype promise of future account linking. Identity binding is unchanged. |
| Email/password button temporarily disabled with a visible explanation | No inspected existing password identity binding matched the configured issuer. Native disabled semantics and the existing provider pattern are reused; the server method allowlist and OIDC routing remain unchanged. |
| Account creation area uses the owner-approved concise registration disclosure | Public registration remains unavailable. Existing-account login only; no signup route or registration-function change. |
| Ordinary sign-in link inside the Auth0 safety note | Keeps the established no-method login path directly available. |
| Dark story kicker/icons use `#69501a` on the reference light-green story background | Pale reference gold has poor contrast there; the fixed pair calculates 6.23:1. This is a disclosed accessibility adaptation pending visual review. |
| Native links, skip link, visible focus and reduced-motion rules | Accessible navigation to real destinations; recorded Edge keyboard/focus checks passed after the separate ordinary-login outline fix. Comprehensive accessibility/contrast and reduced-motion review remain deferred to the pre-public-launch acceptance gate. |
| English translation and LTR | Functional translation for owner review. No approved English visual reference is established. |

The exact Arabic story copy and safety explanation are retained. There is no password field in Ather. The owner approved the new email-unavailable notice and concise registration disclosure in Arabic and English. Other wording/translation review remains open. No complete visual parity or acceptance is claimed.

## Implemented routes and authentication boundary

- `/signin.php`: public `GET`/`HEAD`, default Arabic RTL; exact scalar `lang=en` selects English LTR, other/structured values safely fall back to Arabic. Unsupported methods return shared `405`/Allow behavior. Static copy is escaped; user query text is not reflected.
- Landing's three existing-account links now reach `signin.php?lang=<current locale>`. Accepted Landing copy/design and other navigation remain unchanged.
- `/owner_login.php`: existing GET-only OIDC entry remains compatible. No `method` keeps ordinary Universal Login without a forced connection/prompt.
- Optional `method=google` maps only to `google-oauth2`; `method=email` maps only to `Username-Password-Authentication`. Unknown, empty or structured methods return `400` before rate-limit consumption, discovery and authorization-transaction creation. Arbitrary connection, redirect and prompt query values are not forwarded.
- `beginAuth0Authorization()` adds an optional fourth argument; authorization logic is reused. State, nonce, S256 PKCE, fixed callback/scope, transaction lifetime and single-use behavior remain intact. Existing three-argument switch/retry calls retain `prompt=login` without forcing a connection.
- Callback, session, CSRF, rate-limit, exact issuer/subject identity binding, protected routes, public portfolio contracts and security headers are unchanged.

Read-only evidence from the currently configured application's public client metadata returned HTTP 200 and published the `google-oauth2` and `auth0` strategies with the two exact connection names above. Microsoft was absent. Safe evidence is retained outside Git as `connection-evidence.json`; no secrets, raw client payload, issuer/client ID or authorization URLs are retained. This is configuration evidence, not a Management API audit or successful real-provider login.

Auth0 documents the authorization request's `connection` parameter for selecting an enabled connection: [Universal Login](https://auth0.com/docs/authenticate/login/auth0-universal-login) and [Authorization Code Flow login](https://auth0.com/docs/get-started/authentication-and-authorization-flow/authorization-code-flow/add-login-auth-code-flow). The values here are server-owned and allowlisted, not browser-supplied identifiers.

The authorized temporary experiment recorded Google A reaching its expected four-project portfolio, corroborated by local existing-account/session/ownership checks; exact-file rollback completed. Email testing was blocked by issuer mismatch, with no email success claimed. Its Sign In button is now temporarily disabled, without changing server routing or bindings. Connection configuration, account eligibility and end-to-end acceptance are separate findings. Microsoft remains unavailable unless a separately approved configuration and verified routing change is made. A disabled-auth preview cannot prove real-provider login or callback success.

## Changed files and separation

| Files | Responsibility |
| --- | --- |
| `signin.php`, `signin.css`, `public/signin.php` | Product rendering/styles and production entry shim. |
| `includes/signin_copy.php`, `includes/signin_icons.php` | Escaped bilingual copy and static/reused icon geometry. |
| `includes/auth0_signin_methods.php` | Pure server-controlled connection allowlist. |
| `includes/auth0_oidc.php`, `owner_login.php` | Small method-selection extension into the existing authorization path. |
| `index.php` | Three existing-account destinations only. |
| `Dockerfile.production` | Copy `signin.css` to the production public root; existing public-tree/font/JS copy already covers the other assets. |
| `scripts/run-signin-tests.php`, `.github/workflows/ci.yml` | Synthetic contract checks and production HTTP smoke assertions; no gates weakened or bypassed. |
| This record, `HTTP_CONTRACTS.md`, `LANDING-IMPLEMENTATION.md` | Current route/entry-link behavior, provenance, differences and honest verification limits. |

Clean Code review kept rendering, escaped copy, icon definitions and authentication responsibilities separate. Theme code and authorization generation are reused rather than duplicated. No new framework, dependency, speculative abstraction or unrelated refactor was introduced. Final review corrected test-wiring whitespace/encoding and the new SVG geometry before publication; accepted Landing appearance was not altered.

## Initial-publication verification history

The table and browser limitation below describe initial publication, before the subsequent owner approval, provider experiment, Edge review and outline fix. Dated evidence in the durable handoff supersedes those historical pending statements. The bounded email/copy correction updates only `signin.php`, `includes/signin_copy.php`, one primary-button width declaration in `signin.css`, UI contract assertions and directly affected documentation; authentication/security assertions remain intact. Its focused browser/test and exact-head CI results are recorded in the handoff and Draft PR description.

| Check actually performed | Result |
| --- | --- |
| PHP syntax | All 228 PHP files passed; JS syntax for existing Landing/admin/portfolio scripts also passed. |
| Sign In synthetic contract suite | 54 checks and scoped teardown passed: allowlist rejection, ordinary/method URL selection, prompt compatibility, fixed redirect/scope, state/nonce/PKCE, session retention, single-use/replay, bilingual rendering and safe fallback. No real network/DB/provider login. |
| Local HTTP/source/syntax validation | 306 checks total, including the 228 PHP lint checks. GET/HEAD/405, language/direction, query fallback, asset bytes, labels/IDs, method links, disabled Microsoft, honest registration, preview 503/400/404 boundary, original hash, production copy mapping and protected-source preservation passed. This is not 306 browser tests. |
| Theme event logic | 12 Node DOM-stub checks passed: saved/system preference, storage denial, hidden/enabled control and pressed state. No browser/keyboard acceptance. |
| Workflow validation | YAML parsed and all six embedded PHP programs passed syntax checks. |
| Fixed-token contrast | 10 foreground/background pairs exceed 4.5:1. Not a rendered/composited/full-focus audit. |
| Phase 2 static architecture | Passed. |
| Native PHP Phase 2 regression | 45 cases plus scoped teardown passed; two image cases failed: high-resolution derivatives not generated and libvips CLI absent. Existing baseline reproduction is reused because image code/runtime are unchanged. These failures are not passes; no image dependency/container fix was attempted. |
| Local production container smoke | Not run: local container creation/recreation is outside this task. Exact-head remote CI disposition is recorded in the Draft PR and handoff. |
| Browser capability | One bounded supported Edge-tab creation failed: `Browser is not available: edge`. No repeated recovery attempt. |
| Real-provider login | Not attempted. Preview deliberately disables authentication; metadata/synthetic checks do not prove provider or callback success. |

At initial publication, browser-dependent checks were **unperformed** at 320px, 390px and desktop in both themes and both languages: matched-reference screenshots, loaded-font fidelity, scrolling/horizontal overflow, responsive text/control wrapping, keyboard/focus appearance, controls under an actual DOM, Console errors and rendered/reduced-motion/contrast behavior. No screenshots were captured; source inspection is not substituted for browser evidence. Exact English parity is not claimed.

Durable evidence and preview scripts: `C:/Users/momen/Documents/Codex/ather-signin-review-20261010/`. `HANDOFF.md` contains final SHA, PR/check links, copied test logs, evidence checksums and restart instructions. Previous security-scan exceptions do not apply to this PR; failed/absent/skipped checks must remain labeled as such.

## Isolated owner preview

- Arabic: `http://127.0.0.1:8175/signin.php`
- English: `http://127.0.0.1:8175/signin.php?lang=en`
- Scrollable Sign In reference derivative: `http://127.0.0.1:8175/reference-signin.html`
- Byte-exact original: `http://127.0.0.1:8175/reference.html` (retains original overflow lock/prototype behavior).
- Landing entry navigation: `http://127.0.0.1:8175/` and `/?lang=en`.

This loopback-only allowlisted preview has no tenant/database configuration. All valid login entries stop at an explicit `503` explanation; invalid methods return `400`, other internal/protected routes return `404`. It does not replace the live endpoint. Preview router/reference derivative/scripts are outside Git and never copied into the production image.

Current acceptance limits: recorded Edge and focused correction evidence cover the observed desktop/emulated widths, languages and themes, not physical devices or comprehensive accessibility/contrast/reduced-motion acceptance. Those reviews remain deferred to the pre-public-launch gate. Other copy/translation decisions and the quota-failed Copilot review remain open. Email eligibility must be resolved in a separately authorized task before its UI can be enabled; no new provider attempt is included. No merge or deployment is authorized by this slice.
