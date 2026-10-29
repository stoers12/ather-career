# Contract: owner settings page

Backend: Claude. Frontend: Codex (slice FE-02, decision ق-33). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

## Route

| Item | Value |
|---|---|
| URL | `/owner_settings.php` (route table key `owner_settings.php`) |
| Methods | `GET`, `HEAD`. Anything else gets `405`. |
| Access | Signed-in owner only. A visitor without a session is sent to sign in, like every owner page. |
| Database unavailable | `503` with a plain message and no workspace chrome, because the owner cannot be confirmed. |
| Layout | `ownerLayoutStart('Settings', 'settings', true, …)`. The active page key is `settings`. |
| Template | `renderOwnerSettingsPage(array $settings)` in `includes/owner_settings_presentation.php`. The frontend owns this file. |

## Template data: `ownerSettingsPageData()`

| Key | Type | Meaning |
|---|---|---|
| `locale` | `'ar'` or `'en'` | The current workspace language. |
| `languages` | list of `{code, url, current}` | One entry per supported language. `url` switches the language and stays on this page. `current` marks the active one. |
| `appearance` | `{storage_key, options, default}` | `storage_key` is `ather.evidenceHub.theme` (the existing key). `options` are `system`, `light` and `dark`; `default` is `system`. |
| `text_size` | `{storage_key, options, default}` | `ather.owner.textSize`; options `default` and `large`. |
| `motion` | `{storage_key, options, default}` | `ather.owner.motion`; options `system` and `reduce`. |

## Behavior the frontend implements

- **Language:** links to `languages[*].url` (GET). Do not post a form; the server stores the preference.
- **Appearance:** extend `owner_theme.js` with a three-way control.
  - `light` or `dark`: store that value under the key.
  - `system`: **remove** the key, so the device decides. The current resolver already behaves this way.
  - Never create a second theme store.
- **Text size and motion:** store under their keys and apply before first paint, from `owner_theme.js`, with a root attribute that the CSS reads. The control falls back to the default when storage is unavailable.
- **Without JavaScript:** language links still work. The browser-preference controls cannot work without JavaScript, so show them only when JavaScript is available (progressive enhancement). Never show a control that does nothing.
- **Help:** static content on this page. It needs no backend.

## Not part of this contract

Notifications, account and security, privacy and data. They are phase C features (P-01…P-05); each gets its own contract when its backend exists. Until then, **no sections, links or disabled placeholders for them appear on the page.**
