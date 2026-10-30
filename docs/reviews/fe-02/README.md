# FE-02 — Settings and Help

Decision ق-33. Base `3c74a38` (`origin/main` after FE-01 #81, settings #79 and message read state #80). The owner's extracted `ATHER-Agent-Handoff (2).zip` remains the visual reference. All captures use synthetic fixtures; no resident Owner data or sessions are committed.

`renderOwnerSettingsPage()` consumes the existing Settings contract. The handler, authorization, GET/HEAD boundary and 503 response are unchanged. The only new runtime backend call is `ownerWorkspaceUnreadMessageCount()`, once while rendering the shared sidebar. No application queries or routes were added. The isolated browser fixture alone inserts and removes its own synthetic message in the disposable gate database.

## Differences from the screenshots

- Only Appearance & language and Help appear in section navigation. Removing the three unsupported phase-C sections shortens the mobile navigation. There are no disabled/future placeholders.
- Workspace language uses the supplied native GET links as a small language selector instead of the prototype's select. This works without JavaScript and needs no POST or auto-submit.
- Help answers describe the actual product. The unsupported support-center box and prototype footer are omitted. The preference note truthfully says settings are saved in this browser. Public portfolio language remains read-only English, with the actual visitor-language explanation.
- Text and motion selects are 170px on desktop (185px on mobile), slightly wider than the reference, so the full English system-setting label remains readable. The outer shell and 228px sidebar widths are unchanged.
- With JavaScript disabled, both real sections and native FAQs remain visible; appearance/text/motion controls and preference-only buttons are hidden. The mobile sidebar is expanded, retaining every real destination.
- Large text scales workspace text by 16% and lets content reflow. Appearance defaults to system, per contract; comparison captures explicitly select light/dark to match those reference states.
- The unread badge reflects the real count. Synthetic visual fixtures have no authorized unread count and therefore show none; the authenticated database lifecycle test verifies zero, one unread and opening/read states.

Evidence Hub retains its own shell and language control. Its existing direct ar/en switch uses `ownerLanguageUrl()` / `ownerPersistLocale()` and the shared `ather_owner_locale` cookie; switching there also changes the workspace language preference. This slice leaves that behavior unchanged.

## Verification

| Check | Result |
| --- | --- |
| PHPUnit | `OK (121 tests, 322 assertions)` |
| Phase 2 | `Phase 2 test foundation passed.` |
| PHPStan | Only the 22 known unmatched baseline-ignore entries; no new errors |
| JavaScript syntax | Passed for the controller and both browser drivers |
| Full required gates | `REQUIRED_GATES_PASSED` |
| Settings browser checks | `OWNER_SETTINGS_VISUAL_PASSED` |
| Real unread-message lifecycle | `OWNER_UNREAD_BADGE_LIFECYCLE_PASSED` |
| Existing workspace checks | `OWNER_WORKSPACE_VISUAL_PASSED` |

The existing registered workspace gate now calls the Settings checks. Coverage includes theme persistence and removal for system mode; consistency with the toolbar; text/motion attributes before the first workspace content; native no-JS language switching and FAQ disclosures; exactly two sections; sidebar destinations/current state; no shared toolbar language link; AA text contrast; and ar/en × light/dark × default/large layouts at 390/768/1280/1920/2560px. Authenticated checks also cover every shared Owner route with large text at these widths. The message test inserts one unread message, follows the real message-opening link, then cleans up its own row.

The legacy sidebar test now expects the original seven destinations plus Settings and Help, preserving exact-route, active-item, escaping and CSRF checks. Unit tests cover both localized sections, exclusion of phase-C content, native radio semantics, hidden browser-only controls and escaping of template language values/URLs.

Reproduce the full suite and export fresh synthetic screenshots outside the checkout:

```powershell
pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -EvidenceDirectory '<new-directory-outside-repository>'
```

## Visual comparisons

Reference on the left; FE-02 on the right. Images retain their original viewport width; extra space below a shorter page is comparison padding. No-JS and large-text results are compared with the supplied normal reference because those states have no dedicated source PNG.

| View | Light | Dark |
| --- | --- | --- |
| 1440 Arabic appearance | [Compare](appearance-ar-light-1440.png) | [Compare](appearance-ar-dark-1440.png) |
| 1440 Arabic Help | [Compare](help-ar-light-1440.png) | [Compare](help-ar-dark-1440.png) |
| 1440 English appearance | [Compare](appearance-en-light-1440.png) | [Compare](appearance-en-dark-1440.png) |
| 390 Arabic appearance | [Compare](appearance-ar-light-390.png) | [Compare](appearance-ar-dark-390.png) |
| 390 Arabic without JavaScript | [Compare](appearance-ar-no-js-390.png) | Default light fallback |
| 1440 Arabic large text | [Compare](appearance-ar-large-light-1440.png) | [Compare](appearance-ar-large-dark-1440.png) |
