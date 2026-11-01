# L-03 data rights UI evidence

Latest: [PR #105 review fixes, verification and after-download screenshot](review-fixes.md).

Base: `origin/main` at `ccbf5faf08a9` (PR #104). Branch: `feat/l03-data-rights-ui`.

All captures use synthetic account data and the actual PHP presentation. The final full required-gates run generated these 64 screenshots. Both languages, both themes, and 1440px / 390px are included. The due date fixture is `2026-12-01T23:30:00Z`; Asia/Amman displays December 2, 2026.

The Settings recent-state captures show the native disclosure opened with the keyboard. The error state opens it on the server and associates the announced error with the confirmation field. Cancellation, export, identity confirmation and logout are covered across the two scheduled-page states; export is replaced by identity confirmation when required by the contract.

| State | AR light | AR dark | EN light | EN dark |
|---|---|---|---|---|
| Settings: confirm identity | [1440](screenshots/l03-settings-locked-ar-light-1440.png) · [390](screenshots/l03-settings-locked-ar-light-390.png) | [1440](screenshots/l03-settings-locked-ar-dark-1440.png) · [390](screenshots/l03-settings-locked-ar-dark-390.png) | [1440](screenshots/l03-settings-locked-en-light-1440.png) · [390](screenshots/l03-settings-locked-en-light-390.png) | [1440](screenshots/l03-settings-locked-en-dark-1440.png) · [390](screenshots/l03-settings-locked-en-dark-390.png) |
| Settings: recently confirmed | [1440](screenshots/l03-settings-recent-ar-light-1440.png) · [390](screenshots/l03-settings-recent-ar-light-390.png) | [1440](screenshots/l03-settings-recent-ar-dark-1440.png) · [390](screenshots/l03-settings-recent-ar-dark-390.png) | [1440](screenshots/l03-settings-recent-en-light-1440.png) · [390](screenshots/l03-settings-recent-en-light-390.png) | [1440](screenshots/l03-settings-recent-en-dark-1440.png) · [390](screenshots/l03-settings-recent-en-dark-390.png) |
| Settings: confirmation error | [1440](screenshots/l03-settings-confirmation-ar-light-1440.png) · [390](screenshots/l03-settings-confirmation-ar-light-390.png) | [1440](screenshots/l03-settings-confirmation-ar-dark-1440.png) · [390](screenshots/l03-settings-confirmation-ar-dark-390.png) | [1440](screenshots/l03-settings-confirmation-en-light-1440.png) · [390](screenshots/l03-settings-confirmation-en-light-390.png) | [1440](screenshots/l03-settings-confirmation-en-dark-1440.png) · [390](screenshots/l03-settings-confirmation-en-dark-390.png) |
| Settings: expired identity notice | [1440](screenshots/l03-settings-reauthenticate-ar-light-1440.png) · [390](screenshots/l03-settings-reauthenticate-ar-light-390.png) | [1440](screenshots/l03-settings-reauthenticate-ar-dark-1440.png) · [390](screenshots/l03-settings-reauthenticate-ar-dark-390.png) | [1440](screenshots/l03-settings-reauthenticate-en-light-1440.png) · [390](screenshots/l03-settings-reauthenticate-en-light-390.png) | [1440](screenshots/l03-settings-reauthenticate-en-dark-1440.png) · [390](screenshots/l03-settings-reauthenticate-en-dark-390.png) |
| Scheduled: download available | [1440](screenshots/l03-scheduled-recent-ar-light-1440.png) · [390](screenshots/l03-scheduled-recent-ar-light-390.png) | [1440](screenshots/l03-scheduled-recent-ar-dark-1440.png) · [390](screenshots/l03-scheduled-recent-ar-dark-390.png) | [1440](screenshots/l03-scheduled-recent-en-light-1440.png) · [390](screenshots/l03-scheduled-recent-en-light-390.png) | [1440](screenshots/l03-scheduled-recent-en-dark-1440.png) · [390](screenshots/l03-scheduled-recent-en-dark-390.png) |
| Scheduled: confirm identity / notice | [1440](screenshots/l03-scheduled-locked-ar-light-1440.png) · [390](screenshots/l03-scheduled-locked-ar-light-390.png) | [1440](screenshots/l03-scheduled-locked-ar-dark-1440.png) · [390](screenshots/l03-scheduled-locked-ar-dark-390.png) | [1440](screenshots/l03-scheduled-locked-en-light-1440.png) · [390](screenshots/l03-scheduled-locked-en-light-390.png) | [1440](screenshots/l03-scheduled-locked-en-dark-1440.png) · [390](screenshots/l03-scheduled-locked-en-dark-390.png) |
| Sign-in: deletion scheduled | [1440](screenshots/l03-signin-notice-ar-light-1440.png) · [390](screenshots/l03-signin-notice-ar-light-390.png) | [1440](screenshots/l03-signin-notice-ar-dark-1440.png) · [390](screenshots/l03-signin-notice-ar-dark-390.png) | [1440](screenshots/l03-signin-notice-en-light-1440.png) · [390](screenshots/l03-signin-notice-en-light-390.png) | [1440](screenshots/l03-signin-notice-en-dark-1440.png) · [390](screenshots/l03-signin-notice-en-dark-390.png) |
| Dashboard: deletion cancelled | [1440](screenshots/l03-dashboard-notice-ar-light-1440.png) · [390](screenshots/l03-dashboard-notice-ar-light-390.png) | [1440](screenshots/l03-dashboard-notice-ar-dark-1440.png) · [390](screenshots/l03-dashboard-notice-ar-dark-390.png) | [1440](screenshots/l03-dashboard-notice-en-light-1440.png) · [390](screenshots/l03-dashboard-notice-en-light-390.png) | [1440](screenshots/l03-dashboard-notice-en-dark-1440.png) · [390](screenshots/l03-dashboard-notice-en-dark-390.png) |

## Implementation and contract

- Added `#data` to Settings and its existing hash-navigation enhancement; without JavaScript, all sections remain reachable through native anchors.
- Preserved all URLs, POST methods, exact field sets and CSRF fields. Backend logic, authorization, redirects and validation are unchanged.
- Reused workspace tokens and the recovery stylesheet. No dependencies, inline scripts or inline styles were added.
- Used `grace_days` in the deletion warning, escaped and isolated the confirmation phrase, and added a native disclosure before the destructive form.
- Added fixed, strictly selected AR/EN notices to sign-in and the dashboard. Unknown, structured and wrong-page values render nothing.
- The scheduled page has no workspace sidebar. Cancellation is the first primary form. The date is localized on the server and enhanced to the device timezone by an external script.
- Moved the existing browser-preference note into Appearance so account actions are not described as browser-only preferences.

## Changed existing assertions

One existing assertion changed: `tests/Browser/owner-settings-checks.cjs` checks **3** Settings navigation links instead of **2**, preserving its exact-count rule after adding Your data. Its final progress message says “three sections”. No existing assertion was removed or weakened. Existing Arabic expectations and fixture text remain unchanged.

## Verification

See [verification.md](verification.md) for commands, exit codes and sanitized output. Raw logs and private fixture carriers are not committed.

## Deviations and limits

No product or backend contract deviations. PHPStan retains exactly the 22 known `ignore.unmatched` baseline entries; no new errors or baseline edits. Browser automation used Playwright 1.58.2 / Chromium 1208 on Windows with disposable Linux PHP 8.3 and MySQL 8.4 containers. It does not claim manual screen-reader or physical-device certification.
