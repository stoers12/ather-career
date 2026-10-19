# Authenticated onboarding draft HTTP boundary — 2026-10-18

Current field-rule follow-up: [handoff 25](25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md) adds current Profile catalog validation after authorized structural/receipt handling. This HTTP envelope, denial boundary and bounded exact-retry semantics remain unchanged.

Approved baseline: `674ccb0976a48306545d40dd4025628ad203db54` (PR #23 merged;
Main CI passed on that exact SHA). Branch: `feat/onboarding-draft-http-20261018`.
Isolated worktree: `.worktrees/onboarding-draft-http`.

No AGENTS.md was found in the repository, worktrees or ancestor chain. Before
editing, handoff 19, the reviewed persistence core, authorization/session/Owner
session helpers, CSRF/JSON/error/observability conventions, public shims, Compose,
CI and registration/readiness real-route fixtures were inspected. The canonical
checkout and its unrelated required-gates change were preserved.

## Scope and activation

One endpoint, `/owner_onboarding_draft.php`, supports authenticated GET resume and
POST section save. The production public shim delegates to the application handler
using the existing separate document-root convention. No rewritten URL is added.

The sole capability gate is `ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED`. Only the exact
string `1` enables it. Unset, empty, `0`, `true`, whitespace and other values are
off. Both Compose files default it to `0`; `.env.example` explicitly keeps it off.
These are isolated source changes, not changes to any running service or canonical
environment. Tests activate only their private loopback PHP process. This switch
has no registration, readiness, policy, attestation or completion meaning.

Registration remains Google-only/default off and real policies remain unavailable.
No Auth0 configuration or real authentication is used or changed by this slice.

## HTTP contract

All application responses are JSON with `Content-Type: application/json;
charset=utf-8` and final `Cache-Control: private, no-store`, including denials and
temporary failures. No CORS or login redirects are added. A web server may reject
unsupported transport requests before dispatching PHP; the handler itself admits
only the exact case-sensitive GET and POST tokens and otherwise returns 405 with
`Allow: GET, POST`. Matching and CSRF dispatch use the same method tokens.

Gate and method checks precede session/database access. With the gate enabled,
the route opens the existing `portfolio_owner_session`, validates its current User
with `requireAuthenticatedUser()`, and passes only that context to the reviewed
core. It accepts no request-derived User/Portfolio selector. Supplied identity
headers cannot select another User. Authorization version, account state and
session lifetime remain authoritative in the existing helper and are revalidated
again by the core. No Portfolio or readiness observation is required to save a
private candidate draft.

GET accepts no body or nonempty query string. On success it returns:

```json
{
  "success": true,
  "draft": {
    "version": 0,
    "fields": [],
    "last_edited_section": null,
    "created_at": null,
    "updated_at": null
  },
  "csrf_token": "<current session token>"
}
```

The version-0 absence result is the core's unchanged projection. Resume creates
no draft row. It may generate the existing session CSRF token and refresh session
activity; those are session operations, not draft/account/content writes. On a
nonempty draft, `fields` contains the core's exact saved field map. Consumers must
handle the existing empty-array representation for an empty map. No retry digest,
operation receipt or User identifier is included.

POST requires the current session CSRF token in `X-CSRF-Token`, validated by the
existing `isValidCsrfToken()` helper. Tokens in query strings or JSON are not
accepted. Fetch GET after sign-in/session rotation to obtain the current token.
The media type is `application/json`, optionally followed by unquoted
`; charset=utf-8` (case-insensitive). No Content-Encoding is supported. Form data,
uploads and method override requests are rejected. No nonempty query is allowed.

The UTF-8 body is bounded to **32,768 bytes**, both using advertised length and an
actual read capped at the limit plus one byte. JSON depth is bounded to eight.
Only this four-key object is accepted, in any key order:

```json
{
  "expectedVersion": 0,
  "operationId": "0:0123456789abcdef0123456789abcdef",
  "section": "public_identity",
  "fields": {"display_name": "Candidate", "city": null}
}
```

`expectedVersion` must decode as an integer, never a string, float, boolean or
null. `operationId` and `section` must be strings. `fields` must be a JSON object,
including `{}` for an empty patch; a JSON array is rejected. Missing/extra envelope
keys, duplicate keys (including escaped-equivalent keys), malformed/trailing JSON,
BOMs, invalid UTF-8 and excessive nesting are rejected. The duplicate-key scan
consumes complete JSON strings, so braces/colons within text remain private text.
It is transport validation only; it does not implement candidate/persistence rules.

The route calls `saveOnboardingDraft()` without trimming, normalization, scalar
coercion or synthesis of missing fields. The core owns the section/field allow-list,
string bounds, version-bound operation-ID format, aggregate 16,384-byte stored
payload limit, explicit-null clearing, optimistic version conflicts, transaction,
locking and bounded latest-operation retry receipt. See [handoff 19](19-ONBOARDING-DRAFT-PERSISTENCE-HANDOFF.md)
for exact candidate limits and semantics. Same decoded input with reordered keys
or equivalent JSON escaping is the same retry; transport bytes are not the receipt.
Changing input with the same receipt conflicts. An older receipt cannot erase a
later save, and an operation ID cannot be reused at a newer expected version.

POST success is `{"success":true,"draft":<core result>}` with HTTP 200, including
creation and an exact retry. An empty patch advances one version while preserving
all fields. Saves never establish consent, eligibility, readiness, completion,
slug ownership, visibility or publication.

| HTTP | `code` | Meaning |
| --- | --- | --- |
| 200 | none | Successful current-User resume/save/retry |
| 400 | `invalid_input` | Invalid envelope, candidate, selector/query, body, method override or operation-ID format |
| 401 | `authentication_required` | No internal authenticated session |
| 403 | `access_denied` | Invalid/revoked/disabled/expired/malformed/mismatched current authentication |
| 403 | `invalid_csrf` | Missing or wrong current-session POST token |
| 404 | `unavailable` | Capability gate off |
| 405 | `method_not_allowed` | Method not admitted |
| 409 | `draft_conflict` | Core version or latest-operation input conflict |
| 413 | `request_too_large` | Transport byte bound exceeded/invalid advertised length |
| 415 | `unsupported_media_type` | Unsupported media type/charset/content encoding |
| 503 | `temporarily_unavailable` | Database/config/session-storage failure or incompatible stored draft |

Error envelopes contain only `success:false`, `code`, and a fixed safe `error`
message. Conflicts do not echo private current fields; clients explicitly resume.
Combined-invalid requests are rejected at the first applicable boundary: gate,
method, authentication, query/upload/override, encoding, CSRF, media/body, then core.
An authorization query's wrapped database error is classified as temporary failure,
not as an established authentication denial. No exception, SQL text, credentials,
session token, operation digest or private candidate value is logged by this route.
Active PHP warnings are converted before the default logger can expose session
paths/identifiers. Failure logs contain only a fixed dependency-failure event and
the existing safe request/security metadata. No request/response body is logged.

## Real-route tests and CI

`scripts/run-onboarding-draft-route-tests.php` reuses the existing CLI/test-mode,
exact disposable-database guard and TestEnvironment namespace. It copies the real
public shim into a private document root, links the application outside that root,
starts PHP on loopback, and creates persisted sessions through CLI fixtures only.
There is no test login route or production authentication bypass. Each rejected
HTTP request verifies all database row snapshots are unchanged. Final cleanup
verifies all preexisting rows remain and removes test Users/drafts, processes,
session files and the marked private namespace.

Eight focused PASS groups cover:

1. Strict default-off gate, invalid gate values and unsupported methods. Disabled
   gate requests use invalid database configuration to prove early rejection.
   Lowercase method tokens also exercise the real handler directly, since some
   web servers reject them before PHP dispatch.
2. Missing, idle/absolute-expired, revoked, disabled, malformed, future-dated,
   missing-User and authorization-version mismatched sessions for GET and POST.
3. Empty resume without a row, session CSRF, missing/invalid/cross-session CSRF,
   query selectors, top-level authority fields, GET bodies and method overrides.
4. Exact Unicode partial saves/resume, omission across sections, null clearing,
   empty patches, stale versions, exact/reordered retries, changed input/section
   conflicts, evicted receipts and operation-ID reuse rejection.
5. Strict scalar/object types, malformed/duplicate/escaped JSON keys, UTF-8/BOM,
   depth/body/text bounds, wrong-section/authority fields and encoding rejection.
6. Independent User drafts and refusal to trust an identity header; no Portfolio,
   attestation, consent or completion effects.
7. Private cache/JSON headers; real UPDATE constraint failure/rollback, missing
   draft table, authentication-query failure, invalid/missing database target,
   unavailable session storage and corrupt saved payload; safe error bodies/logs.
8. Full preexisting data preservation and process/session/fixture cleanup.

The fault fixture uses a temporary database-scoped CHECK targeting one generated
operation identifier. It never disables the core's strict CHECKs or grants global
privileges. It removes the temporary constraint in finally; the subsequent eight
core groups verify the exact schema again. Fault table renames are restored in
finally and occur only in the guarded disposable database.

CI now runs eleven rehearsals, adding the HTTP test under the existing generated
database-scoped application-user sequence. That test enables only its private
process; the production-like stack keeps the switch off. Existing unconditional
CI cleanup still removes the rehearsal database/user and Compose resources on
success or failure. The production HTTP smoke gains default-off GET/POST checks.

## Validation evidence and retained failures

Review folder: `.worktrees/draft-http-review`. Its validation/static scripts,
timestamped pass/failure logs, commands/results, manifest, changed-source export,
source hashes and complete baseline-to-head patch are included in the review ZIP.
The manifest records the final local commit; the adjacent ZIP verification record
records its final SHA-256 and verified Windows Desktop copy.

Final complete local run: `validation-0948b5c8522a.log`, exit 0, MySQL 8.4.11/PHP
8.3.33. Application identity and SHOW GRANTS prove database-scoped ALL privileges
and global USAGE only. The outer harness uses root to provision/clean disposable
resources; the new route and core tests/migrations run as the application user.
The unchanged readiness creation-route regression retains its baseline guarded
harness and separate disposable fixture database. Source mounts are read-only,
execution uses a container-local copy, and generated credentials are not bundled.

All eight HTTP groups, eight draft-core groups, five attestation groups, eight
registration-core groups, eleven routing groups, ownership/public/tenant/Owner/
Experience/Auth0 synthetic rehearsals, Phase 2 foundation, 62-check Sign In,
readiness claim/session/real-POST and 572-check presentation regressions passed.
Four changed PHP files passed lint. YAML parse, all CI Bash run-block syntax,
validation-shell syntax, PowerShell harness parse, both Compose config/default-off
checks and Git diff/complete patch checks passed. Cleanup evidence is retained.

Two initial failed runs remain transparent:

- `validation-9d15014ac7be.log`: an invalid-array fixture used an associative PHP
  array that serialized to a valid JSON object. The server correctly returned 200;
  the fixture was corrected to a JSON array. CLI session-cookie setup warnings
  were also removed by using the normal session-cookie setting.
- `validation-f3a41f5d3933.log`: the trigger-based fault setup failed in the scoped
  test environment after the behavioral groups passed. Its generic diagnostic
  does not establish an exact SQL error code. The fixture was replaced with a
  database-scoped CHECK fault rather than broadening privileges. Subsequent runs
  passed. Earlier complete passes are retained, with the final run superseding
  them after exact-method and session-warning hardening.

Not run for this head: remote CI, a production-image rebuild, actual Apache/Caddy
HTTP smoke or restored-startup execution, browser/UI tests, or a separate fresh
database run. Baseline Main CI already passed production build, restored startup
through 016 and HTTP/cleanup checks on `674ccb0`; that is prior-baseline evidence,
not a claim that those checks ran on this new head. No migrations or persistence
core source changed. New production-smoke checks are integrated but not yet run.

## Remaining integration and boundaries

Future UI integration must handle authenticated resume/token acquisition, escaped
rendering, conflict/resume/retry UX, session rotation and temporary failures. It
must not treat the returned last-edited section as a passed gate. Keep private
responses/tokens out of persistent browser caches and telemetry. Approved final
catalogs/required fields, completion/eligibility/consent validation, Profile and
Portfolio mapping, slug reservation, visibility/publication and draft lifecycle/
retention remain separate work. Clearing a field is an edit, not a legal-erasure
or withdrawal workflow. Operator/contact/hosting and policy drafting remain
deferred to launch preparation under the existing owner decision.

No onboarding UI was wired or redesigned. No completion, Portfolio creation,
slug reservation, policy activation, push, PR, merge, deployment, live migration,
real authentication, Auth0/Notion or canonical service/data change was performed.
