# Atomic first completion and durable receipt — local core only

Later bounded UI: [31 — private final review and completion UI](31-COMPLETION-EDITOR-UI-HANDOFF.md) consumes the unchanged core through handoff 30 HTTP. It does not alter mappings/transaction/receipt ordering or manufacture existing-owner completion. Earlier core-only evidence remains historical; real policies/activation/routing/publication remain deferred.

Later bounded transport: [30 — completion HTTP transport](30-COMPLETION-HTTP-HANDOFF.md) exposes the three existing core operations behind an independent off gate, preserving their transaction/authorization and historical receipt ordering. It does not modify this core, add UI, provide production policies or activate the journey. Earlier core-only evidence below remains historical.

Later UI-only slice: [29 — private editor attestation UI](29-ATTESTATION-EDITOR-UI-HANDOFF.md) presents/records attestations separately in the private editor. This does not expose completion HTTP/UI, alter receipts/first-completion requirements, grant access or enable real policies. Earlier core evidence remains historical.

Later transport-only slice: [28](28-ATTESTATION-HTTP-HANDOFF.md) provides default-off
attestation HTTP presentation/recording. This completion core and receipt contract
are unchanged; completion HTTP/UI and production policy availability remain deferred.

Implementation baseline: merged `fd116782128e9cadf1e9ce017dca90d1495c4b39`.
Remote main was fetched and matched before isolation. Worktree
`.worktrees/onboarding-completion-core`, branch `feat/onboarding-completion-core-20261019`.
The separate catalog commit is `b4062d08befe041423042db46e79abdf945ab836`;
the completion commit is its descendant. No core work was discarded for the amendment.
Canonical HEAD remains `82353dc…` and its unrelated required-gates edit/hash is preserved.
Applicable README maintenance instructions, contract 22, handoffs 19–25 and actual
authorization/readiness/attestation/draft/Profile/slug/migration code were inspected.

## Accepted decisions and scope

The [accepted overlay in contract 22](../../auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md#accepted-bounded-first-completion-core--2026-10-19)
supersedes only its listed recommendations. Professional display name maps to
`full_name` (1–150 Unicode code points / 600 bytes), headline to `hero_headline`
(1–180 / 720); no legal-name components, truncation or hidden normalization.
All seven connected fields are required for first completion. Initial slug is
canonical 3–63 ASCII bytes through the existing syntax/reserved list/unique index;
the existing 64-byte lifecycle remains. Specialization uses the shared exact
150-code-point/600-byte validator. Country/language/status use the shared
[13-country launch catalog](../../auth-onboarding/26-LAUNCH-COUNTRY-CATALOG.md), ar/en and six statuses.
Country is professional location; status grants no authority. No inferred Jordan.

New Portfolio is private/unpublished; contacts are private. The four final facts
remain excluded from public projections. Name remains required on later Profile
saves; five other personal facts may clear, and slug retains its lifecycle. Existing
Profile normalization and every existing-owner access/publication rule remain.
Completion history is not current profile data or publication readiness.

All seven optional draft-only fields remain exact private historical candidates:
`name_ar`, `name_en`, `city`, `university`, `graduation_year`, `biography`, `phone`.
They are disclosed in server review and explicitly acknowledged, never promoted,
truncated or published. Draft retention/deletion is a launch decision, not indefinite
approval. Other R recommendations, policies, HTTP/UI and route/publication integration
remain deferred; no wider contract acceptance is implied.

## Internal API and review binding

[Completion core](../../../includes/onboarding_completion.php) exposes server-only:

- `prepareOnboardingCompletionReview(PDO, AuthenticatedUserContext, expectedVersion)`:
  authorized read transaction; returns all saved fields privately, deferred field
  names and bilingual disposition, expectedVersion and a 32-byte random review token.
  Future private review UI must show these before offering explicit confirmation.
- `completeOnboarding(PDO, AuthenticatedUserContext, intent)`: exactly five keys:
  actual integer `expectedVersion` 1–2,147,483,647; canonical version-bound
  `operationId=<version>:<32 lowercase hex>`; `reviewToken=<64 lowercase hex>`;
  strict booleans `confirm_private_creation=true`, `acknowledge_private_draft=true`.
  Acknowledgement is explicit even if there are no optional values; it is data
  disposition acknowledgement, not a new consent/policy substitute.
- `readOnboardingCompletion(PDO, AuthenticatedUserContext)`: original receipt
  outcome for the current authorized User, or null. No old token/cookie/session
  secret, User selector or fresh policy lookup is required for recovery.

No route, public shim, final-review UI, CSRF bypass or capability activation is
added. The future transport must supply its own CSRF/size/no-store controls; none
is claimed for this library-only slice. There are no personal-field overrides,
User/Portfolio selectors, client policies/revisions/timestamps or uploads.

Only one bounded server-side PHP-session review record is kept. It stores a token
and binding metadata/digests, not optional bodies. Binding covers current User,
authz version and authentication time, saved draft version/exact payload digest,
contract/catalog revisions, exact server document bundle, application observation
timestamp and deferred keys. New review replaces it; authentication reset clears
session state. Normal session lifetime and matching revisions/versions invalidate
stale review; no new freshness TTL is invented. Confirmation must echo its token
and both strict flags. No client can hydrate the trusted binding.

## Owned atomic operation and exact receipt

The operation refuses silent PDO/ambient transactions and requires a current active
session. It independently authorizes, locks the User, then repeats active-account,
authz-version, lifetime and context checks. All draft writes use the same User lock.

1. After strict structural intent validation and current authorization, inspect
   only that User's durable receipt. Exact operation ID plus canonical full-intent
   digest returns original IDs/version/revisions/deferred-key names/UTC time.
   It never reads draft/profile bodies as current data or writes any evidence.
   Different intent conflicts/already-completed; invalid confirmation still rejects.
2. For a NEW completion require no owned Portfolio/receipt, the existing strict
   `readAuthorizedOwnerReadinessObservation()` with `email_verified === true`,
   authoritative `currentOnboardingAttestationBundle()`, and exact stored evidence.
   The production resolver is unchanged and **always null**, so new production
   completion remains unavailable. Readiness is an authorized application claim
   observation, never provider verification/freshness. Existing owners are not gated.
3. Use [the internal exact lookup](../../../includes/onboarding_attestations.php)
   under the already-authorized User transaction/lock with exact binary descriptors
   and row share lock. Public attestation APIs retain their owned transactions;
   recording remains separate and is never nested into completion.
4. Read/decode the saved draft, require the exact reviewed version and server
   binding, validate all seven fields. Long headlines and unsupported historical
   countries remain private until explicitly corrected. Unknown/corrupt stored
   data fails closed. No preview step/navigation/attestation alone proves completion.
5. Insert one Portfolio (`is_published=0`, `published_at=NULL`) and canonical slug;
   existing unique owner/slug indexes arbitrate races. Create one scoped Profile
   using the existing helper with mapped exact facts, version default 1 and contact
   flag 0. Optional final fields stay NULL; specialization never overwrites title,
   country/city never synthesizes location, phone/biography are not imported.
6. Insert one receipt with server `UTC_TIMESTAMP(6)` and commit all three rows
   together. No upsert/backfill or existing-row replacement occurs. SQL/slug failure
   rolls back owned work; prior separately recorded attestations/draft stay intact.

Once COMMIT is attempted, missing/false acknowledgement is conservatively
`OnboardingCompletionUncertainException`, even if rollback later acknowledges.
Unknown rollback outcome is also uncertain. No deletion compensates a possibly
committed operation. Recover using a working connection/current authorized session,
then explicitly review/choose next action; no automatic stale replay is performed.

Exact completion retries work after later Profile edits, later authorized sessions
and unavailable policies, because the original immutable receipt is recognized
before new-completion rules. Current Profile/Portfolio remains authoritative.
Draft reads remain compatible. A new draft mutation after receipt presence conflicts
under the shared User lock; an exact old latest draft retry may return its original
read-only result. Save-first makes completion stale; completion-first freezes saves.

## Additive 019 evidence schema and recovery

Only new `onboarding_completions` is added, in InnoDB; no existing content,
ownership, timestamps, visibility or profile_version is initialized/updated.

| Stored evidence | Definition / purpose |
| --- | --- |
| `user_id`, `portfolio_id`, `profile_id` | INT UNSIGNED, User PK; unique Portfolio/Profile IDs; explicit update/delete RESTRICT references |
| `draft_version`, `authz_version`, `readiness_observed_at` | INT UNSIGNED; enforced positive values, draft version ≤ 2,147,483,647; application timestamp is Unix seconds |
| `draft_digest`, `intent_digest`, `review_digest` | BINARY(32), exact SHA-256 digests; no duplicate private field bodies/session secrets |
| `operation_id` | VARBINARY(43), exact original operation identity |
| `contract_revision`, `catalog_revision` | VARBINARY(64), server-owned immutable revision names |
| Twelve document descriptor columns | Same exact VARBINARY bounds as 015; composite FK to that User's complete exact attestation key, not a latest row |
| `attested_at`, `completed_at` | DATETIME(6), original evidence time and server UTC completion time |
| `deferred_fields` | VARBINARY(256), canonical JSON list of up to seven names; contract semantics prove explicit private creation/disposition acknowledgement, never optional bodies |

The guarded migration runner verifies exact columns/defaults/engine, all indexes,
FK target/column/rules, enforced CHECK expressions and no unexpected constraints/
triggers on apply/repeat. Interrupted whole CREATE is adopted only if empty/exact;
partial, weakened or populated-unrecorded state stops for manual review. No destructive
repair or evidence manufacture. Runtime schema readiness/full ledger advances to 019.

The 015 migration fixture temporarily removes only its empty dependent 019 table,
runs every existing 015 assertion through 018, restores 019 and its original ledger
timestamp before protected snapshots. All assertions remain; this avoids weakening
the real attestation-reference FK to accommodate tests. The existing 017/018 fixture
ordering, credentials and cleanup remain. CI adds one focused suite (18 total).

## Validation, commands, retained failures and gaps

Review evidence is retained in `.worktrees/onboarding-completion-core-review` and
the verified Desktop ZIP. Tests use generated database-scoped users in disposable
MySQL 8.4.11/PHP 8.3.33 Docker resources; source mounts are read-only and execution
uses container-local copies. No canonical service/database is used.

Synthetic policy tests explicitly load a guarded CLI fixture with test environment,
Docker and generated database namespace checks. It creates private temporary source
copies changing only the null resolver and include paths; completion behavior below
its include block is byte-compared to production. Original production files/resolver
stay untouched, with no injectable bundle or environment-selected synthetic fallback.
Workers clean source/session files. Recovery workers explicitly load unmodified
production code and prove receipt recovery/exact retry with null policies.

Actual local commands/results (all final steps passed, no skipped suite):

| Command / retained evidence | Actual result |
| --- | --- |
| `validate.ps1 -Suite core-only.sh`, `validation-45518daf252b.log` | First fresh/populated 019 rehearsal and nine initial completion groups pass, all PHP syntax and catalog reproduction pass. |
| `validate.ps1`, `validation-29077755ffe7.log` | All 18 ordered CI rehearsal commands including completion; existing Profile 5/6, final Profile 5/7, draft 11/10, editor three renderer/572 preview; attestation/registration/routing/owner/tenant/Experience/mock OIDC, foundation/Sign In 62/readiness claim/session/POST regressions pass. |
| Strengthened `validate.ps1 -Suite core-only.sh`, `validation-3085b3f86483.log` | All ten completion groups pass, including original-production-code recovery workers, actual maximum Unicode MySQL storage and a valid older observation without a new TTL, rollback acknowledgement failure and all races. |
| `editor.ps1`, `editor-browser-ce66bba87166.log` | All 11 browser groups on final core/019, with matching preservation/private-log/session/process cleanup. 20 redacted synthetic AR/EN, RTL/LTR, 1440/390/320px/theme screenshots. |
| `browser.ps1`, `browser-5c65454aaa2e.log` | All five existing Profile browser groups on final 019, including older US preservation, approved order, real edits/reload/clear/conflict and responsive recovery; final fixture snapshot/cleanup passes. |
| `production-check.ps1`, `production-0ecdd8292f0b.log` | Final production build/PHP lint, Apache health/readiness/bilingual assets/private-catalog exclusion/default-off/legacy routes, repeat migrations/security, full 001–019 ledger and original NULL final defaults/profile versions pass. Restored DB additionally contains a real guarded synthetic completion, Profile, draft and exact attestation; production startup preserves the full dump byte-for-byte: SHA-256 `620f34c32ed62750e3fc6cc1a9e978c0b76caa8f9d500e7348a2942823083a5f`. All restored files/DB and task containers/three volumes/network/credentials removed. |
| `static-checks.py`, `static-runtime.sh`, JS syntax and PowerShell AST | 147 local links/anchors, YAML/all 24 CI Bash blocks and startup script syntax pass; original migrations/full raw sources/null resolver/auth/public/Profile/HTTP/UI/gates and draft decoder/fingerprint remain exact. All old CI calls remain; 25 final functional files match the production image with LF normalization. |

The first production run `production-fe87fa3f55dc.log` also passed its actual 019
ledger/empty-table restored dump, but inherited a stale “through 018” log label.
That external label was corrected, then the stronger nonempty-completion fixture
was added to the checked-in CI startup rehearsal and tested by the final production
harness above. No pass through only 018 is used as proof of 019. The original build
log is retained separately. An initial static exact-transformation probe rejected
one extra blank line around the new CI invocation; its comparison was corrected
without changing source or dropping any invocation/assertion.

Historical catalog-only run/failure evidence is in handoff 26; it is not substituted
for completion tests. The initial catalog run rejected old positive `NZ`; the final
positive example uses approved `KW`, without weakening new-write validation.

No completion HTTP/UI/CSRF/browser final-creation journey exists to test. Existing
Profile/editor real HTTP/browser tests cover unchanged transport boundaries. No
remote CI/push, real authentication/provider freshness, physical network/daemon
partition, real-device/Safari/screen-reader certification, deployment or live
migration is claimed. PDO fault injection surrounds real MySQL transactions; it
is not a physical network fault. Real policies, routing/legacy bypass prevention,
final review UX, optional import, publication and retention/deletion remain separate
launch/integration decisions. Registration stays Google-only/default off; all
existing gates/Create/consent/visibility controls remain off/disabled.
