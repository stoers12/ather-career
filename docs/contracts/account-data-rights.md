# Contract: account data rights (export and deletion)

Backend: Claude. Frontend: Codex. Card L-03 (P-05). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

The owner can download all their data, and delete their account. Deletion hides the public page at once, gives **30 days** to change their mind, then deletes everything, including the Auth0 account. This matches section 7 of the Privacy Policy.

## Confirming identity ("recent sign-in")

Export and deletion need a sign-in from the **last 10 minutes** (`ACCOUNT_RECENT_AUTHENTICATION_SECONDS`).

| Item | Value |
|---|---|
| URL | `POST /owner_reauthenticate.php` |
| Fields | `csrf_token`, and optional `return`: `data` (default) or `deletion`. Any other field gets `403`. |
| Effect | Ends the current session and sends the browser to Auth0 with `prompt=login`. |
| After sign-in | `return=data` lands on `/owner_settings.php#data`. `return=deletion` lands on `/owner_account_deletion.php`. If the journey sends the owner elsewhere (for example, onboarding), the journey wins. |
| Rate limit | Shares the sign-in start limit: `429` with `Retry-After`. |

## Settings: the "Your data" section

`renderOwnerSettingsPage($settings)` now also receives `$settings['data_rights']`, from `ownerDataRightsSettingsData()`:

| Key | Type | Meaning |
|---|---|---|
| `export_url` | string | `/owner_data_export.php` |
| `reauthenticate_url` | string | `/owner_reauthenticate.php` |
| `deletion_url` | string | `/owner_account_deletion.php` |
| `recently_authenticated` | bool | True: show the export and delete buttons. False: show «أكّد هويتك» first (a form to `reauthenticate_url` with `return=data`). |
| `recent_until` | int or null | Unix time when the recent sign-in expires. Optional for the UI. |
| `confirmation_phrase` | string | What the owner types to confirm deletion: their **public slug**, or the word `delete` if they never chose one. Comparison ignores case and surrounding spaces. |
| `grace_days` | int | `30`. Show it in the warning text; never hard-code it. |
| `notice` | `null`, `'reauthenticate'` or `'confirmation'` | Set after a refused action. `reauthenticate`: the sign-in was too old. `confirmation`: the typed name did not match. Show it as an announced error in this section. |

The section's anchor is `#data`. The contract in [owner-settings.md](owner-settings.md) remains in force for everything else.

## Export: "Download my data"

| Item | Value |
|---|---|
| URL | `POST /owner_data_export.php` |
| Fields | exactly `csrf_token`. |
| Who | Any signed-in owner, **including an account scheduled for deletion**. |
| Sign-in too old | `303` to `/owner_settings.php?data=reauthenticate#data` (or `/owner_account_deletion.php?data=reauthenticate` for a scheduled account). |
| Rate limit | 5 per hour per account: `429` with `Retry-After`. |
| Success | `200`, `Content-Type: application/zip`, `Content-Disposition: attachment; filename="ather-data-YYYY-MM-DD.zip"`. A plain form submit downloads it; no JavaScript is needed. |

The ZIP holds:
- `data.json`: every row of the account, by table (`format: ather-account-export`, `version: 1`);
- `readable.html`: the same data as a page a person can read, in the owner's language;
- `images/profile/…` and `images/projects/…`: the original images.

It is built on request and streamed. Nothing is kept on the server.

## Deletion

### Request (from Settings)

| Item | Value |
|---|---|
| URL | `POST /owner_account_deletion.php` |
| Fields | exactly `csrf_token`, `action=request`, `confirmation` (the typed phrase). |
| Sign-in too old | `303` to `/owner_settings.php?data=reauthenticate#data`. |
| Wrong phrase | `303` to `/owner_settings.php?data=confirmation#data`. Nothing changes. |
| Success | The public page returns `404` at once (page, projects, images, contact form). Every session of the account ends, on every device. `303` to `/signin.php?account=deletion_scheduled`. |

The frontend shows the confirmation step before posting: what will be deleted, the 30-day period, and the field for the phrase. On sign-in, `account=deletion_scheduled` shows a notice such as «تم جدولة حذف حسابك. سجّل الدخول خلال 30 يوماً إن غيّرت رأيك.»

### The scheduled account

After signing in, every owner page redirects to `GET /owner_account_deletion.php`. It renders `renderOwnerAccountDeletionPage(array $page)` in `includes/owner_data_rights_presentation.php`. **The frontend owns that file**; the backend ships a plain version so the flow already works.

`$page` from `ownerAccountDeletionPageData()`:

| Key | Type | Meaning |
|---|---|---|
| `locale` | `'ar'` or `'en'` | Page language. |
| `requested_at`, `due_at` | string | UTC ISO 8601, for example `2026-12-01T10:00:00Z`. Show `due_at` as a local date. |
| `grace_days` | int | `30`. |
| `cancel_url` | string | Form: `POST`, `csrf_token` + `action=cancel`. |
| `export_url` | string | The export form above. |
| `reauthenticate_url` | string | When `recently_authenticated` is false, offer it with `return=deletion` before export. |
| `logout_url` | string | The usual logout form. |
| `recently_authenticated` | bool | As in Settings. |
| `notice` | `null` or `'reauthenticate'` | Export was refused because the sign-in was too old. |

`GET` for an account that is not scheduled redirects to `/owner_settings.php#data`.

### Cancel

`POST /owner_account_deletion.php` with exactly `csrf_token` and `action=cancel`. No recent sign-in needed. Success: `303` to `/owner.php?account=deletion_cancelled`, and everything is exactly as before (page, projects, publication). The dashboard may show a short success notice for that query value.

### Permanent deletion (backend only)

`scripts/ops/purge-deleted-accounts.php run`, daily on the server. After the 30 days it:
1. closes the account (no sign-in, no cancel);
2. deletes the Auth0 user of every identity through the Management API (`delete:users` only);
3. deletes the portfolio's images and files;
4. deletes every row, and keeps one line in `account_deletions`: the internal id and two dates, no personal data.

If Auth0 fails, nothing is deleted locally and the next run tries again. Without `OIDC_MANAGEMENT_DOMAIN`, `OIDC_MANAGEMENT_CLIENT_ID` and `OIDC_MANAGEMENT_CLIENT_SECRET` the script refuses to run.

## Frontend rules

Everything escaped; no inline script or style (CSP); WCAG AA in light and dark; 44px targets; no horizontal scroll at 320/390/768/1280px; Arabic RTL and English LTR. The delete button is visually destructive and never the default action.
