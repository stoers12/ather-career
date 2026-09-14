# EVIDENCE-HUB-0 LITE

Evidence Hub (Arabic UI descriptors: مركز الأدلة / تحليلات مسارك) is a private, Owner-only future capability. This package defines contracts and synthetic fixtures only. It adds no route, UI, database change, analytics runtime, integration, or job.

## Repository data inventory

| Required fact | Source inspected | Classification | Contract treatment |
| --- | --- | --- | --- |
| Internal Owner identity | `users.id`, validated session, `requireAuthenticatedUser()` | EXISTS_DIRECTLY | Derive tenant scope only from the validated internal User. |
| Portfolio ownership | `portfolios.owner_user_id`; `requireOwnedPortfolioContext()` | EXISTS_DIRECTLY | One authenticated User resolves one owned Portfolio. |
| Projects | `projects`, `listAuthorizedProjects()` | EXISTS_DIRECTLY | Query only with `portfolio_id = :authorized_portfolio_id`. |
| Description | `projects.description` | EXISTS_DIRECTLY | Kept as existing project description; never re-labelled as a problem statement. |
| Problem statement | no column or controlled field | NOT_AVAILABLE | Documentation metric is unavailable for this field pending a product/schema decision. |
| Personal role | no column or controlled field | NOT_AVAILABLE | Documentation metric is unavailable for this field pending a product/schema decision. |
| Measurable outcome | no column or controlled field | NOT_AVAILABLE | Documentation metric is unavailable for this field pending a product/schema decision. |
| Project technologies | `projects.technologies` JSON; `projectTechnologiesFromStorage()` | EXISTS_DIRECTLY | Preserve each stored raw label and map only through taxonomy v1 exact aliases. |
| Project publication state | no `projects.is_published` equivalent | NOT_AVAILABLE | Do not infer it from portfolio publication. |
| Portfolio publication state | `portfolios.is_published`, `published_at` | EXISTS_DIRECTLY | May describe portfolio visibility, not project-level publication. |
| Project created timestamp | `projects.created_at` | EXISTS_DIRECTLY | May order current evidence. |
| Project updated timestamp | no `projects.updated_at` | NOT_AVAILABLE | No change-based history or trend claim. |
| Skill normalization | `normalizeProjectTechnologies()` (trim, UTF-8, bounded, case-insensitive de-duplication) | EXISTS_WITH_DIFFERENT_NAME | It is storage normalization, not taxonomy canonicalization. |
| Owner-scoped repositories | `portfolio_scoped_data.php` and `AuthorizedPortfolioContext` | EXISTS_DIRECTLY | Future repository must retain the same two-part resource + tenant predicate. |

`projects.description` is not safely derivable into problem, role, or outcome. Adding those fields would require a product decision and migration. Existing title/category/description/technologies remain untouched.

## Metric contracts

### Project Documentation Coverage

An eligible project is a project returned by the authenticated Owner's tenant-scoped repository. Eligibility does not imply project publication. The intended evidence fields are `problem`, `personal_role`, and `measurable_outcome`; all three are currently unavailable, so the current repository-facing metric is `unavailable`, confidence `unavailable`, with `FIELD_NOT_AVAILABLE` reason codes. It must not pretend that `description` supplies any of them.

Gate-A candidate defaults (product calibration inputs, not standards):

| Field | Maximum graphemes | Complete at | Useful tokens | Distinct tokens |
| --- | ---: | ---: | ---: | ---: |
| problem | 2000 | 80 | 10 | 6 |
| personal_role | 1500 | 50 | 7 | 5 |
| measurable_outcome | 1000 | 40 | 6 | 4 |

Future EVIDENCE-HUB-1A rule functions must apply Unicode NFC before validation; count grapheme clusters (not bytes/code points); define useful tokens as Unicode letter/number runs after case folding with one-character punctuation-only runs excluded; and count distinct useful tokens after the same normalization. Storage validity is separate from evidence completeness. Hard invalid input is non-scalar input, invalid UTF-8, empty NFC value, prohibited C0/C1 controls (except normalized line breaks where storage permits them), maximum violation, or a confirmed exact placeholder. A hard-invalid value must not be used as evidence.

Confirmed placeholders are exact normalized values in the published fixture list (including `N/A`, `TBD`, `TODO`, and `Lorem ipsum`); partial matches require review, never automatic rejection. `GRAPHEME_THRESHOLD_NOT_MET`, `LOW_TOKEN_COUNT`, and `LOW_DISTINCT_TOKEN_COUNT` distinguish threshold failures. Suspicious repetition, low diversity, or low-information prose produces `NEEDS_ATTENTION`, normally without blocking normal draft storage. Stable reason codes are in the JSON schema. Coverage uses integer basis points: eligible complete evidence-field count / eligible required evidence-field count × 10000, rounded half up. If the denominator is zero, coverage is `0` BPS, confidence is `unavailable`, and `NO_ELIGIBLE_EVIDENCE_FIELDS` is emitted. Confidence expresses only evidence sufficiency: unavailable (no usable basis), low (one usable field/project), medium (partial field coverage across evidence), high (all required field evidence sufficient). It never estimates competence.

### Technology Evidence Map

`contracts/evidence-hub-taxonomy-v1.json` is the versioned v1 canonical taxonomy. A mapping preserves `raw_label` and reports `taxonomy_version`, `technology_id`, `canonical_key`, category, and state. Mapping is exact after NFC only; there is no fuzzy matching. An unknown label is `unmapped`; an ambiguous or policy-held label is `manual_review`; deprecated/merged entries remain traceable with `replaced_by_id`.

Collision guards are normative: `Java` never maps to JavaScript; `JS` maps to JavaScript only through the explicit `JS` alias; `C`, `C++`, and `C#` are distinct; React and React Native are distinct unless an explicit alias says otherwise; SQL Server never collapses to SQL.

### Portfolio Progress

This is an evidence-state classifier, not a universal score. `no_projects` applies when the tenant-scoped project list is empty. `incomplete_first_project` applies to one project with unavailable or insufficient documentation evidence. `single_project` applies to one project with sufficient available current evidence. `emerging_patterns` requires at least two current projects with available mapping/documentation evidence. `trend_ready` additionally requires at least three projects with distinct valid `created_at` timestamps spanning at least 90 days and sufficient present evidence; it is readiness to inspect a trend, not a claim that a trend exists. There are no historical snapshots or milestones today, so no improvement/decline claim is permitted.

## Recommendation lifecycle

Eligibility is a pure predicate of normalized relevant current evidence and rule version. A recommendation contains `recommendation_key`, `rule_version`, an SHA-256 `evidence_fingerprint`, reason codes, priority, allow-listed target, and nullable `snoozed_until`. The fingerprint includes only normalized evidence used by that rule plus `rule_version`; it never contains raw project text and must never be logged with raw project text.

Lifecycle states are `active`, `snoozed`, `dismissed`, `resolved`, and `superseded`. Only the future user dispositions `snoozed` and `dismissed` may be persisted. Pending and completed are derived views, never independent truth. Any relevant normalized evidence or rule-version change supersedes the old disposition and recomputes eligibility.

## Cold start

`zero`: no usable project evidence; show one constructive first action and no weakness language or fabricated metric. `partial`: show only available evidence, label unavailable evidence, and offer completion actions. `ready`: all three metric families may be shown inside their evidence limits; never imply verified professional ability. EVIDENCE-HUB-1B UI is deferred.

## Tenant-isolation threat model

Required future flow: **Verified Owner Session → Internal User ID → Tenant Scope → Tenant-Scoped Repository → Pure Analytics Core → Contract Mapper → Owner Presenter**.

The controller must reject/ignore authority from query/body `owner_id`, body/query `portfolio_id`, request Auth0 subject, forwarded headers, and inbound request IDs. A project resource ID is usable only in a query additionally constrained by the authenticated Owner's resolved Portfolio. The private response uses `Cache-Control: no-store`; no public analytics, weaknesses, rankings, or employment decisions are permitted.

Mandatory synthetic crossing tests: Owner A requesting Owner B's project; Owner A supplying Owner B `owner_id`; Owner A supplying Owner B `portfolio_id`; request Auth0 subject differing from session-derived internal User; forwarded/request IDs attempting scope selection. Each must deny or ignore the supplied authority and return no Owner B evidence.

## Gate A, deferrals, and traceability

Gate A is **ready for human review**, not passed: product must decide whether to add the three explicit evidence fields, validate thresholds with synthetic representative samples, approve exact placeholder vocabulary, and approve taxonomy stewardship/versioning.

Deferred: EVIDENCE-HUB-1A CORE, EVIDENCE-HUB-1B UI, `/owner/evidence-hub`, Analytics Core, migrations, snapshots, GitHub, AI/NLP/embeddings, external APIs, cron/schedulers/queues/workers/event buses, and all public analytics.

| Metric contract | Future rule function | Fixture IDs | Reason codes | JSON field | Future UI |
| --- | --- | --- | --- | --- | --- |
| Documentation Coverage | `evaluateDocumentationFieldEvidence` | `DOC-*` | `FIELD_*`, `TEXT_*`, `PLACEHOLDER_CONFIRMED` | `metrics.project_documentation_coverage` | coverage card |
| Technology Evidence Map | `mapTechnologyEvidenceLabel` | `TECH-*` | `TECH_*` | `metrics.technology_evidence_map` | technology map |
| Portfolio Progress | `classifyPortfolioProgress` | `PROGRESS-*` | `HISTORY_*`, `NO_PROJECTS` | `metrics.portfolio_progress` | progress state |
| Recommendations | `isRecommendationEligible` | `REC-*` | rule-specific | `recommendations` | action list |
| Tenant boundary | `loadEvidenceHubForAuthorizedOwner` | `TENANT-*` | `TENANT_AUTHORITY_REJECTED` | no cross-tenant field | private presenter |
