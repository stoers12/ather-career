# AGENTS.md — working on Ather Career

For a human introduction and local setup, start with [README.md](README.md).

Guidance for coding agents (Codex, Claude Code, …) and human contributors. Read it fully before changing code. When this file and a task prompt disagree, stop and ask; do not guess.

## Project in one paragraph

A PHP 8.3 / Apache / MySQL 8.4 multi-tenant portfolio platform. Owners sign in with Auth0 (OIDC) and manage a private workspace; visitors see published portfolios. **Evidence Hub** is the Owner-private analytics page. It is deterministic, with no AI and no ranking of people. It separates claims from evidence: project documentation coverage, a technology evidence map, progress, and at most three recommendations.

## Layout

- `*.php` at the root are thin routes: authentication, CSRF, request shape, then a call into `includes/`. Keep routes thin.
- `includes/` holds the domain logic as procedural functions with `declare(strict_types=1)`.
  - Pure functions stay pure: no database access, no `$_GET`/`$_POST`/`$_SESSION`.
  - Repositories take `PDO` + `AuthorizedPortfolioContext`.
- `contracts/` holds versioned JSON: `evidence-hub-contract-vN.schema.json` and `evidence-hub-taxonomy-vN.json`.
- `database/migrations/NNN_name.sql` is applied in order by `database/migrate.php`. Every additive table gets a `database/rollback/NNN_name.sql` companion.
- `tests/phase2/cases/*Test.php` are unit and static tests, registered in `scripts/run-phase2-tests.php`. `tests/phase2/support/` holds fixtures, SQLite schemas and the Playwright `*.cjs` browser gates.
- `scripts/` keeps the Phase 2, required-gates and production-security entry points at its root; see the [scripts layout](scripts/README.md) for `test/`, `ci/`, `ops/`, `tools/` and the approved `archive/`.
- `docs/EVIDENCE_HUB_0_LITE.md` is the authoritative technical description of Evidence Hub. Update it when its behavior changes.

## Workflow (mandatory)

1. **Worktree per task.** Create a new worktree from current `origin/main` under `../ather-career-worktrees/<task>`, on branch `feat/…`, `fix/…`, `test/…` or `docs/…`.
   - Never commit in the Owner's original checkout `portfolio-project/`; it has unrelated local changes.
   - Never move or delete other worktrees.
2. **Smallest correct change.** Read the code you change and reuse existing helpers.
   - Match the surrounding style: naming prefix (`evidenceHub…`), docblocks with array shapes, short comments that explain *why*.
3. **Tests for every behavior change.** Unit tests are not enough for HTTP or UI behavior; extend the existing HTTP check or browser gate.
4. **Run the tests** (see below) and make them pass. Do not weaken, skip or delete an assertion to get green.
   - If an old test now contradicts the task, explain why in the PR, and change only what the task requires.
5. **Commits** use conventional style (`feat(evidence-hub): …`, `test(evidence-hub): …`), split by logical change. The body explains why.
6. **Open a Draft PR** whose description contains the verification table. **Never merge, deploy, run migrations against a live database, or change containers of the running stack.** The Owner reviews and merges.

## Running tests

Host PHP lacks `ext-intl`, so run PHP tests inside an application image. Use the newest local tag from `docker image ls ather-career-local`:

```powershell
docker run --rm --entrypoint php -v "<worktree>:/var/www/html:ro" -w /var/www/html `
  -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 ather-career-local:<tag> scripts/run-phase2-tests.php
```

There are three levels of check:

| Level | Command | Expected result |
|---|---|---|
| Fast loop, run after every change | the Phase 2 command above | `Phase 2 test foundation passed.` |
| Gate registration, when you touch gate wiring | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -RegistrationChallenge` | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates, before the PR (about 10 minutes) | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1` | `REQUIRED_GATES_PASSED` |

The full gates use disposable MySQL, Apache with two synthetic Owners, the HTTP checks, Playwright 1.58.2 with Chromium 1208, and the production security check.

Additional checks:
- `composer analyse` (in the PHP image, after `composer install`) for PHP changes; new code must not add baseline entries.
- `node --check <file>` for any edited JavaScript.
- `php -l` (inside the image) for new PHP files.

## Evidence Hub invariants — do not break

**Rules and contracts**
- The text evidence rules are frozen: `EVIDENCE_TEXT_RULE_VERSION = '1.0.0'`.
  - Contract schemas pin it and enumerate every reason code.
  - Changing a threshold, a placeholder or a reason code is a new rule version, which needs explicit approval.
  - Presentation aids (`includes/evidence_writing_guidance.php`) never feed the rules.
- Contracts v1…vN are frozen byte-for-byte.
  - A change is a **new** contract version: one row in `EVIDENCE_HUB_CONTRACT_VERSIONS` (`includes/evidence_hub_contract_mapper.php`), plus a new `contracts/evidence-hub-contract-vN.schema.json` derived from the previous one.
  - The single live selection is `EVIDENCE_HUB_OWNER_CONTRACT_VERSION` (`includes/evidence_hub_owner_core.php`). Never hard-code a live version anywhere else.
- Taxonomies are append-only.
  - A new version keeps every previous entry first and unchanged, in order.
  - Matching is normalized exact (NFC, whitespace, Latin case fold), never fuzzy. No alias may normalize to two entries.
  - The categories are fixed to the v2+ list. The database CHECK in migration 024 depends on them, so a new category needs a migration and approval.
- Recommendation rules have a fixed priority, `EVIDENCE_HUB_RECOMMENDATION_RULE_VERSION = '1.0.0'`, and at most 3 visible recommendations.
  - `recommendation_key` excludes the rule version by design.

**Security and tenancy**
- A recommendation `target_ref` and `recommendation_key` are **never rendered**; the keys derive from those references.
  - A form needing an opaque reference uses its own domain-separated HMAC, like `evidenceHubTechnologyReviewRef()`.
- Tenancy: every query is scoped through `AuthorizedPortfolioContext`, joining `portfolios.owner_user_id`.
  - A foreign, stale or forged target → 409, or 404 for resources. Never trust IDs from the request.
- POST actions follow this sequence: CSRF (`requireValidCsrfToken`), then the exact allowed field set, then a transaction (`runDatabaseTransaction`), then PRG (303).
- Owner-authored text is always escaped with `ownerEscapeHtml`. Isolate labels with `<bdi dir="auto">`.

**Copy and language**
- All Evidence Hub UI copy goes through `evidenceHubText($english, $locale)`, with the Arabic translation in `includes/evidence_hub_copy.php`.
  - `EvidenceHubLocalizationTest` must stay green: no missing Arabic, and placeholders `{x}` preserved.
- The locale is always an explicit parameter. Anything other than `'ar'` renders English.
  - Visitor-facing output must always be English. Never read the Owner's workspace preference implicitly.

**UI**
- Every control works without JavaScript; JavaScript only enhances.
- The layout works at 390px and 1280px with no horizontal overflow, and in RTL.
- `admin.css` forces button display. Hidden controls need an explicit `[hidden] { display: none !important; }` rule, following the existing pattern.

## Things to never do

- Never commit secrets, `.env*`, database dumps, or files from `ather-career-releases/`.
- Never print or log credentials, session cookies, HMAC keys or Owner data.
- Never add network fetches or new runtime dependencies without approval.
