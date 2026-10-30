# Contract: project case study page

Backend: Claude (B-05). Frontend: Codex, slice FE-08 (public portfolio and preview). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

Each published project gets its own public page. A visitor opens it from a project card on the public portfolio. The reference calls it the case study page.

## Routes

| URL | Who | Shows |
|---|---|---|
| `/p/{slug}/projects/{id}` | Visitors | One **published** project of a **published** portfolio whose owner account is active and whose profile has a professional name. These are the same rules as `/p/{slug}`. |
| `/owner_preview.php?project={id}` | The signed-in owner | Any of the owner's own projects, **published or draft**. |

- **Methods:** `GET` and `HEAD`. Anything else gets `405`.
- **The visitor route never says why a page is hidden.** Every one of these gets the same HTML `404` ("Project not found."):
  - an unknown or malformed slug or id;
  - a draft project;
  - another portfolio's project id;
  - an unpublished portfolio or an inactive account.
- **The owner preview route:** a missing or foreign id gets the same `404`.
- **Database failure:** `503` on both routes.
- `{id}` is the project's existing numeric id, the one the public media route `/p/{slug}/media/project/{id}` already uses.

## Template data

**Public route:** the handler calls `renderPublicProjectPage($page)` with exactly `publicProjectPageData()`.

**Preview route:** the handler calls it with `ownerPreviewProjectPageData()`.

The array shape:

```php
[
    'preview'       => bool,     // true only on /owner_preview.php
    'draft'         => bool,     // true only in preview, for a project visitors cannot see yet
    'owner'         => ['name' => string, 'professional_title' => string],
    'project'       => [
        'id'           => int,
        'title'        => string,
        'category'     => string,        // may be ''
        'description'  => string,        // may be '' ; keep line breaks (nl2br)
        'technologies' => list<string>,  // may be []
        'live_url'     => ?string,       // contract project-live-url.md; null = no safe link
        'github_url'   => ?string,       // null = no safe link
        'image_url'    => ?string,       // scoped media route; null = no image
    ],
    'portfolio_url' => string,   // "/p/{slug}" or "/owner_preview.php"; add "#projects" for the back link
    'canonical_url' => ?string,  // absolute; null in preview, or if PUBLIC_BASE_URL is not configured
]
```

**Rules for the template:**
- **Escape every text value.** These values are written by the owner.
- **Links:** `live_url` and `github_url` are already checked; they are `http(s)` and never local addresses. Render them as they are, as external links with `rel="noopener noreferrer"`.
  - The live link is labelled as a live project or demo, not code.
  - When both are `null`, say that no public link has been added.
- **No owner contact details.** The page gets none and shows none. The contact form stays on the portfolio page.
- **Preview:** when `preview` is true, the page is `noindex,nofollow` and has no canonical link. When `draft` is true, it says visitors cannot see the project yet.
- **Language:** visitors always see English, so the page is `lang="en"`.
- **CSP:** no inline scripts or styles.

The handler ships a minimal baseline template (`includes/public_project_presentation.php`), which FE-08 replaces with the reference design. FE-08 also links each project card to its page:
- on the public portfolio, `/p/{slug}/projects/{id}`;
- in the owner preview, `/owner_preview.php?project={id}`.

## Not part of this contract

- **Private evidence.** It is never shown publicly; Evidence Hub data stays in the owner workspace.
- **Extra case study fields** (problem, role, outcome). The project editor has no such fields today; adding them would be a new backend card.
- **Next/previous project navigation.**
- **Readable URLs** (`/projects/hr-dashboard`). Ids are used, as the media route already does.
