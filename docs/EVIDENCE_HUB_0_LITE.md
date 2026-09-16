# EVIDENCE-HUB-0C — Gate-A calibration freeze

Evidence Hub (مركز الأدلة / تحليلات مسارك) is a private future Owner capability. EVIDENCE-HUB-1A adds private project evidence storage and a deterministic internal text evaluator. EVIDENCE-HUB-1B.1 adds only internal Owner-scoped aggregation hardening, factual progress semantics, and exact taxonomy mapping; it adds no route, UI, recommendation, integration, worker, job, or dependency.

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

Every eligible Owner project has exactly the three frozen evidence fields. The project collection itself must be an ordered list. Before aggregation, field keys and states are validated; `complete_evidence_fields` is derived from those states and must agree with the retained internal total and completion flag. Missing, extra, duplicate-shaped, unknown, numeric-string, negative, out-of-range, non-list, or inconsistent facts fail closed as an internal invariant error. Coverage is `complete evidence fields / expected evidence fields × 10000`, with integer half-up calculation `floor((complete × 10000 + floor(expected / 2)) / expected)`. Examples: 0/3 = 0, 1/3 = 3333, 2/3 = 6667, 3/3 = 10000, 4/6 = 6667, 1/6 = 1667, 2/9 = 2222, and 5/9 = 5556 BPS. With no projects, status is `unavailable`, `coverage_bps` is `null`, and `NO_PROJECTS` is present.

Top-level data readiness is not competence: `zero` means no Owner projects; `partial` means projects exist but none has all three fields complete; `ready` means at least one project has all three complete fields.

## Technology Evidence Map

Taxonomy v1 has 17 bounded canonical entries. Alias matching is deterministic normalized exact matching: NFC, trimming, internal whitespace normalization, then Latin case folding. It is not fuzzy matching. Unknown non-empty labels are `unmapped` with `canonical_id: null`. `manual_review` is not a v1 state. jQuery is a distinct non-deprecated `library`; it is not merged into JavaScript. Java/JavaScript, C/C++/C#, React/React Native, and SQL/SQL Server remain distinct.

Technology storage is private input only. Canonical empty storage is valid and yields no labels. A non-empty malformed value, non-list root, non-string or nested member, duplicate stored label, or existing writer-contract violation is `invalid` with `TECHNOLOGY_STORAGE_INVALID`; the project contributes no labels and processing continues for other projects. Raw malformed storage is never returned, logged, fingerprinted, or exposed.

An occurrence is one accepted non-empty label from one valid project collection before canonical grouping. `technology_occurrence_count = mapped_occurrence_count + unmapped_occurrence_count`. Canonical distinctness uses `canonical_id`; unmapped distinctness uses the normalized exact-comparison label. Thus aliases across projects remain separate occurrences but one distinct canonical technology; case/whitespace/NFC-equivalent unknown labels remain one distinct unmapped technology. The summary also records `invalid_technology_storage_project_count`. Mapping order is `mapping_state`, canonical ID, normalized label, then raw label; no database identifier participates.

## Portfolio Progress

Portfolio Progress exposes Owner-only facts: project count, projects with complete evidence, first/latest project timestamps, recorded activity span, and Portfolio publication state. Project time is an absolute instant: the repository supplies a Unix epoch derived from the production MySQL `TIMESTAMP` using `UNIX_TIMESTAMP`, never a timezone-less string relabeled as UTC. Output timestamps are canonical UTC `Z` values. `recorded_activity_span_days` is `floor((latest_epoch_seconds - first_epoch_seconds) / 86400)`: it is elapsed complete 24-hour periods, not UTC-calendar date difference. Same instant and a cross-midnight interval under 24 hours are 0; exactly 24 hours is 1; 47:59:59 is 1; exactly 48 hours is 2. Invalid/ambiguous instants fail closed. It always reports `trend_status: not_available`, `trend_direction: null`, and `HISTORY_NOT_TRACKED`; project creation dates never prove professional improvement or a trend.

## Recommendations

EVIDENCE-HUB-1C.0 freezes four future private rules only: `add_first_project` (rank 1), `complete_project_evidence` (rank 2), `review_unmapped_technology` (rank 3), and `complete_portfolio_publication` (rank 4). This is pre-endpoint contract clarification: no runtime, persistence, route, or UI exists. A visible response contains active items only; filtering precedes the three-item cap, then ordering is priority, rule ID, opaque target reference, and recommendation key, with dense display order.

`recommendation_key` is the lowercase SHA-256 of canonical UTF-8 JSON containing exactly rule ID, target type, and opaque target reference. It is stable across predicate and rule-version changes. `evidence_fingerprint` separately hashes exactly rule ID, rule version, target type, target reference, and allow-listed predicate facts; it changes with relevant facts or rule version. A changed fingerprint/version supersedes an old disposition and exposes the current active item. Snoozed and dismissed dispositions never consume visible slots.

Publication is eligible only for an Owner with projects, an unpublished Portfolio, and existing publication prerequisites: canonical non-reserved slug and a trimmed professional full name. It is independent of maturity, coverage, technology, competence, and quality. The future boundary is Owner Session → Internal User ID → Tenant Scope → Owner-scoped Adapter → Pure Recommendation Core → Contract Mapper → Owner Presenter; opaque targets select resources and never authorize access.

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

## Stable target-reference key

Evidence Hub opaque target references use the dedicated
`EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY` configuration value. The value must be
exactly 64 hexadecimal characters and decodes to 32 bytes; whitespace, padding,
fallbacks, per-request generation, and reuse of Auth0/session/database
credentials are forbidden. The configuration boundary fails closed without
including key material in exceptions, logs, serialized data, or responses.

The key is injected into the existing pure recommendation core; the core never
reads environment state. Rotating the key changes target references, so stored
dispositions no longer suppress the newly referenced eligible recommendations,
which intentionally makes them reappear. Rotation, migration, or dual-key
support requires separate Owner approval and is not implemented here.

## Tenant isolation

Required flow: **Verified Owner Session → Internal User ID → Tenant Scope → Tenant-Scoped Repository → Pure Analytics Core → Contract Mapper → Owner Presenter**. Controllers never accept tenant authority from request `owner_id`, `portfolio_id`, `user_id`, `authz_version`, Auth0 subject, headers, or request IDs. Project resources require the resolved authenticated Owner scope. Future private responses use `Cache-Control: no-store` and never expose analytics publicly.

## EVIDENCE-HUB-1A internal core boundary

`includes/evidence_text_policy.php` is the runtime policy source for frozen v1 thresholds and placeholders. `includes/evidence_text_normalization.php` validates and prepares an analytical copy without changing stored text. `includes/evidence_text_evaluator.php` returns field-local validity, completeness facts, and stable reason codes. These pure functions accept only a field and candidate value; they accept no Owner, Portfolio, session, Auth0, or request authority.

Migration `010_project_evidence_fields.sql` adds nullable `TEXT` columns, with no default backfill. The companion rollback SQL is used only for task-owned disposable migration verification because applying column drops to populated storage would be destructive. Internal `createAuthorizedProject()` and `updateAuthorizedProject()` accept an optional allow-listed evidence map; omitted fields retain existing project behavior, and public Project JSON remains an explicit non-disclosing allow-list.

The EVIDENCE-HUB-1B.1 internal core loads only Owner-scoped private project facts, reuses the frozen field evaluator, validates derived aggregation facts, calculates coverage/maturity/progress, and maps stored technology labels through the frozen taxonomy. Schema version remains `1.0.0`: this is a pre-endpoint clarification of internal/result facts, not a public compatibility break. It returns no HTTP response and has no public reader.

## Traceability

| Metric/rule | Source/future field | Normalization/calculation | Reasons | Fixtures | JSON field | Future UI state |
| --- | --- | --- | --- | --- | --- | --- |
| Field evidence v1.0.0 | future evidence fields | frozen analytical pipeline, graphemes, token keys, repetition | text reasons | `TEXT-*` | `$defs.field_evaluation` | field action |
| Documentation Coverage | three private evidence fields per Owner project | complete/expected × 10000, half up | `NO_PROJECTS` and text reasons | `DOC-METRIC-*` | metrics.documentation_coverage | coverage card |
| Hub maturity | Owner project aggregation | zero/partial/ready predicates | `NO_PROJECTS` | `HUB-STATE-*` | maturity.state | constructive state |
| Technology map | `projects.technologies` | validated collection, normalized exact alias, occurrence/distinct summary | `TECHNOLOGY_*` | `TECH-*` | metrics.technology_evidence_map | technology action |
| Portfolio Progress | projects/created_at/Portfolio publication | UTC absolute instants; complete elapsed 24-hour span; trend unavailable | `HISTORY_NOT_TRACKED` | `PROGRESS-*` | metrics.portfolio_progress | facts-only state |
| Recommendations | allow-listed canonical predicate facts | predicate/fingerprint/order | lifecycle reasons | `REC-*` | recommendations | action list |
| Tenant denial | validated Owner scope | reject request authority | `TENANT_AUTHORITY_REJECTED` | `TENANT-*` | none | private denial |

EVIDENCE-HUB-1B UI, recommendations/dispositions, snapshots, AI, external services, queues, and public analytics remain deferred.
# Evidence Hub Owner route

The private, read-only Evidence Hub is available at `/owner/evidence-hub`.
It derives its Owner and Portfolio scope solely from the validated server
session, produces the frozen contract server-side, and renders no client-side
recommendation calculations. The route accepts only GET and HEAD; there are
no action mutations in this release.

`EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY` remains required. Missing or invalid
configuration makes the route return a generic unavailable response without
revealing configuration data.
