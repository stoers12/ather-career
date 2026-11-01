# PR #105 review fixes

The Settings and scheduled-deletion export forms no longer have `data-owner-form`. ZIP attachments leave the current page open, so their buttons keep the original localized label and remain enabled after downloading. Identity confirmation, deletion scheduling, deletion cancellation and logout retain that hook and now use escaped AR/EN pending labels from `owner_data_rights_copy.php`.

No backend, contract, field, route, method, layout, stylesheet or shared `admin.js` changes.

The regression check first failed on the reviewed code (`settings/ar: export stays enabled after download`, actual false). It now downloads an attachment with the contract's ZIP content type and attachment header on both pages in AR and EN, awaits completion, checks the original document remains open, and verifies the button stays enabled with its original label and no busy state. Separate submit checks record all non-export actions' live localized pending labels before navigation. Existing exact POST-field assertions remain unchanged; the unit helper adds form-hook and pending-label assertions.

[Settings after a download, Arabic / 390px](screenshots/l03-settings-after-download-ar-390.png)

## Verification

| Check | Result |
|---|---|
| PHPUnit (standalone and full gate) | Exit 0 — `OK (241 tests, 1943 assertions)` |
| Full required gates | Exit 0 — `REQUIRED_GATES_PASSED` |
| Phase 2 (inside full gate) | `Phase 2 test foundation passed.` |
| Focused L-03 browser checks | Exit 0 — both download/pending regression and the full AR/EN light/dark layout/native-form matrix passed |
| Registered browser checks (inside full gate) | All Owner workspace and Evidence Hub checks passed |
| Production boundary/security (inside full gate) | Passed |
| PHPStan | Exit 1 — exactly 22 known `ignore.unmatched` entries; no new errors or baseline changes |
| `node --check`, `git diff --check`, UTF-8/LF | Passed |

```text
L03_DOWNLOAD_PENDING_PASSED: Settings and scheduled ZIP exports remain enabled without navigation; all other actions show localized AR/EN pending labels.
L03_DATA_RIGHTS_BROWSER_PASSED: ar/en, light/dark, 320/390/768/1280/1440, keyboard, AA contrast, 44px, local dates, strict notices, all native POST field sets without JavaScript.
Production security gate passed.
REQUIRED_GATES_PASSED
```

The first pending-label browser assertion raced navigation. It was changed to record the real submit state before the document is replaced, then await the navigation. The final focused run and full required-gate run passed with the final source.

The focused browser run and final full required-gate run use Playwright 1.58.2 / Chromium 1208, the real PHP presentations and `admin.js`, and synthetic fixtures in disposable containers. The ZIP response in the regression is intercepted to reproduce attachment semantics without touching account data. One new screenshot is included; the original 64 captures remain as initially submitted.

No deviations and no weakened or removed assertions. Raw logs stay outside the repository. The PR remains draft; nothing was merged or deployed.
