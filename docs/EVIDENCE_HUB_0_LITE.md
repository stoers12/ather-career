# EVIDENCE-HUB-0B — Gate-A contracts

Evidence Hub (مركز الأدلة / تحليلات مسارك) is a private future Owner capability. This package contains contracts and identity-free synthetic fixtures only. It adds no runtime, route, UI, migration, integration, worker, or job.

## Repository inventory and approved gaps

| Fact | Source | Status | Contract treatment |
| --- | --- | --- | --- |
| Owner identity and tenant | validated `users.id`, `requireOwnedPortfolioContext()` | exists | Scope every future read to the authenticated Owner's Portfolio. |
| Projects | `projects`, `portfolio_id`, `created_at` | exists | Analyze all authenticated Owner projects, whether the Portfolio is public or private. |
| Description | `projects.description` | exists | Never derive evidence fields from it. |
| Technologies | `projects.technologies` JSON | exists | Preserve raw labels and use normalized exact taxonomy matching. |
| `problem_statement`, `personal_role`, `measurable_outcome` | no fields | future fields | Later nullable optional fields; no backfill or semantic derivation. |
| Project publication / `projects.updated_at` | no fields | deferred | Not needed for v1. |
| Portfolio publication | `portfolios.is_published`, `published_at` | exists | A separate factual signal only. |

Resident development accounts are not calibration data. The Momen Portfolio is protected showcase data: it is never opened, modified, reset, deleted, calibrated, or used by automated tests.

## Fixture scopes

`TEXT-*` evaluates one future field only. `DOC-METRIC-*` aggregates evidence fields for one or more projects. `HUB-STATE-*` evaluates top-level Evidence Hub data readiness. `TECH-*`, `PROGRESS-*`, `REC-*`, and `TENANT-*` evaluate their named contract only. `PAYLOAD-*` and `NEG-*` are schema-shaped positive and negative payloads. A passing field never makes the complete Hub response ready.

## Text evidence contract

Canonical fields are `problem_statement`, `personal_role`, and `measurable_outcome`. Storage validity and evidence completeness are separate.

- Storage-invalid: non-scalar input, invalid UTF-8, a maximum storage-length violation, prohibited C0/C1 controls, or invisible zero-width format characters.
- Unavailable evidence: null or normalized-empty values. They are not malformed storage.
- Evidence-only checks: content graphemes, useful tokens, distinct useful tokens, exact placeholders, and deterministic repetition.

Future EVIDENCE-HUB-1A must apply NFC using PHP `ext-intl` `Normalizer`; this is a mandatory runtime prerequisite. It must not hand-write Unicode normalization. Matching normalizes NFC, trims, collapses internal whitespace, and applies Latin case normalization. Content graphemes count only grapheme clusters containing a Unicode letter or number; punctuation, whitespace, emoji, and symbols do not pad length. Useful tokens are Unicode letter/number runs. Distinct tokens use the same normalized token form.

Proposed (not frozen) thresholds:

| Field | Storage maximum | Content graphemes | Useful tokens | Distinct tokens |
| --- | ---: | ---: | ---: | ---: |
| problem_statement | 2000 | 80 | 10 | 6 |
| personal_role | 1500 | 50 | 7 | 5 |
| measurable_outcome | 1000 | 40 | 6 | 4 |

The exact placeholder vocabulary is: `lorem ipsum`, `lorem ipsum dolor sit amet`, `todo`, `tbd`, `coming soon`, `test`, `placeholder`, `n/a`, `لاحقاً`, `لاحقا`, `قريباً`, `قريبا`, `تجريبي`, `اختبار`, `غير متوفر`. It matches the complete normalized value only. It is valid storage but incomplete evidence. Repetition is suspicious when one normalized token represents at least 60% of useful tokens and there are fewer than the field's distinct-token minimum. It and low diversity produce `needs_attention`, never storage rejection.

Stable evidence reasons are `GRAPHEME_THRESHOLD_NOT_MET`, `USEFUL_TOKEN_THRESHOLD_NOT_MET`, `DISTINCT_TOKEN_THRESHOLD_NOT_MET`, `REPETITION_SUSPECTED`, `PLACEHOLDER_CONFIRMED`, and `TEXT_COMPLETE`.

## Documentation Coverage and maturity

Every eligible Owner project has three equally weighted expected evidence fields. Coverage is `complete evidence fields / expected evidence fields × 10000`, rounded half up. Examples: 0/3 = 0, 1/3 = 3333, 2/3 = 6667, 3/3 = 10000, and 4/6 = 6667 BPS. With no projects, status is `unavailable`, `coverage_bps` is `null`, and `NO_PROJECTS` is present.

Top-level data readiness is not competence: `zero` means no Owner projects; `partial` means projects exist but none has all three fields complete; `ready` means at least one project has all three complete fields.

## Technology Evidence Map

Taxonomy v1 has 17 bounded canonical entries. Alias matching is deterministic normalized exact matching: NFC, trimming, internal whitespace normalization, then Latin case normalization. It is not fuzzy matching. Unknown non-empty labels are `unmapped` with `canonical_id: null`. `manual_review` is not a v1 state. jQuery is a distinct `library`; it is not globally deprecated or merged into JavaScript. Java/JavaScript, C/C++/C#, React/React Native, and SQL/SQL Server remain distinct.

## Portfolio Progress

Portfolio Progress exposes Owner-only facts: project count, projects with complete evidence, first/latest project timestamps, recorded activity span, and Portfolio publication state. It always reports `trend_status: not_available`, `trend_direction: null`, and `HISTORY_NOT_TRACKED`; project creation dates never prove professional improvement or a trend.

## Recommendations

At most three recommendations are visible. Rules order by stable priority, then `rule_id`, then opaque target reference: add first project; complete missing project evidence; correct/review unmapped technology; complete Portfolio publication setup when eligible. Actions are allow-listed and each names the predicate it is intended to resolve.

`active` means predicate true with no suppressing disposition; `snoozed` applies until expiry; `dismissed` applies for the same fingerprint/rule version; `resolved` means predicate false; `superseded` means fingerprint or rule version changed. Only snoozed and dismissed are future persisted dispositions. SHA-256 fingerprints cover canonical facts, rule ID, and rule version only; they never contain raw evidence text, identity, credentials, paths, or database IDs.

## Tenant isolation

Required flow: **Verified Owner Session → Internal User ID → Tenant Scope → Tenant-Scoped Repository → Pure Analytics Core → Contract Mapper → Owner Presenter**. Controllers never accept tenant authority from request `owner_id`, `portfolio_id`, `user_id`, `authz_version`, Auth0 subject, headers, or request IDs. Project resources require the resolved authenticated Owner scope. Future private responses use `Cache-Control: no-store` and never expose analytics publicly.

## Traceability

| Metric/rule | Source/future field | Normalization/calculation | Reasons | Fixtures | JSON field | Future UI state |
| --- | --- | --- | --- | --- | --- | --- |
| Field evidence | future evidence fields | NFC, content graphemes, tokens, repetition | text reasons | `TEXT-*` | `$defs.field_evaluation` | field action |
| Documentation Coverage | three future fields per Owner project | complete/expected × 10000, half up | `NO_PROJECTS` and text reasons | `DOC-METRIC-*` | metrics.documentation_coverage | coverage card |
| Hub maturity | Owner project aggregation | zero/partial/ready predicates | `NO_PROJECTS` | `HUB-STATE-*` | maturity.state | constructive state |
| Technology map | `projects.technologies` | normalized exact alias | `TECHNOLOGY_*` | `TECH-*` | metrics.technology_evidence_map | technology action |
| Portfolio Progress | projects/created_at/Portfolio publication | factual-only; trend unavailable | `HISTORY_NOT_TRACKED` | `PROGRESS-*` | metrics.portfolio_progress | facts-only state |
| Recommendations | canonical current facts | predicate/fingerprint/order | lifecycle reasons | `REC-*` | recommendations | action list |
| Tenant denial | validated Owner scope | reject request authority | `TENANT_AUTHORITY_REJECTED` | `TENANT-*` | none | private denial |

Gate A remains pending human threshold calibration and approval of this correction package. EVIDENCE-HUB-1A CORE, EVIDENCE-HUB-1B UI, migrations, snapshots, AI, external services, queues, and public analytics remain deferred.
