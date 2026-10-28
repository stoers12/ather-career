# Card 04 — bounded authenticated completion HTTP transport

Later bounded UI successor: [31 — private final review and completion UI](31-COMPLETION-EDITOR-UI-HANDOFF.md) consumes these unchanged transport/core operations in the editor. No API contract, policy resolver, gate or schema change. Earlier transport-only/no-UI evidence below retains its original scope; no launch or full journey completion is implied.

Baseline / fetched remote main: `dd63500e11e34019856bf438cab354502886bd31`.
New isolated worktree `.worktrees/onboarding-completion-http`, branch
`feat/onboarding-completion-http-20261020`. No applicable ancestor/repository AGENTS.md
was found. README maintenance contract, contract 22 accepted overlay, handoffs 27–29
and actual completion/attestation/draft/session/authorization/CSRF/HTTP/fixture code
were inspected. Canonical HEAD and both unrelated dirty source files remain intact.

## Implemented; no activation

[Handler](../../../owner_onboarding_completion.php), [public shim](../../../public/owner_onboarding_completion.php)
and [transport helper](../../../includes/onboarding_completion_http.php) introduce only
`/owner_onboarding_completion.php`. Independent capability
`ATHERCAR_ONBOARDING_COMPLETION_HTTP_ENABLED` enables only on exact string `1`.
Unset/empty/0/true/01/whitespace are off. Checked-in defaults in `.env.example` and
both Compose files are 0; no actual environment/gate is changed.

Both production bundle/content resolvers remain literal null. New production
completion is unavailable; guarded test-only source copies supply synthetic bundles.
No migration, completion/final-review UI, Create activation, final navigation,
Dashboard/callback/legacy cutover, publication change or existing-owner gate.
Registration stays Google-only/default off. Ordered 13-country catalog and all
Profile/draft/attestation/editor behavior remain unchanged. Card 04 is incomplete.

## Exact API and core authority

All responses are private/no-store JSON; no CORS or redirect. Only exact GET/POST
admitted, gate/method checks precede session/DB work. Independently authorize the
current active internal User; never select User/Portfolio from client data.

- GET: no query/body; `readOnboardingCompletion()` returns original receipt or null
  under `receipt`, plus `csrf_token`. No draft/Profile bodies or raw records. No old
  review token, old session, readiness observation or available policies required.
- POST review: exactly `{"action":"review","expectedVersion":3}` with actual
  integer 1..2147483647 and `X-CSRF-Token`. Calls `prepareOnboardingCompletionReview()`;
  returns `review` containing the unchanged saved fields, expectedVersion,
  deferred_fields, bilingual disposition and reviewToken. New successful review
  replaces the one bounded session record. No attestation or Portfolio is created.
- POST complete: exactly action `complete`, expectedVersion actual integer,
  operationId `<version>:<32 lowercase hex>`, reviewToken `<64 lowercase hex>`,
  `confirm_private_creation:true`, `acknowledge_private_draft:true` (actual booleans).
  Remove only validated `action`, pass unchanged five-key intent to `completeOnboarding()`;
  return original `receipt`. No trusted descriptor/binding is client-authored.

The unchanged core owns its transaction and current-User lock/reauthorization.
Only new completion checks readiness application observation (no provider freshness
claim/new TTL), current bundle/exact recorded attestations, saved fields/version,
server-bound review, explicit confirmation and atomic slug uniqueness. No route
prerequisite precedes core exact receipt recognition: exact historical retry works
with later authorized session/Profile edits/catalog changes/unavailable policies,
without rewriting draft/Profile/evidence/timestamps. Changed intent conflicts.
Optional fields/disposition and existing-owner policy remain as in contract 22.

## Transport and safe errors

4096-byte capped php://input read (4097 detection), validated Content-Length,
UTF-8 JSON with depth bound, exact flat allow-list/types and duplicate-key scan
including escaped equivalents. Query/form fields, uploads, method override header,
unsupported JSON media parameters/encoding and request authority overrides reject.
No body/review/receipt/token/session/SQL detail is logged. Dependency logs use only
a fixed route/category/reason. Input JSON exceptions are caught immediately while
decoding; corrupt stored-draft decoding is a dependency failure, not a JSON 400.

| HTTP/code | Meaning / caller action |
| --- | --- |
| 404 unavailable | Gate off; do not infer receipt absence. |
| 405 method_not_allowed | Only GET, POST (Allow header). |
| 400 invalid_input / 413 request_too_large / 415 unsupported_media_type | Correct transport. |
| 401 authentication_required / 403 access_denied / 403 invalid_csrf | Reestablish authorized context; no automatic real authentication. |
| 409 review_conflict | Explicitly read receipt; if missing, load and review saved data again. |
| 422 saved_fields_invalid | Correct saved draft fields, then prepare fresh review. |
| 503 prerequisites_unavailable | Current required policies unavailable; receipt read remains possible. |
| 503 temporarily_unavailable | Dependency/config/storage failure; explicitly read receipt. |
| 503 outcome_uncertain | Core could not establish transaction outcome; explicitly read receipt. |

Plain messages never assert nothing was saved or instruct automatic POST retry.
Lost response may follow commit; GET receipt recovery must be explicit. No
compensating deletion, new intent creation, automatic replay, or nested transaction.
Error payload contains only success=false, stable code and safe error string.

## Tests and evidence

[Focused route suite](../../../scripts/run-onboarding-completion-route-tests.php) uses
real HTTP to two independent PHP servers, persisted synthetic PHP sessions,
disposable fully migrated MySQL, and guarded private source copies. Its test-only
builder reversibly checks source include/resolver substitutions; no production
hook/resolver parameter/environment-selected fake bundle is introduced.

CI preserves all nineteen earlier calls/assertions/migration order/cleanup and
appends the twentieth scoped-user rehearsal. Default-off production HTTP smoke
covers GET/POST through the new public shim. Core tests remain the authority for
internal boundaries and genuine same-User/changed-intent/cross-User slug/draft-save
races, so these are reused rather than rebuilding every assertion in HTTP.

Final commands/results, retained failures/corrections and explicit gaps follow.
Review evidence lives outside the frozen source in
`.worktrees/onboarding-completion-http-review`.

## Final local results (2026-10-20)

Disposable MySQL 8.4.11 / PHP 8.3.33, generated database-scoped user, read-only source
mount and container-local execution copy. Only task-owned containers/databases/
networks/volumes/credential files were created/removed; no canonical service touched.

| Command / retained evidence | Actual result |
| --- | --- |
| `validate.ps1`, `validation-24bab94b75bb.log` | All 20 ordered CLI invocations PASS. New completion HTTP 12 groups; unchanged completion core 10; attestation HTTP 12/core 5; draft core 11/HTTP 10; Profile core 5/HTTP 6; final Profile core 5/HTTP 7; renderer 3, preview 572; ownership/public/tenant/Experience/owner-flow/mock OIDC/registration/routing. Foundation/Sign In 62/readiness claim/session/real POST regressions PASS. No failed/skipped final groups. |
| `validate.ps1 -Suite route-only.sh`, `validation-8d727eeb05bd.log` | Earlier focused 12 completion HTTP groups PASS; full final run above additionally tests exact 4096-byte acceptance, chunked over-limit capped read and changed saved draft after review. |
| `production-check.ps1`, `production-8c295f9c081d.log`, `production-build-final.log`, `php-lint.log` | Production build and all PHP syntax PASS. Apache new public shim GET/POST default-off private/no-store 404, health/readiness/AR-EN/private catalog/legacy checks PASS. Repeated migration/security PASS; exact 001–019 ledger unchanged. Nonempty completion/Profile/draft/exact attestation restore preserves entire dump (SHA256 `e40cb05204d874d0380e873c409ded904a6c15347ff7e8145e3568f5b872b81a`). Restore DB/files and containers/three volumes/network/credentials removed. |
| Final cached source build `production-build-final-source.log`, `final-source-php-lint.log` | Final source image rebuild and all six new PHP files lint PASS after test/document updates; runtime route/helper/shim unchanged from the successful production smoke above. |
| `static-checks.py`, `static-results.json`; Linux `bash -n` for extracted CI blocks | YAML, all 25 Bash blocks, all 20 ordered calls, exact bounded CI diff preserving old assertions/credentials/cleanup/migration order, 153 existing/new document links and git diff whitespace PASS. Core/draft/Profile catalog/editor and both null resolvers unchanged. |

The real HTTP suite covers authorization on GET/POST, CSRF/transport types/duplicates/
forged selectors/size/encodings/uploads, original null policies, exact private saved
review with seven deferred names and disposition, token replacement, missing/stale
saved draft, changes in bundle/review/readiness/evidence, locked revocation, invalid
saved fields versus corrupt stored data, first private creation, original receipt,
changed intent, later-session/Profile authority under unmodified null production
resolver, genuine concurrent two-session confirmation at the real User-lock barrier,
SQL failures at all three writes, uncertain before/after real COMMIT and lost HTTP
response followed by explicit receipt read. Full database snapshots protect unrelated
rows for reads/rejections/retries and final fixture cleanup. Existing core tests
add genuine changed-intent/draft-save/cross-User slug races and internal boundaries.
No response promises absence or retries automatically. HTTP logs are scanned for
private values/tokens/session IDs/passwords/SQL/exception details; private fixture
sources/sessions/processes and generated scoped user are removed.

## Retained failures and corrections

- `validation-7a4a3d04c880.log`: external runner used Windows CRLF shell lines;
  shell stopped before application tests. Runner converted to LF.
- `validation-1c98a6030742.log`: Guzzle normalized lowercase methods, yielding
  anonymous 401 rather than raw-method 405. Test now uses the existing CLI raw
  method-admission technique; actual HTTP tests retain all uppercase method cases.
- `validation-f32aa093a099.log`: deliberate `[]` corruption was blocked by MySQL's
  existing JSON OBJECT constraint. Test now inserts an OBJECT with an unknown key,
  reaching the stored-data decoder while preserving schema checks. Core fixture
  seed's CLI use_cookies=0 caused cookie-parameter warnings in browser-session
  helpers; restore use_cookies=1 after seed. Final run has neither failure/warning.

No application acceptance condition was weakened for these fixture corrections.
Earlier failures remain in the external review logs; final selected suites have
zero failed/skipped groups. No hosted CI or Main CI is claimed for this local commit.

## Explicit gaps / next safe step

- Existing editor/attestation/Profile browser suites were not rerun locally: no
  UI/JavaScript/CSS changes; their merged-baseline evidence remains historical.
- No real policies/legal approval, real users/provider authentication/freshness,
  manual devices/Safari/screen readers or production activation were tested.
- PDO test faults wrap real MySQL writes/COMMIT; lost response is a private source
  copy exit after commit, not a physical daemon/network partition.
- Current catalog remains unchanged. Historical receipt ordering is source-checked
  and exercised with missing policies/readiness/review and later sessions/Profile
  edits; no dynamically replaced catalog fixture is claimed.
- Production Apache smoke stays gate-off. Enabled successful requests use real
  HTTP on guarded PHP fixture servers; no enabled production journey is exercised.

Next safe action is owner review of the local commit, complete patch, manifest and
retained logs. Final UI/Create integration, real policies, routing/publication,
retention/deletion and activation remain separate. No push/PR/merge/deployment.
