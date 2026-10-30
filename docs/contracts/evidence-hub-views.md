# Contract: Evidence Hub views (B-07)

Backend: Claude (B-07). Frontend: Codex, FE-07 Phase 2. It delivers requests **B1** and **B2** from `docs/reviews/fe-07/COMPARISON.md`, which the owner approved on 2026-10-30 (decisions D1–D5). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

Nothing here changes a contract version, a request, a validation rule, a redirect, a status code, Undo, or authorization. Raw evidence text stays on the private project route.

## B1: view modes of `/owner/projects/{id}/evidence`

The handler computes one view and passes it to `renderOwnerProjectEvidencePage($project, $values, $fieldErrors, $sources, $sourceValues, $sourceErrors, $view, $locale)`:

```php
$view = ['mode' => 'read' | 'write', 'part' => null | 'problem' | 'personal_role' | 'measurable_outcome'];
```

| Request | View |
|---|---|
| `GET` (canonical, no query) | `read` |
| `GET ?mode=write` | `write`, `part` null |
| `GET ?mode=write&part={part}` | `write` at that part. An unknown or structured part gives null. |
| Any other `mode` value | `read` |
| Text save rejected (422) | `write` at the **first invalid part** in display order: problem, personal_role, measurable_outcome. `$values` holds the submitted text. |
| Source action rejected (422) | `read`. `$values` holds the **saved** text; `$sourceValues` / `$sourceErrors` hold the source draft. |
| Source action succeeded | `303` to `…/evidence#sources`, which lands in `read`. |
| Text save succeeded | Unchanged: `303` to `/owner/evidence-hub#project-{id}`, with Undo. |

**What FE-07 must render:**
- **`read`:**
  - the saved record (three parts, read-only, each with its server state and reason);
  - the **source list and the source forms**;
  - an "Edit" link per part to `?mode=write&part={part}`.
  - **No editable evidence text,** so no source action can discard unsaved text.
- **`write`:**
  - the **one** existing text form: exact fields `csrf_token`, `problem`, `personal_role`, `measurable_outcome`, and no `action`. It opens at `part`, or at the first part when `part` is null.
  - **No source forms.** The fourth step says to save the text first and then manage sources; its action submits the text form.
  - A "Read" or "Cancel" link back to the canonical route.

**Helpers** (`includes/project_evidence_view.php`):
- `projectEvidenceViewFromQuery($query)`;
- `projectEvidenceViewAfterTextError($fieldErrors)`;
- `projectEvidenceViewUrls($projectId)`, which returns `{read, write, sources}` = `…/evidence`, `…/evidence?mode=write`, `…/evidence#sources`.

**Baseline markup:** the shipped baseline renders both the form and the sources in both modes, exactly as before, and adds `data-evidence-view-mode` / `data-evidence-view-part`. FE-07 replaces it with the mode-honouring design above.

## B2: skills without project evidence

`buildAuthorizedEvidenceHubOwnerPageModel()` now adds:

```php
'skill_gaps' => list<array{canonical_id: string, display_name: string}>
```

**What the list contains:**
- **Every** claimed skill without project technology evidence. It is not limited to the three visible recommendations, and snooze and dismiss do not affect it.
- It uses exactly the facts behind `add_skill_evidence`: canonical, deduplicated, owner mappings applied, unknown skill labels excluded.
- It is sorted by display name.
- An empty list means **no gaps**. A database failure makes the whole page unavailable (503), as today, so there is no separate "unavailable" state.
- **Display names only:** no raw skill text, target identity, recommendation key or token.

**For the Insights layer:** show the list with its explanation and the real actions «Add project» (`/owner_projects.php?add=1`) and «Review skills» (`/owner_profile.php#skills`). The active `add_skill_evidence` recommendation stays in Records with its own snooze and dismiss.

## Also in the page model

- **New fields on each `projects[]` entry:**
  - `evidence_read_url` (`…/evidence`);
  - `evidence_write_url` (`…/evidence?mode=write`).
- **Unchanged:** `sources_url`.
- **Recommendation link:** the `complete_project_evidence` recommendation now links to `evidence_write_url`.

## Not part of this contract

- A project category (B3 was declined, D5).
- New contract fields.
- Exposing evidence text on the hub.
- Source editing in place.
