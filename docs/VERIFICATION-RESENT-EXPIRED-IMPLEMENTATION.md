# Verification resent and expired presentation — 2026-10-12

This isolated batch adds fixed `resent` and `expired` fixtures to the existing email-verification template. Base: approved PR #11 merge `6edd46a2e922a2d3263fea83f2cc5536b247d8e1`. Read-only remote-main inspection confirmed that revision was still current. The earlier pending/success owner acceptance remains separate from acceptance of these new screens.

## Scope and boundaries

- Reuse the merged layout, local fonts, theme script, localization, disabled action conventions and static icons; add the reference's send, clock-alert and triangle-alert shapes. No production dependency, public fixture shim or production rewrite is added.
- The fixture requires `ATHER_EMAIL_PREVIEW === true`, CLI/CLI-server and an allowlisted server-selected value. The external loopback preview router maps fixed paths to fixtures; it is evidence/runtime tooling outside the repository. Query parameters, cookies and browser storage cannot select verification state, verify identity or grant access. Only language and the existing theme preference are client-selectable.
- Both new screens visibly disclose that no email was sent and no account, verification status or access changed. Resent has native-disabled refresh/resend actions with the existing visible explanation. Expired has a native-disabled new-link action and its own visible explanation. Localized Sign In links navigate normally; the isolated host cannot start authentication.
- Arabic/RTL and English/LTR, light/dark, and automatic responsive layout share the merged styles. Pending/success copy and rendered markup are preserved; source comparison normalizes only asset cache-version timestamps. Existing pending focus correction is reused.
- CI's production HTTP 404 assertion includes both new fixture paths. No workflow gate is weakened. No production image/container was built or run during this batch.

## Approved reference and adaptations

Original: `ather-email-account-states.html` in the existing `ather-approved-designs-20261004` archive and the prior batch's preserved references. SHA-256: `744f0202c09229d273e39196536924287a2e4268d583692931433197eb6762b9`. Original bytes remain unchanged.

Retained: hierarchy, grid/card geometry, typography, theme colors, resent success/send icon, expired clock icon and warning, masked address on resent, and action hierarchy. Adaptations: prominent fixture/no-change disclosure and explanatory copy, neutral disabled action fills, no reference timer/countdown, no claim about old-link invalidation or used-token behavior, planned-flow privacy wording, real localized Sign In links, theme/skip/focus controls, English/LTR, and automatic narrow scrolling without prototype device/state toolbars. The additional notices make cards taller. The expired warning selector targets the static SVG icon to preserve the reference's gold warning color. Reference comparisons are adapted visual comparisons, not pixel equivalence.

## Validation

Evidence: `D:/2- Ather Portfolio Project Planing SaaS/ملفات Temp/ather-verification-resent-expired-20261012/`.

- Installed supported Playwright 1.58.0 / headless Chromium 145.0.7632.6, isolated nonpersistent contexts, loopback-only request allowance and blocked service workers; no package/browser download, user profile, credentials or provider experiment.
- **24 combinations**: resent/expired × 1280×900, 320×645, 390×645 × Arabic/English × light/dark. **1,404 assertions, zero failures**. Includes actual wheel scrolling, wrapping/horizontal overflow, Tab/Shift+Tab/page boundary/skip behavior, visible/unclipped focus, disabled pointer/focus checks, localized navigation, theme/language persistence, hostile query/cookie/storage claims, and application Console/page/resource failures. No auth/mutation/external requests or new auth/session cookies observed.
- All 24 full-page captures and narrow focus sheets visually inspected. Four Arabic approved-reference comparisons cover both screens at desktop/light and 390px/dark, using static derivatives with verified local fonts; original prototype scripts never executed. No concrete unresolved presentation defect was identified.
- PHP lint: changed PHP files and external router, five files. **244 account-entry presentation contracts**, **62 Sign In contracts**, five focused identity/authentication/architecture regressions plus scoped teardown passed. These are synthetic local tests, with no real provider/database access.
- **70 valid local HTTP/guard/regression checks**, zero failures: methods/headers/client-state boundaries, no public shims, public-root 404s, private preview paths denied, unknown fixture/constant guards, and unchanged pending/success markup. Eight initial harness assertions miscompared fresh-worktree asset timestamps or HTTP CRLF versus CLI LF. Diagnosis and initial results are retained; only those eight checks were rerun with corrected comparison normalization. This required no product change or visual-matrix rerun.
- Diff whitespace check passed. The prior batch's 36-case matrix and accepted provider experiments were not repeated. Prior exact-merge Main CI 38170059703 belongs to the base; no new remote CI result is claimed because this batch is local and unpublished.

## Remaining acceptance and integration gaps

Owner visual/copy/adaptation acceptance is pending for these two new screens. Headless Chromium with emulated widths does not certify physical devices, other browsers or comprehensive accessibility/contrast/reduced-motion behavior. Functional delivery, resend, verification refresh, token policy, trusted server confirmation and account/access transitions remain unavailable and require separately authorized integration. Existing owner-reported Copilot quota limitation is not a successful or waived scan; no new scan/review was requested.

No push, PR, merge, deployment, canonical E: working-file update, Auth0/identity binding change, migration, database/container/volume change or Notion update is part of this batch. Completed branches/worktrees and the canonical script modification remain preserved.
