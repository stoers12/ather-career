# EVIDENCE-HUB-0C — Gate-A calibration freeze

Evidence Hub (مركز الأدلة / تحليلات مسارك) is a private future Owner capability. EVIDENCE-HUB-1A adds only private project evidence storage and a deterministic internal text evaluator; it adds no route, UI, Owner aggregation, recommendation, integration, worker, job, or dependency.

## Repository inventory and approved gaps

| Fact | Source | Status | Contract treatment |
| --- | --- | --- | --- |
| Owner identity and tenant | validated `users.id`, `requireOwnedPortfolioContext()` | exists | Every future read is scoped to the authenticated internal Owner. |
| Projects | `projects`, `portfolio_id`, `created_at` | exists | Analyze all authenticated Owner projects, public or private. |
| Description | `projects.description` | exists | Never derive evidence fields from it. |
| Technologies | `projects.technologies` JSON | exists | Preserve raw labels and use normalized exact taxonomy matching. |
| `problem_statement`, `personal_role`, `measurable_outcome` | nullable `projects` fields | exists | Optional independently authored storage; no backfill or semantic derivation. |
| Project publication / `projects.updated_at` | no fields | deferred | Not needed for v1. |
| Portfolio publication | `portfolios.is_published`, `published_at` | exists | A separate factual signal only. |

Resident development accounts are not calibration data. The Momen Portfolio is protected showcase data: it is never opened, copied, modified, reset, deleted, calibrated, or used by automated tests.

## Fixture scopes

`TEXT-*` evaluates one analytical field. `DOC-METRIC-*` aggregates evidence fields for one or more projects. `HUB-STATE-*` evaluates top-level data readiness. `TECH-*`, `PROGRESS-*`, `REC-*`, and `TENANT-*` evaluate only their named contract. `PAYLOAD-*` and `NEG-*` are schema-shaped payloads. A passing field never makes the full Hub response ready.

## Frozen text evidence rule v1.0.0

These are Evidence Hub v1 product defaults, not scientific quality scores. Passing means there is sufficient structured evidence for Evidence Hub v1; it does not prove truth, competence, impact, or employment suitability. Any future change requires an explicit new rule version and fixture recalibration.

| Field | Storage maximum Unicode scalars | Content graphemes | Useful tokens | Distinct tokens |
| --- | ---: | ---: | ---: | ---: |
| `problem_statement` | 2000 | 60 | 8 | 5 |
| `personal_role` | 1500 | 30 | 5 | 4 |
| `measurable_outcome` | 1000 | 20 | 4 | 3 |

Storage validity and evidence completeness are separate. Non-scalar input, invalid UTF-8, a scalar maximum violation, or a prohibited character makes storage invalid. Null or normalized-empty input is valid but unavailable evidence. Grapheme, token, distinct-token, placeholder, and repetition checks determine evidence completeness only; they never reject otherwise valid storage.

The EVIDENCE-HUB-1A runtime applies this pipeline only to an analytical copy; stored user text remains unchanged:

1. Validate scalar type.
2. Validate UTF-8.
3. Enforce the field maximum in Unicode scalar values.
4. Reject prohibited controls and directionality characters.
5. Normalize to NFC with PHP `ext-intl` `Normalizer`; hand-written NFC normalization is prohibited.
6. Treat TAB, LF, and CR as permitted whitespace in the analytical copy.
7. Collapse Unicode whitespace to one ASCII space.
8. Trim leading/trailing normalized whitespace.
9. Remove Arabic Tatweel U+0640 and exclude allowed ZWNJ/ZWJ from analytical evidence.
10. Apply safe Latin-only case folding where comparison is relevant, then calculate content graphemes and tokens.

PHP `ext-intl` is a mandatory EVIDENCE-HUB-1A runtime prerequisite. Development, production, and derived test images install it; the evaluator fails closed with a clear runtime-readiness error if `Normalizer` or grapheme support is absent. Hand-written NFC normalization is prohibited.

### Unicode and control policy

TAB U+0009, LF U+000A, and CR U+000D are allowed in stored multiline input and normalize as whitespace in the analytical copy. All other C0 controls U+0000–U+001F, plus C1 controls U+007F–U+009F, are rejected. Reject U+200B, U+2060, U+FEFF when inside input, U+202A–U+202E, and U+2066–U+2069. U+200C ZWNJ and U+200D ZWJ are allowed in stored text but contribute neither grapheme nor token evidence.

A content grapheme is a Unicode extended grapheme cluster containing at least one letter or number after analytical normalization. Whitespace, punctuation, symbols, emoji, combining marks without a base, ZWJ/ZWNJ, and Tatweel do not contribute independently.

A useful token begins with a Unicode letter or number and may continue with Unicode letters, numbers, or combining marks. Its comparison key is: normalized token → canonical decomposition → remove combining marks for comparison only → Unicode case fold → NFC. Thus optional Arabic diacritics share one token identity without changing stored text. Arabic and Latin digits both count as numeric content; v1 does not convert one digit script to the other or claim numerical equivalence.

### Placeholders and repetition

The exact complete-value placeholder vocabulary is: `lorem ipsum`, `lorem ipsum dolor sit amet`, `todo`, `tbd`, `coming soon`, `test`, `placeholder`, `n/a`, `لاحقاً`, `لاحقا`, `قريباً`, `قريبا`, `تجريبي`, `اختبار`, `غير متوفر`. Detection runs after NFC, whitespace normalization, and Unicode case folding. It never uses substring or semantic matching. It is valid storage but incomplete evidence with `PLACEHOLDER_CONFIRMED`.

`REPETITION_SUSPECTED` is independent of field thresholds. It applies when `useful_token_count >= 5` and `dominant_token_count × 10000 >= useful_token_count × 6000`. Low token and distinct-token counts remain separate reasons: `USEFUL_TOKEN_THRESHOLD_NOT_MET` and `DISTINCT_TOKEN_THRESHOLD_NOT_MET`.

Long, diverse, syntactically valid nonsense may pass deterministic completeness checks. This accepted v1 risk is limited to a private, non-ranking, non-employment-decisional feature and does not claim truth verification. AI, NLP, semantic scoring, and hidden heuristics remain out of scope.

## Documentation Coverage and maturity

Every eligible Owner project has three equally weighted expected evidence fields. Coverage is `complete evidence fields / expected evidence fields × 10000`, rounded half up: `floor((complete / expected) × 10000 + 0.5)`. Examples: 0/3 = 0, 1/3 = 3333, 2/3 = 6667, 3/3 = 10000, and 4/6 = 6667 BPS. With no projects, status is `unavailable`, `coverage_bps` is `null`, and `NO_PROJECTS` is present.

Top-level data readiness is not competence: `zero` means no Owner projects; `partial` means projects exist but none has all three fields complete; `ready` means at least one project has all three complete fields.

## Technology Evidence Map

Taxonomy v1 has 17 bounded canonical entries. Alias matching is deterministic normalized exact matching: NFC, trimming, internal whitespace normalization, then Latin case folding. It is not fuzzy matching. Unknown non-empty labels are `unmapped` with `canonical_id: null`. `manual_review` is not a v1 state. jQuery is a distinct non-deprecated `library`; it is not merged into JavaScript. Java/JavaScript, C/C++/C#, React/React Native, and SQL/SQL Server remain distinct.

## Portfolio Progress

Portfolio Progress exposes Owner-only facts: project count, projects with complete evidence, first/latest project timestamps, recorded activity span, and Portfolio publication state. It always reports `trend_status: not_available`, `trend_direction: null`, and `HISTORY_NOT_TRACKED`; project creation dates never prove professional improvement or a trend.

## Recommendations

At most three recommendations are visible. Rules order by stable priority, then `rule_id`, then opaque target reference: add first project; complete missing project evidence; correct/review unmapped technology; complete Portfolio publication setup when eligible. Actions are allow-listed and each names the predicate it is intended to resolve.

`active` means predicate true with no suppressing disposition; `snoozed` applies until expiry; `dismissed` applies for the same fingerprint/rule version; `resolved` means predicate false; `superseded` means fingerprint or rule version changed. Only snoozed and dismissed are future persisted dispositions.

The fingerprint is SHA-256 of canonical UTF-8 JSON containing only `rule_id`, `rule_version`, `target_type`, opaque `target_ref`, and allow-listed predicate facts. Canonicalization recursively sorts object keys lexicographically, preserves stable array order, uses integers/booleans/null where possible, and forbids floating-point values. It never contains raw evidence text, raw technology labels, email, Auth0 subjects, cookies, tokens, filesystem paths, showcase identity, or client-visible raw database IDs.

| Rule | Allow-listed predicate facts |
| --- | --- |
| `add_first_project` | `has_projects` |
| `complete_project_evidence` | `target_ref`, field completeness states, stable reason codes |
| `review_unmapped_technology` | `target_ref`, digest of normalized unmapped label, `mapping_state` |
| `complete_portfolio_publication` | `has_projects`, `portfolio_published`, `publication_prerequisites_met` |

The future pure core receives opaque `target_ref` from an Owner-scoped adapter; this package neither designs nor implements that adapter.

## Tenant isolation

Required flow: **Verified Owner Session → Internal User ID → Tenant Scope → Tenant-Scoped Repository → Pure Analytics Core → Contract Mapper → Owner Presenter**. Controllers never accept tenant authority from request `owner_id`, `portfolio_id`, `user_id`, `authz_version`, Auth0 subject, headers, or request IDs. Project resources require the resolved authenticated Owner scope. Future private responses use `Cache-Control: no-store` and never expose analytics publicly.

## EVIDENCE-HUB-1A internal core boundary

`includes/evidence_text_policy.php` is the runtime policy source for frozen v1 thresholds and placeholders. `includes/evidence_text_normalization.php` validates and prepares an analytical copy without changing stored text. `includes/evidence_text_evaluator.php` returns field-local validity, completeness facts, and stable reason codes. These pure functions accept only a field and candidate value; they accept no Owner, Portfolio, session, Auth0, or request authority.

Migration `010_project_evidence_fields.sql` adds nullable `TEXT` columns, with no default backfill. The companion rollback SQL is used only for task-owned disposable migration verification because applying column drops to populated storage would be destructive. Internal `createAuthorizedProject()` and `updateAuthorizedProject()` accept an optional allow-listed evidence map; omitted fields retain existing project behavior, and public Project JSON remains an explicit non-disclosing allow-list.

The core intentionally does not aggregate an Owner response, expose a route, read public project output, implement recommendations, or create a persistence adapter for dispositions.

## Traceability

| Metric/rule | Source/future field | Normalization/calculation | Reasons | Fixtures | JSON field | Future UI state |
| --- | --- | --- | --- | --- | --- | --- |
| Field evidence v1.0.0 | future evidence fields | frozen analytical pipeline, graphemes, token keys, repetition | text reasons | `TEXT-*` | `$defs.field_evaluation` | field action |
| Documentation Coverage | three future fields per Owner project | complete/expected × 10000, half up | `NO_PROJECTS` and text reasons | `DOC-METRIC-*` | metrics.documentation_coverage | coverage card |
| Hub maturity | Owner project aggregation | zero/partial/ready predicates | `NO_PROJECTS` | `HUB-STATE-*` | maturity.state | constructive state |
| Technology map | `projects.technologies` | normalized exact alias | `TECHNOLOGY_*` | `TECH-*` | metrics.technology_evidence_map | technology action |
| Portfolio Progress | projects/created_at/Portfolio publication | factual-only; trend unavailable | `HISTORY_NOT_TRACKED` | `PROGRESS-*` | metrics.portfolio_progress | facts-only state |
| Recommendations | allow-listed canonical predicate facts | predicate/fingerprint/order | lifecycle reasons | `REC-*` | recommendations | action list |
| Tenant denial | validated Owner scope | reject request authority | `TENANT_AUTHORITY_REJECTED` | `TENANT-*` | none | private denial |

EVIDENCE-HUB-1B UI, Owner aggregation, recommendations/dispositions, snapshots, AI, external services, queues, and public analytics remain deferred.
