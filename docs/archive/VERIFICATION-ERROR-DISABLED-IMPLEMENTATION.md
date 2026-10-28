# Verification error and disabled-account presentations — 2026-10-12

This focused batch starts from approved main `62adb2243e186646a127ff1eedaa886f2a738fda` on isolated branch `feat/verification-error-disabled-20261012`. Read-only remote main and every preserved worktree matched the verified pause record. Canonical E: and its existing script change remain untouched. Owner acceptance of these two screens, translations and adaptations is pending.

## Presentation and fixture boundaries

The merged verification template now accepts two additional fixed server-selected values, `error` and `disabled`. The strict `ATHER_EMAIL_PREVIEW === true` and CLI/CLI-server guards remain intact. An external loopback router maps `/verification-error.php` and `/account-disabled.php`; neither path exists in the production public surface. The existing production CI 404 assertion includes both paths. No dependency or production rewrite was added.

Both headings say preview. Descriptions explicitly state that no real verification request failed and no real account was disabled. The existing prominent disclosure states that no email was sent, no account/verification/access changed and no access is granted. Original reference codes are labelled examples and isolated with LTR `bdi`; they identify no real incident or account.

Retry, secure review and sign-out are native-disabled buttons with visible, associated explanations. Error keeps two vertically arranged actions; disabled keeps two buttons and a final text navigation slot. The disabled reference's account-switch control is adapted to an explicitly labelled localized Return to sign in link: it opens the sign-in page, without switching identity. The explanation also states that account switching is unavailable. Only the existing theme, language, skip, home and Sign In navigation can operate; the preview router refuses authentication and all mutation methods.

Arabic/RTL, English/LTR, both themes and automatic narrow layouts reuse merged fonts, styles and theme behavior. There are no device/state selectors or timers. Queries, cookies and browser storage cannot choose a fixture, verify an account or grant access.

## Reference and adaptations

Original `ather-email-account-states.html` is preserved unchanged, SHA-256 `744f0202c09229d273e39196536924287a2e4268d583692931433197eb6762b9`. It was located in the prior batch's `references/` and copied into this batch's archive. Static comparison derivatives use local verified fonts and the previously preserved Lucide reference; original prototype scripts/imports/toolbars never execute.

Retained: grid/card composition, action order and treatments, fonts, theme tokens, state icons (circle-alert/user-x), action icons (rotate-ccw/message-circle), error shield notice, disabled warning notice and reference-code placement. Five static Lucide shapes reuse the established icon renderer/license. The error notice selector now targets `.hf-icon` rather than prototype `i`, preserving the original red icon color. Added fixture disclosure, truthful preview headings/descriptions, native-disabled neutral fills and explanations, example-code labels, planned-flow support/privacy wording, safe Sign In navigation, existing theme/skip/focus controls and English/LTR make the cards taller. Narrow layouts scroll naturally. Reference comparisons document adaptations; pixel equivalence is not claimed.

## Validation and preservation

Evidence folder: `D:/2- Ather Portfolio Project Planing SaaS/ملفات Temp/ather-verification-error-disabled-20261012/`.

- PHP 8.2.33 lint passed for four changed PHP files and the external router. Focused command `php scripts/test/run-account-entry-tests.php error disabled` passed 139 checks. Optional screen arguments allow focused execution; no-argument CI still checks every presentation.
- Installed Playwright 1.58.0 / headless Chromium 145.0.7632.6: 24 combinations, both screens × 1280×900 / 320×645 / 390×645 × AR/EN × light/dark. 1,404 assertions, zero failures. Actual pointer/wheel/Tab/Shift+Tab input verifies wrapping, scrolling, overflow, visible unclipped focus, skip behavior, disabled actions, safe links, theme/language persistence, hostile query/cookie/storage claims and absent application Console/page/resource errors. No external, authentication or mutation requests or new session cookies observed. Isolated nonpersistent contexts; service workers blocked; no package/browser download or user profile access. Default launch EPERM was resolved through supported escalated execution; CSP and browser settings were not relaxed.
- All 24 full-page captures, 16 narrow focus captures and four reference comparisons were visually inspected. No concrete unresolved presentation defect was identified.
- 86 valid boundary/preservation checks passed: strict missing/false/non-boolean guards, unknown fixtures, GET/HEAD and rejected mutation methods, no-store/locale/no-index/CSP headers, ignored hostile claims, structured language fallback, denied private/auth paths and public-root 404s. The separate task-owned public-root server was stopped; this is not a full Apache/Docker smoke.
- Initial boundary harness had 12 comparison failures: eight inter-tag indentation differences in old-screen output, four Git LF versus checkout CRLF comparisons. Product unchanged; originals and diagnosis preserved. Only those 12 comparisons were corrected/rerun; 74 valid original checks reused.
- Accepted pending/success/resent/expired output matches baseline in both languages after normalizing asset timestamps, line endings and inter-tag whitespace. Prior translations remain exact. The sole CSS change cannot match those screens. Theme script, fonts, Sign In/Sign Up and icon fallback remain unchanged. Their existing visual matrices and authentication evidence are reused, with no repeat of unaffected browser/provider tests.
- Source hashes bind browser evidence to the committed implementation; final closure checks them against the worktree. Diff whitespace checks passed. No new remote CI or review scan exists for this unpublished local commit; absent checks are neither passed nor waived.

## Review and integration limits

Owner acceptance remains pending for both screens, translations and adaptations. Headless Chromium widths do not certify physical devices, other browsers or comprehensive accessibility/contrast testing. Functional retry, review, sign-out, account switching, registration/email delivery/resend/verification/token handling and account/access transitions remain unavailable from these fixtures and need separately authorized integration.

No push, PR, merge or deployment; no canonical E: working-file, Auth0, identity binding, database, migration, container, volume or Notion change. Existing worktrees and unrelated changes remain preserved. Exact preview restart and recovery instructions, final commit SHA and archive checksums are in the new durable `BATCH-HANDOFF.md`.
