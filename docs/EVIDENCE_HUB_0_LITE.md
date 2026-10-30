# EVIDENCE-HUB-0C — Gate-A calibration freeze

Evidence Hub (مركز الأدلة / تحليلات مسارك) is a private future Owner capability. EVIDENCE-HUB-1A adds private project evidence storage and a deterministic internal text evaluator. EVIDENCE-HUB-1B.1 adds only internal Owner-scoped aggregation hardening, factual progress semantics, and exact taxonomy mapping; it adds no route, UI, recommendation, integration, worker, job, or dependency.

## FE-07 private presentation (D1–D5, B-07)

The Owner route now has **Records** and **Your path insights**, in the separate Evidence workspace shell. Native anchors expose both layers without JavaScript; enhanced tabs switch locally without consuming immediate Undo with another request. Existing section and project fragments remain usable. Records retain the bounded keyboard carousel, three recommendation actions, real three-field quality states/reasons, source counts and client-only filters. Empty recommendations do not imply complete evidence. Insights retain coverage, confidence and its basis/sample detail, maturity, factual progress/publication counts, the technology map and all technology review forms. The complete `skill_gaps` list is supplied by B-07 independently of recommendations and dispositions; only display names are rendered. Credentials remains visibly unavailable.

The private record route honours [B-07 view modes](contracts/evidence-hub-views.md): canonical **Read** contains saved text and native source forms; **Write** contains exactly the existing three-field evidence form and no source actions. Its four-part stepper retains all fields in one form, and Sources explains saving first. Without scripts all four parts are stacked. A text rejection opens/focuses the first invalid part and retains unsaved status. Writing aids remain guidance only; quality badges show the last server assessment. A frontend departure warning protects edited text, including inserted starters. Text save still returns to the hub with immediate Undo; the saved card links to source management. Following another page ends the Undo opportunity as before. Source addition saves immediately and has no text-Undo claim.

The final reference palette is scoped to `body.evidence-hub-page`, with the existing appearance resolver and system-dark fallback when scripts are unavailable. Owner locale is explicit through `evidenceHubText()`. The unchanged privacy boundary and disclosure apply to every layer and record. No rule, mapper, schema, live switch, request handling, validation, authorization or database behavior changes in this slice. The approved capability mapping and test preservation plan remain in [COMPARISON.md](reviews/fe-07/COMPARISON.md); implementation verification and visual differences are recorded in [the review README](reviews/fe-07/README.md).

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

### Taxonomy v2

Taxonomy v2 and contract v2 remain independently loadable and byte-for-byte frozen, as do the original 17-entry taxonomy v1 and contract v1. Contract v2 changes the envelope identity to `evidence-hub-contract-v2` and schema version to `2.0.0`; the existing Evidence text, coverage, progress, and recommendation rules retain their v1 semantics and internal rule versions.

Taxonomy v2 retains all 17 v1 canonical entries and adds CSS (stylesheet), DAX (language), Git (tool), GitHub and Power BI (platform), Joblib, Matplotlib, NumPy, Pandas, Scikit-learn, and Seaborn (library), and Latent Semantic Analysis (LSA), Multiple Linear Regression (MLR), and TF-IDF (technique). Its explicit category order is database, framework, language, library, platform, runtime, stylesheet, technique, tool. Labels within each category sort by canonical display name and key.

Exact NFC, Unicode whitespace normalization, and Latin case folding still determine aliases. Reviewed aliases cover stored `Css`, `Dax Equation`, `Github`, `LSA`, and `Power bi` without fuzzy matching. Generic `Linear Regression` is intentionally not an alias for MLR: simple linear regression is a different possible future value. A comma-separated six-library label remains one unknown value until the owner splits it in the project editor. Unknown values retain null canonical fields and privacy-safe numbered Owner labels; they are never automatically split or displayed raw by the Hub. Per-project canonical deduplication and deterministic sorting remain in force.

### Taxonomy v3

The private Owner Evidence Hub now selects taxonomy v3 and contract v3 (`evidence-hub-contract-v3`, schema version `3.0.0`). V3 retains all 31 v2 entries and their category order, then adds FastAPI (framework), pgvector (database extension), SentenceTransformers (library), Redis (database), Celery (framework), and Groq (platform). Exact normalized aliases include `Fast API`, `pg vector`, and `Sentence Transformers`; case folding already covers capitalization. GitHub Actions remains outside this project technology taxonomy. Unknown values remain privacy-safe and combined labels are not split. Evidence, coverage, progress, and recommendation rules retain their existing semantics.

### Owner technology review and contract v5

The private Owner Evidence Hub now publishes contract v5 (`evidence-hub-contract-v5`, schema version `5.0.0`) over taxonomy v4. `EVIDENCE_HUB_CONTRACT_VERSIONS` in `includes/evidence_hub_contract_mapper.php` binds each contract to the taxonomy it publishes, and `EVIDENCE_HUB_OWNER_CONTRACT_VERSION` in `includes/evidence_hub_owner_core.php` is the single live selection. Contracts v1–v4 remain byte-for-byte frozen and reject the new state.

An Owner resolves an unmapped project label on the Evidence Hub page in one of three ways:

- **Fix the project.** Each unknown label is shown to its Owner exactly as written, with links to the projects that contain it. This is the path for a misspelling.
- **Map it to a current taxonomy entry.** The decision is stored per Portfolio in `technology_owner_mappings` (migration 023), keyed by the SHA-256 of the normalized label. The label text itself is never stored.
  - A taxonomy alias always wins over an Owner mapping.
  - A mapping whose entry is absent from the live taxonomy is ignored, and the label is unmapped again.
  - A mapped label is published as `owner_mapped`, carrying the canonical entry. The metric gains `owner_mapped_occurrence_count` and the reason code `TECHNOLOGY_OWNER_MAPPED`.
  - From v5, `mapped + owner_mapped + unmapped` occurrences must equal the technology occurrence count.
  - A canonical technology is one distinct technology, however it was recognized.
  - An `owner_mapped` label is no longer a `review_unmapped_technology` candidate. The rule version stays `1.0.0`, so existing dispositions and fingerprints keep their meaning.
- **Propose it.** The proposal goes into `technology_proposals` (migration 024): a label of at most 60 characters, one taxonomy category, and an optional note of at most 280 characters.
  - A generated pending-label column makes the database allow at most one pending proposal per label.
  - The Owner can withdraw a pending proposal.
  - Nothing accepts proposals yet. The queue is the interface for a future curator.

Forms address a label only through a review-only HMAC reference, bound to the Portfolio and resolved again against its current labels. It is domain-separated from recommendation target references, which are never rendered because recommendation keys derive from them. A foreign, stale or forged reference returns 409; a malformed request returns 422. Every action requires CSRF and uses PRG to `#technologies`. Mapping, unmapping and withdrawal are reversible. No matching is fuzzy, and nothing is shown to visitors.

### Taxonomy v5 and contract v6

Taxonomy v5 retains the 38 v4 entries first and unchanged, then adds 40 common technologies for 78 entries in the same category order:

- **Language (9):** HTML, Go, Rust, Kotlin, Swift, Dart, Ruby, R, Shell.
- **Framework (10):** Laravel, Vue.js, Angular, Django, Flask, Spring Boot, ASP.NET Core, Next.js, Express, Flutter.
- **Stylesheet (2):** Bootstrap, Tailwind CSS.
- **Database (3):** MongoDB, SQLite, MariaDB.
- **Library (4):** TensorFlow, PyTorch, OpenCV, Redux.
- **Runtime (1):** .NET.
- **Platform (5):** AWS, Microsoft Azure, Google Cloud, Firebase, Linux.
- **Tool (6):** Kubernetes, Postman, Figma, Jira, Nginx, Jupyter Notebook.

`EVIDENCE_HUB_OWNER_CONTRACT_VERSION` now selects contract v6 (`evidence-hub-contract-v6`, schema version `6.0.0`), which publishes taxonomy v5. Existing Owner mappings stay valid because v5 is a superset of v4. A taxonomy match takes precedence: an earlier Owner mapping of `Laravel` to PHP now resolves as `mapped` to Laravel; an unknown label such as `vue 3` can still be `owner_mapped` to Vue.js. Matching remains normalized exact. Text and recommendation rules, migrations, and UI are unchanged; earlier contracts and taxonomies remain frozen.

### Contract v7: evidence details

The live Owner selection is contract v7 (`evidence-hub-contract-v7`, schema version `7.0.0`), still using taxonomy v5. It adds exactly four fields:

- `portfolio_progress.stage` uses the pure stage rule with `trendReady = false`: `no_projects`, `incomplete_first_project`, `single_project`, or `emerging_patterns`. The contract keeps `trend_status: not_available` and never says `trend_ready`. For the same Portfolio, the page stage agrees except that its dated historical-context refinement, `trend_ready`, corresponds to the contract's `emerging_patterns`.
- `portfolio_progress.published_project_count` counts scoped project flags equal to 1, from 0 to `project_count`, independently of the Portfolio's visibility. Authorized builders load it through the ownership-scoped repository. A pure core can carry null when the count was not supplied, but v7 rejects null.
- `technology_evidence_map.mapping_coverage_bps` is the half-up percentage in basis points of mapped plus Owner-mapped occurrences out of all technology occurrences, or null with no occurrences. It uses the same integer rounding helper as documentation coverage. The Owner card displays the whole percent with `intdiv(bps, 100)`.
- `documentation_coverage.evidence_confidence_detail` sits beside the unchanged `evidence_confidence` string. Its `observations` equals project count, and `recommended_observations` uses the single `EVIDENCE_HUB_RECOMMENDED_OBSERVATIONS = 3` constant also used for page historical context. The Owner card explains the sample size when observations are positive and below that recommendation.

The detail's `basis_codes` are **not reason codes**. They are a closed, unique list sorted in lexicographic byte order (`SORT_STRING`): `NO_PROJECTS` for zero projects, `SINGLE_PROJECT` for one, `BELOW_RECOMMENDED_OBSERVATIONS` for two, plus `EVIDENCE_INCOMPLETE` for any positive project count with coverage below 10000. Thus one partially documented project produces `["EVIDENCE_INCOMPLETE", "SINGLE_PROJECT"]`; three fully documented projects produce an empty list. The mapper validates this exact sorted result. Existing reason codes, text and recommendation rules, taxonomies, and contract schemas v1–v6 remain frozen.

### Contract v8: skill evidence rule

The live Owner contract is v8. The private `add_skill_evidence` recommendation compares recognized Owner skills with technologies evidenced by any Owner project, whether published or unpublished. Both taxonomy matches and Owner mappings count. Unrecognized, empty, or unnormalizable skill labels are silent: the system cannot establish whether a project provides evidence for an unknown technology. One candidate is emitted per distinct canonical technology, regardless of how many skill labels map to it. Its priority is 5, after the four existing rules, and the three-card cap still applies.

The taxonomy remains frozen: `React.js` is a native alias of React, while `ReactJS` counts only through an explicit Owner mapping. A `ReactJS` skill without that mapping is unrecognized and silent.

The shared recommendation rule version remains `1.0.0`: adding a fifth rule does not change any existing rule. Bumping the shared version would alter existing fingerprints and stop stored snoozes and dismissals from suppressing the same recommendations. Canonical IDs, not raw skill labels, determine the private target and predicate facts; the Owner card displays the taxonomy's canonical name and links to add a project or review skills. Recommendation targets and action tokens remain tenant-scoped. Recommendation undo is outside this card's scope; snooze and dismissal retain the existing lifecycle.

### Text rules 1.1.0 (contract v9)

The live Owner contract is v9 and explicitly selects text rules `1.1.0`; the editor uses the same live version. Historical contracts v1–v8 select the callable, frozen `1.0.0` baseline. Field thresholds, placeholder vocabulary, normalization, and storage validity are unchanged. No migration is needed.

Character runs of at least three identical letter graphemes, compared after Latin case folding, count as one content grapheme. Tokens consisting entirely of such a run are excluded from useful tokens. The reported `content_graphemes` and `useful_token_count` are effective counts: the counts used by the thresholds. `CHARACTER_RUN_SUSPECTED` replaces a grapheme or useful-token threshold reason only when raw counts would pass that threshold. Genuine detail with one stretched word can still be complete. Digits such as `10000` are excluded from run discounting, and runs of two such as `coffee` or `keep` are untouched. Tatweel was already stripped analytically; punctuation and emoji runs already contributed no content letters or digits. Tests pin those existing behaviours.

`LOW_DISTINCT_TOKEN_RATIO` applies independently of dominant-token repetition when there are at least 12 useful tokens and `distinct × 10000 < useful × 4000`. All comparisons use integer math.

Inspired by the Gopher duplicate-content filter, project-local cross-field checking builds sets of word 3-grams using the existing token comparison keys after excluding run-only tokens. An otherwise complete field with at least three distinct shingles is flagged when at least 80% of its shingles occur in another field of the same project. Exact equality of non-empty comparison text is checked regardless of shingle count. Mutual duplicates flag only the later field in the order problem, role, outcome; a shorter field copied from a longer field is the one flagged. `DUPLICATED_ACROSS_FIELDS` changes only status and reason. Shingles, raw evidence, and the identity of the other field never enter facts, contracts, or logs.

The shared recommendation rule version remains `1.0.0`. New evidence reasons can intentionally change a project-completion predicate and its fingerprint, while unaffected keys and fingerprints remain stable. Existing snoozes and dismissals retain their meaning for unchanged predicates.

Calibration preceded runtime implementation. The selected defaults (4000 / 12 tokens and containment 0.80) produced zero natural-text false positives, caught all six new adversarial padding/rotation inputs, and matched all seven cross-field scenarios. Natural English and Arabic problem statements near the 2000-scalar limit remained complete. Existing synthetic letter-run boundary inputs are listed individually as **synthetic padding (intended)**; their `1.0.0` assertions remain unchanged, and their `1.1.0` catches are asserted separately. New run-free boundaries prove exact and one-below thresholds in both languages. The full per-fixture table, ratio sweep, and containment sweep are in [the calibration report](archive/EVIDENCE_HUB_TEXT_110_CALIBRATION.md). All examples are synthetic. Only Owner hints are added; visitor routes do not expose them.

## Portfolio Progress

The Owner page shows how many projects are public (`projects.is_published`). The five stages are computed by the pure `evidenceHubProgressStage()`.

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
