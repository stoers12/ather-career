# Contract: owner Overview (dashboard)

Backend: Claude. Frontend: Codex (slice FE-01). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

## Route

| Item | Value |
|---|---|
| URL | `/owner.php` (unchanged) |
| Methods | `GET`, `HEAD` |
| Access | Signed-in owner only (unchanged). |
| Database unavailable | Status `503`. The page shows the existing "temporarily unavailable" state with a reload link, and **no counts, projects or publication state**: a failed read must never look like an empty portfolio. |
| Layout | `ownerLayoutStart(…, 'dashboard', true, ['locale' => $locale, 'account_name' => $data['owner_name']])` |

## Loading the data

In `handlers/owner.php`, the `try` block reads everything with **one call**, and keeps its existing `catch` branches as they are:

```php
require_once dirname(__DIR__) . '/includes/owner_dashboard_model.php';
…
$context = requireOwnerPortfolioContext($database);
$data = ownerDashboardPageData($database, $context);
```

On a database error, `$data` stays `null` and the unavailable state renders.

## Template data: `ownerDashboardPageData()`

| Key | Type | Meaning |
|---|---|---|
| `owner_name` | string | Full name from the profile; `''` when not set. For the account menu. |
| `first_name` | string | First word of the name, for "مرحبًا، …". `''` when not set: show the greeting without a name. |
| `overview.total` | int | All of the owner's projects. |
| `overview.published` | int | Projects shown in the portfolio. |
| `overview.drafts` | int | Projects hidden from visitors. |
| `overview.needs_writing` | int | Projects where at least one of the three evidence parts (problem, role, outcome) is empty. |
| `overview.recent` | list, up to 3 | Newest first. Each: `id` (int), `title`, `category` (owner text, **escape it**), `published` (bool), `written_parts` (0–3). |
| `next_step` | string | The one suggestion for the "خطوتك التالية" banner, in priority order: `first_project` (no projects), `write_evidence`, `publish_drafts`, then `preview`. |
| `publication_state` | string | `no_slug`, `reserved`, `offline` or `published`. Same values and copy keys as the publication page. |
| `public_url` | ?string | The public portfolio address, only when `publication_state` is `published`; otherwise `null`. |

`written_parts` counts which parts contain text. It is **not** a quality score: only Evidence Hub judges quality, so the Overview must not present it as one.

## Destinations

| Action | URL |
|---|---|
| Add project | `/owner_projects.php?add=1`. Exactly **one** link on the page has the text "Add project" / "إضافة مشروع" (an acceptance test relies on it). |
| `first_project` banner | `/owner_projects.php?add=1`, labeled differently from "Add project" |
| `write_evidence` banner | `/owner/evidence-hub` |
| `publish_drafts` banner | `/owner_projects.php?lang=…` |
| `preview` banner | `/owner_preview.php` |
| Edit a recent project | `/owner_projects.php?edit={id}&lang=…` |
| Publication settings | `/owner_publication.php?lang=…` |

The heading must keep the selector `.dashboard-header .dashboard-heading > h1 > bdi` for the first name; the onboarding browser test reads it.

## Not part of this contract

Unread message counts (F-02) and the settings and help pages. Each has its own contract when its backend exists; until then, nothing for them appears.
