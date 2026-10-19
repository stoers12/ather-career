# Card 04 — exact attestation HTTP presentation and recording

Base: `8bef4ac1ebaa4e2b667291e4ad667ad0b6c44c6a` (merged PR 29). Remote main was
fetched and matched before isolation. Branch `feat/onboarding-attestation-http-20261019`,
worktree `.worktrees/onboarding-attestation-http`. Applicable repository/ancestor
instructions, README maintenance contract, handoffs 18/20/25/27, contract 22's accepted
overlay and actual attestation/authorization/session/CSRF/HTTP/completion source were
read first. No applicable AGENTS.md was found. Canonical HEAD and both unrelated
dirty files are preserved; their hashes are retained in the review evidence.

## Implemented boundary; unavailable production policies

One endpoint `/owner_onboarding_attestation.php`, with the required public shim,
implements private authenticated JSON GET/POST only. Only exact string `1` enables
`ATHERCAR_ONBOARDING_ATTESTATION_HTTP_ENABLED`; unset, empty, `0`, `true`, `01` and
whitespace are off. `.env.example` and both Compose defaults are 0. No actual
environment, canonical service, provider or gate was changed/activated.

`currentOnboardingAttestationBundle()` remains the original literal null. New fixed
server content contract `currentOnboardingAttestationDocuments()` also returns null.
It has exactly three slots, age/terms/privacy, each with `language` and exact UTF-8
plain-text `content` bytes. The implementation adds no legal text, approved URL,
version, generic registry, runtime download or environment-selected synthetic fallback.
Future approved immutable bytes/translations require a separately reviewed source
change. Descriptor construction is not legal approval. Production GET/POST cannot
present or record policies now, even if the endpoint flag is syntactically enabled.

For each of the three documents, resolution requires nonempty valid UTF-8, at most
65,536 bytes, exact descriptor language and matching lowercase SHA-256 of the exact
bytes. All three must pass together. No trimming, normalization, translation or
rewriting occurs. JSON escaping is a transport representation; decoded content bytes
must reproduce the digest. Future UI must render this content as text, not HTML.

These bounds are transport/storage safety contracts, not approval of legal policy
content. Full fixed document slots are server-owned, never selected by query/body,
provider claims, profile language preference or catalog values.

## GET presentation and exact-current evidence

Gate/method admission precede session/DB access. GET starts the existing Owner
session and independently calls `requireAuthenticatedUser()`. It requires no
Portfolio or readiness observation and adds no existing-owner access restriction.
All GET attempts retire the preceding presentation, including unavailable documents
or dependency failure. Successful resolution reads evidence only for the exact
current trusted bundle using the existing independently authorized/locked read API.

Success projection:

```json
{
  "success": true,
  "documents": {
    "age": {"identifier":"<server>","version":"<server>","digest":"<sha256>","language":"<server>","content":"<exact bytes as UTF-8 text>"},
    "terms": {"identifier":"<server>","version":"<server>","digest":"<sha256>","language":"<server>","content":"<exact bytes as UTF-8 text>"},
    "privacy": {"identifier":"<server>","version":"<server>","digest":"<sha256>","language":"<server>","content":"<exact bytes as UTF-8 text>"}
  },
  "evidence": {"recorded":false,"recorded_at":null},
  "csrf_token":"<session token>",
  "presentation_token":"<64 lowercase hex>"
}
```

`recorded=true` means only this exact current bundle has evidence for the current
authorized User. `recorded_at` is its original server UTC microsecond timestamp,
not a new read time. Internal User/Portfolio selectors and other historical evidence
are not projected. GET records no evidence and changes no Profile/draft/completion.

One bounded PHP-session record stores a fresh 32-byte random presentation token plus
current User, authz version, authentication time, SHA-256 of session ID and the twelve
exact trusted descriptors. No bodies, raw session secret or token history are stored.
Normal activity refresh does not invalidate it; authentication/authorization/session
or bundle change does. No new freshness TTL or provider freshness claim is introduced.
New GET replaces it. Authentication reset already clears the session as before.

Missing/malformed/mismatching content or null bundle returns 503 `policies_unavailable`,
no documents/token/evidence status and no usable presentation record or evidence write.

## POST explicit actions and idempotence

POST accepts exactly the following four members, no extras:

```json
{
  "presentation_token":"<current token>",
  "age_18_or_older":true,
  "terms_accepted":true,
  "privacy_notice_acknowledged":true
}
```

The existing `X-CSRF-Token` header is required. All actions must be actual boolean
true; strings/numbers/false/null/arrays/objects/missing members fail. Token syntax is
exactly 64 lowercase hexadecimal characters. No request-derived User, Portfolio,
document/revision/digest/language/timestamp or authority overrides are accepted.

After current authorization/transport validation, POST re-resolves and validates all
server bytes/descriptors and compares the complete presentation binding/token. A
missing/changed session record, other account/session, rotated token or changed
bundle returns 409 `presentation_stale`, clears the record and requires fresh review.
Missing token *member* is structurally invalid (400); a valid-shaped token without
the corresponding current server presentation is stale (409). No acceptance is
carried from an older bundle. Unavailable documents clear the record and return 503.

Only then is explicit `recordOnboardingAttestations()` invoked. It owns its existing
transaction, locks the current User and independently revalidates current session,
account status/authz version/lifetime before exact lookup/INSERT. There is no nested
transaction, upsert, client descriptor construction or attestation-core/schema change.
Concurrent same-User requests serialize at this existing lock and unique exact key.
Success returns only `success` plus exact-current `evidence` status/original time.

The presentation remains usable for explicit repeats until fresh GET or invalidation.
Repeats return the original exact evidence without changing `recorded_at`; fresh
review in another authorized session can likewise read/explicitly repeat the same
bundle without replacing its timestamp. Changed bundles require fresh actions and
preserve older evidence. No age verification, blanket processing consent, access
grant, readiness observation, completion or publication follows from these actions.

## HTTP controls and failure/recovery contract

Exact case-sensitive GET/POST only; other methods return 405 with `Allow: GET, POST`.
No query selectors, uploads, parsed form data, method-override header or GET body.
POST uses UTF-8 application/json only (optional UTF-8 charset); content encodings
are refused. Request body is bounded to 4,096 bytes using both Content-Length checks
and a capped input read, including chunked input. Decode depth is bounded; duplicate
keys, including escaped equivalents, malformed/trailing JSON/UTF-8 and unexpected
members/types are rejected. No request or descriptor values enter safe error text.

Every application response has JSON UTF-8 and final `Cache-Control: private, no-store`,
no CORS or login redirect. Active warnings are converted into safe exceptions before
PHP logs paths/session IDs; generic failures log only a static dependency classification.
Production Apache already uses its safe method/protocol/status access format without
URL/query/header/body. Private PHP test-server logs contain only synthetic request
paths, are scanned for tokens/documents/SQL/private sentinels and then removed.

| HTTP/code | Meaning |
| --- | --- |
| 404 `unavailable` | Flag off, no session/DB access |
| 405 `method_not_allowed` | Unsupported exact method |
| 401 `authentication_required` | No internal authenticated session |
| 403 `access_denied` | Invalid/expired/revoked/disabled session/User |
| 403 `invalid_csrf` | Missing/wrong session header token |
| 400 `invalid_input` | Invalid envelope, field/type/duplicate/query/body/upload/override |
| 413 `request_too_large` | Advertised/actual body exceeds bound |
| 415 `unsupported_media_type` | Unsupported POST media type/charset or content encoding |
| 409 `presentation_stale` | Fresh document review required |
| 503 `policies_unavailable` | No complete valid current configured documents/bundle |
| 503 `temporarily_unavailable` | Dependency/configuration/session/SQL/result-confirmation failure |

Authentication DB failures are dependencies (503), not an account denial (403).
Any failed response after recording starts may follow a committed INSERT. The generic
503 explicitly asks the user to read current evidence before submitting again; it
does not promise no record or trigger an automatic retry. No compensating delete is
performed. Recover with a fresh authorized GET on a working connection/current
documents, inspect original status/time, then explicitly choose the next action.
Unavailable policies cannot be bypassed to recover a supposedly current presentation.

## Validation and retained failures

Review evidence: `.worktrees/onboarding-attestation-http-review`. Guarded tests use
disposable MySQL 8.4.11, PHP 8.3.33, real loopback HTTP requests and persisted sessions.
The unmodified public shim/handler/resolvers prove the enabled-but-null production
boundary directly, including test-mode environments. Successful tests use PRIVATE
copied source with only exact fixed-null resolver/include-path substitutions plus
guarded test bootstrap/PDO faults. Copies are reversibly compared to original
functional source. Production selection accepts no fake resolver argument/environment.
Synthetic bytes and controls live in guarded support files/private copies only.

Focused final `validation-c316e16db300.log`: all 12 HTTP groups PASS, including all
three document faults, AR/EN bytes/languages, exact 64KiB document limit, 4KiB request
and chunked limits, strict types/duplicate/overrides, account/session/CSRF boundaries,
fresh review, genuine two-server/two-session requests held at the real User lock,
original timestamps/history, real CHECK rollback, injected before/after real INSERT
and after real COMMIT failure, explicit re-read/repeat recovery and complete protected
DB/process/session/source/file cleanup. No screenshots/browser UI are part of this route.

Full ordered local rehearsal and production/static results are recorded in the
verified review bundle; the final validation summary below identifies their logs.

| Final command / evidence | Actual result |
| --- | --- |
| `validate.ps1 -Suite focused.sh`, `validation-c316e16db300.log` | All 12 new HTTP groups, PHP syntax, fresh migrated 001–019 and exact 13-country reproduction PASS. |
| `validate.ps1`, `validation-a9508e67f013.log` | All 19 ordered CLI invocations PASS: attestation core 5, completion 10, draft 11/10, Profile 5/6, final Profile 5/7, renderer 3, preview 572, registration 8/routing 11 and all ownership/public/tenant/Experience/owner/mock-OIDC/foundation/Sign In/readiness regressions. Generated scoped user and fixtures cleaned. |
| `validate.ps1 -Suite focused.sh -Image ather-attestation-http-review:20261019-local`, `validation-e18849c5c246.log` | All 12 HTTP groups also PASS on the freshly built production PHP 8.3.33 image itself; original null-policy, concurrent/uncertain-response and cleanup paths included. |
| `production-check.ps1`, `production-cb90dc9a8d5d.log` / `production-build-final.log` | Fresh production build and all PHP lint; Apache GET/POST public-shim off-gate JSON/private/no-store 404; health/readiness/assets/private catalog and legacy boundaries; repeat migration/security gate PASS. Nonempty completion/Profile/draft/exact attestation restore/startup preserved full dump through 019, SHA256 `1bf29540f1b3424c4889c134f0f9d7642fda4f30fcd4483938032c30d0ea1897`. Restored DB/private files and all task containers/three volumes/network/credentials removed. |
| `static-checks.py`, `static-runtime.ps1`, Bash and PowerShell parse checks | 83 protected files unchanged, no migration, 165 local links/anchors, YAML/all 24 CI Bash blocks, both Compose configs and PowerShell syntax PASS. 18 final functional source files match the production image with LF normalization; literal-null production resolvers and default-off capability confirmed. |

CI retains the entire original eighteen-command sequence, migration prerequisites,
generated database-scoped credentials, traps and cleanup, appending this HTTP suite
as the nineteenth. Production HTTP smoke adds off-gate GET/POST shim checks only;
all browser, restored-startup 019 and other assertions remain intact.

Retained failed attempts (not represented as passes):

- `validation-5c0d263133bf.log`: external shell harness had CRLF, stopped before tests;
  external generator now writes LF. No application behavior changed.
- `validation-c170e897cf27.log`: nine groups passed, dependency-failure group stopped
  with an overly broad safe phase label. That label did not diagnose a SQL failure.
- `validation-c5055a6148a9.log`: external probe confirmed the old SQL CHECK is accepted;
  a temporary timestamp-based fixture variant did not resolve the failure and was
  discarded. Final CHECK and rollback assertion remain as originally written.
- `validation-a9d9a97da0d9.log`: safe phase/class diagnostic identified a ConnectException
  after server restart. The fixture chose new ports while existing browser clients
  retained old addresses. Final fixture reuses its original private ports on restart;
  no assertion or production route was weakened. An explicit fresh authorized fixture
  session is used after session-storage failure. All attempts cleaned disposable resources.
- `production-6bc79ede6fae.log` and `production-948da4b226a9.log`: build/lint passed,
  but an external Apache probe compared Cache-Control as an ordered string. Safe
  diagnostics showed 404/unavailable with `no-store, private`; PowerShell's parsed
  directive order differs from the PHP-server raw header. Final probe requires
  exactly the two private/no-store directives as an unordered set, rejecting extras;
  application code/response semantics were unchanged. Both attempts cleaned all
  disposable production resources; final complete restore is only the cb90 run.

An initial external static probe referenced nonexistent helper filenames; corrected
to actual paths before the final protected-file/link/CI verification. This was a
verification-helper lookup error, not a source or acceptance-rule correction.

Physical network partitions/daemon failure are not injected; possible-commit testing
throws around real MySQL COMMIT in an isolated PDO subclass. No real authentication,
provider freshness, live policies, final review/CSRF journey UI, real-device/accessibility
certification, remote CI, deployment or live migration is claimed for this local slice.
No local browser run is claimed for this HTTP-only slice; unchanged editor/Profile
browser suites remain in CI with every assertion and private-carrier cleanup intact.
Local Apache smoke exercises off-gate presentation/recording; successful enabled
document cases run over real PHP HTTP with persisted sessions/private source copies.
The full existing CI synthetic OIDC/public-project HTTP smoke remains integrated,
but its exact hosted job is not executed locally or claimed as a new remote CI result.
Final-review UI, completion HTTP, callback/Dashboard/legacy cutover, publication,
optional draft promotion, retention/deletion and activation remain separate work.

## Preserved behavior and next safe action

No migration, schema/backfill, attestation core, completion, Profile/draft save,
public output, catalog, auth/session/CSRF helper or existing-owner gate changed.
Exactly thirteen countries remain supported in their approved order; no all-249
offering or geolocation restriction is introduced. Registration stays Google-only
and default off. Create/consent/visibility preview controls remain disabled.
Next safe action is owner review of the local commit/bundle; production documents
and journey integration remain unavailable. No push/PR/merge/deployment/activation,
Auth0/Notion or canonical service/data/environment change was performed.
