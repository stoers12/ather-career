# FE-01 — shared workspace and Overview

Decision ق-33. Base: `c90f76b` (`origin/main`, after #77, B-01 #78 and B-02 #79).
Reference: the owner's `ATHER-Agent-Handoff (2).zip`, extracted outside the repository. The PNGs determine appearance; the root prototype was checked for group, compact and account behavior. Existing uncommitted presentation work from the `redesign` worktree supplied the initial markup, tokens and licensed fonts.

Production `/owner.php` loads only `ownerDashboardPageData($database, $context)`. `renderOwnerDashboard()` consumes that contract, including its unavailable state. The backend model, queries, authorization, request fields, actions, routes and CSRF contracts are unchanged. All captures use a guarded synthetic B-01 fixture; no resident Owner data or sessions are included.

## Deliberate differences from the PNGs

- The toolbar retains the EN/عربي link for FE-01. Settings/help entries and the unread message badge are absent, as requested. Their removal also moves the account control upward; there are no blank utility slots.
- Publication status describes actual publication and links to the contract's real public URL. It never says “published in this preview.” Project titles, categories, initials, visibility, counts and the next-step suggestion come from real data in production.
- The prototype's “interactive preview / demo changes” and “design exploration” footer is omitted from the application.
- Empty, unavailable, writing-needed and draft states follow B-01 rather than the reference's populated demo. Written-part counts are never presented as an evidence-quality score.
- Without JavaScript, navigation stays expanded, native groups/account menus work, and preference buttons are hidden. On coarse pointers, controls grow to at least 44px. Both adaptations can increase spacing relative to the desktop PNGs.
- Evidence Hub retains its separate shell and assets, as required by the integration guide and its protected design track. This slice does not restyle its analytics or remove actions. Settings remains available at its existing route, with the shared toolbar, pending FE-02's page design and navigation item.
- Project tile monograms are derived from the actual project title; the reference's arbitrary demo tile letters are not hard-coded.

## Verification

| Check | Output |
| --- | --- |
| PHPUnit | `OK (116 tests, 262 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | No new errors; only the 22 known unmatched baseline-ignore entries reported by the dev image |
| JavaScript syntax | `node --check` passed for `admin.js`, `owner_theme.js` and the workspace browser driver |
| Gate registration | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates | `REQUIRED_GATES_PASSED` |
| Workspace browser gate | `OWNER_WORKSPACE_VISUAL_PASSED` |
| Whitespace / encoding | `git diff --check`; changed text files use LF, UTF-8 without BOM |

The new browser gate is registered and executed by `scripts/run-evidence-hub-required-gates.ps1`, using its disposable Apache, Playwright 1.58.2 and Chromium 1208. It checks ar/en × light/dark × 390/768/1280/1920/2560px; compact persistence and group restoration; denied storage; menu/account Escape and focus; native no-JS account forms; B-01 states; long mixed-direction text; AA text contrast; coarse targets; and short-window navigation with 12 temporary entries.

The existing workspace DOM assertion now finds the same seven real links inside native groups. Its exact routes, active item, escaped name and POST/CSRF checks remain. The flow assertion names B-01's loader instead of the retired dashboard aggregate; the frontend assertion follows the new presentation file and checks Experience in shared navigation rather than the old dashboard quick-action row. The integrated journey's first-name selector and single Add project link are preserved.

Reproduce the complete verification and export all fresh synthetic captures outside the checkout:

```powershell
pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -EvidenceDirectory '<new-directory-outside-repository>'
```

## Matched visual comparisons

Each comparison has the supplied reference on the left and FE-01 on the right, at the original viewport width and full-page height. Remaining white/gray area under the shorter page is comparison padding. The no-JS capture has no matching reference state.

| View | Light | Dark |
| --- | --- | --- |
| 1440 Arabic Overview | [Compare](overview-ar-light-1440.png) | [Compare](overview-ar-dark-1440.png) |
| 1440 English Overview | [Compare](overview-en-light-1440.png) | [Compare](overview-en-dark-1440.png) |
| 390 Arabic Overview | [Compare](overview-ar-light-390.png) | [Compare](overview-ar-dark-390.png) |
| 390 Arabic menu | [Compare](mobile-menu-light-390.png) | [Compare](mobile-menu-dark-390.png) |
| 1440 Arabic account | [Compare](account-light-1440.png) | [Compare](account-dark-1440.png) |
| 1440 Arabic compact rail | [Compare](compact-light-1440.png) | [Compare](compact-dark-1440.png) |
| 390 Arabic without JavaScript | [Capture](overview-ar-no-js-390.png) | Default light fallback |
