# Contract: evidence sources

Backend: Claude (B-06). Frontend: Codex, slice FE-07 (Evidence Hub, special track). Rule 2 of [ENGINEERING_GUIDELINES.md](../ENGINEERING_GUIDELINES.md): neither side changes this contract alone.

A source is a link an owner adds to support a project's evidence: a report, a demo, a specific contribution or a repository they may share. It is the reference's fourth evidence part, «المصادر» / "Sources". Owner decisions (2026-10-30):
- Sources feed the Evidence Hub analysis through contract **v10**.
- A project may have **at most 10** sources.

## Privacy

Sources are **private to the owner**. No public read touches them: not the portfolio page, not `projects.json`, not the case study page, and not the owner preview. A unit test enforces this. The contract carries **counts only**, never a title or URL.

## Storage

Table `project_evidence_sources` (migration `027_project_evidence_sources`):

| Column | Rule |
|---|---|
| `id`, `portfolio_id`, `project_id` | `portfolio_id` is copied from the authorized project row. |
| `title` | Required, trimmed, 1–120 characters. |
| `url` | Required, trimmed, at most 500 characters, `http(s)` with a host, never a local address. |
| `note` | Optional, trimmed, at most 300 characters; empty means `NULL`. |
| `created_at` | |

**Integrity:**
- Deleting a project deletes its sources.
- MySQL `CHECK` constraints back the title, URL and note rules.
- The application enforces the 10-source limit under a lock on the project row.

## Owner actions: `POST /owner/projects/{id}/evidence`

The existing evidence save (no `action` field) is unchanged. The source forms carry an `action`:

| `action` | Fields (exactly these) | Result |
|---|---|---|
| `add_source` | `csrf_token`, `action`, `title`, `url`, `note` | `303` to `…/evidence#sources`. Validation error or limit: `422`, re-rendering the typed values with field errors. |
| `remove_source` | `csrf_token`, `action`, `source_id` | `303` to `…/evidence#sources`. |

**Error answers, in the order they are checked:**
- `404` for another owner's project. This is checked before CSRF, as for the evidence save.
- `403` for a bad CSRF token.
- `422` for unexpected fields, an unknown action or a malformed `source_id`.
- `404` for a source that is not this project's.

**Validation messages:**
- **Field errors** are keyed `title`, `url` and `note`.
- **The limit error** is keyed `source_limit`: "This project already has 10 sources. Remove one before adding another."

## Template data

| Where | Data |
|---|---|
| **Editor** (`/owner/projects/{id}/evidence`) | `renderOwnerProjectEvidenceSources($projectId, $sources, $values, $errors, $locale)`. `$sources` is a list of `{id, title, url, note}` for this project in insertion order; `$values` echoes the add form; `$errors` is described above. |
| **Evidence Hub read view** | Each `$model['projects'][]` has `sources` (the same list) and `sources_url` (`/owner/projects/{id}/evidence#sources`). |

**Rules for the template:**
- **Escape** every title and note.
- **Links:** open them with `rel="noopener noreferrer"`. The stored URL was validated when it was added.
- **Label the list** "Sources you added · not checked or verified".
- **Warn** owners to add only links they may share, and never secrets or access tokens.
- **Hide the add form** once a project has 10 sources.
- **Copy:** Arabic and English copy goes through `evidenceHubText()`.

The baseline markup ships with this card; FE-07 replaces it with the reference design (`12-evidence-write.png`, part 4).

## Analysis: contract v10

- **Live contract:** `EVIDENCE_HUB_OWNER_CONTRACT_VERSION = 'v10'`.
- **Schema:** `contracts/evidence-hub-contract-v10.schema.json`. It equals v9 plus the additions below; `EvidenceHubContractV10Test` proves this.
- **Older versions:** v1–v9 and their definitions are unchanged, and they reject the new recommendation.

**`metrics.documentation_coverage` gains two fields:**
- `projects_with_sources`: projects with at least one source.
- `source_count`: total sources across the owner's projects.

**The existing coverage is unchanged.** `coverage_bps` and every other existing field still measure only the three written parts, so no current number moves.

**The new recommendation, `add_evidence_source`:**
- Its priority is 6, the lowest.
- Its reason code is `PROJECT_EVIDENCE_SOURCE_MISSING`.
- It targets a project whose three written parts are **complete** and that has **no source**, so it never overlaps `complete_project_evidence`.

**Recommendation identity:**
- `rule_version` stays `1.0.0`, so no stored dismissal is revived.
- Predicate facts are `{evidence_complete: true, source_count: 0}`, so adding a first source resolves the recommendation.
- The counts reach the recommendation core through an optional `project_sources` facts key. Without it, the historical candidate set is identical.

**Evidence Hub page:** the recommendation links to `/owner/projects/{id}/evidence#sources`.

## Release

It needs a database backup, then migration 027, then a `web` update. The migration is additive only, so the previous image ignores the new table and rollback stays possible.

## Not part of this contract

- Checking or fetching links.
- File uploads.
- Editing a source in place: remove it and add it again.
- Showing sources to visitors.
- Any AI suggestion.
