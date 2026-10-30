# Contract: live project URL

Backend: Claude (B-04). Frontend: Codex. The project editor field is slice FE-04; the public display is slice FE-08. Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

A project can have an optional link people can visit: a live demo, a report (for example Power BI) or any public page. It sits next to the existing source-code link (`github_url`). The reference calls it "رابط العرض" / "Live project URL".

## Storage

`projects.live_url VARCHAR(500) NULL` (migration `026_project_live_url`). Existing projects have no link.

## Project form: `owner_projects.php` (`action=add` / `action=update`, unchanged otherwise)

| Field | Rule |
|---|---|
| `live_url` | **Optional.** Trimmed, at most 500 characters, and only an `http://` or `https://` URL with a host. An empty value means "no link". |

**Behavior:**
- **Field absent from the POST:** an update **keeps** the stored link; an add stores none. Forms that don't have the field yet therefore never erase a link.
- **Field present and empty:** the link is removed.
- **Invalid value:** `422`; the form is re-rendered with `field_errors['live_url']` = "Please enter a valid HTTP or HTTPS URL." (or the length/type message), and nothing is saved.

**Template data:** `$editingProject['live_url']` is the value to show in the field. It holds:
- the stored link when editing;
- the submitted text after a validation error;
- `''` for a new project.

Each row from `listAuthorizedProjects()` and `findAuthorizedProject()` has `live_url` (`?string`).

**Field name in the markup:** `name="live_url"`, `type="url"`, `maxlength="500"`. Use the reference's label and hint.

## Public portfolio

- **Public JSON** (`/p/{slug}/projects.json`): `live_url` appears **only** when it is a valid http(s) URL of at most 500 characters. Otherwise the key is omitted, as with `github_url`.
- **Public HTML:**
  - `listPublicProjects()` rows include `live_url`.
  - Render it **only** through `portfolioPresentationProjectLink($project, 'live_url')`, which returns `null` for anything unsafe, including local addresses. Never echo the raw value.
  - Open it like the existing GitHub link: an external link with `rel="noopener noreferrer"`. Its visible label says it is a live demo, not code.
- **Only published projects of a published portfolio reach visitors.** This is unchanged.

## Not part of this contract

Link previews, screenshots, and checking that the link is reachable.
