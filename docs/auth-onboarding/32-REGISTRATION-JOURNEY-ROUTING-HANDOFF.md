# Card 05 — bounded server-side registration/journey routing

Base fetched and verified: `435ee57e31baa2e233f30a8162090ade07741066`.
Isolated branch `feat/registration-journey-routing-20261020`, worktree
`.worktrees/registration-journey-routing`. Applicable ancestor/repository instructions
(none present), README maintenance contract, contract 22 accepted overlay, handoffs
27–31 and actual callback/Owner/registration/editor/completion/attestation/session/
CSRF/storage/public-shim/direct-caller evidence were reviewed before editing.
Canonical HEAD/status and both unrelated dirty-file hashes remain preserved.

## Trusted distinction and frozen capture

Migration **020** was the next unused version. It is schema-only:

- `portfolios.legacy_owner_user_id INT UNSIGNED NULL DEFAULT NULL`: original User
  binding, not a boolean inferred from existence, client input, session, ID or date.
- `owner_journey_cutover`: constrained singleton id 1, fixed server revision,
  original snapshot count, BINARY(32) SHA256 of sorted integer `[Portfolio, User]`
  pairs, and original UTC microsecond capture time. No workflow/status framework.

The exact cutover is the commit of the explicit, server-controlled capture under
exclusive maintenance. It captures all owned Portfolios present in the reviewed DB
at that moment, including existing published/private owners without completion or
true readiness. No receipt is fabricated. Capture writes only the original binding
and seal; content/ownership/timestamps/contact visibility/profile versions remain.
Migration and application startup NEVER call capture. Fresh/upgrade/interrupted
migration verifies exact schema and refuses populated unrecorded 020 state.

Future deployment requires separately authorized exclusive maintenance: stop/drain
all old/new application and maintenance writers, including in-flight callbacks,
draft/completion requests and workers; verify backup and all intended owner bindings;
apply 020; then explicitly run:

```text
php database/capture-owner-journey-cutover.php --confirm-exclusive-maintenance --database=<exact DB name>
```

The arguments are operator acknowledgements, not an automatic traffic-stop system.
This task executes it ONLY in disposable tests. The CLI requires the recorded schema,
uses a named lock and owns one SERIALIZABLE transaction, locking the Portfolio set
before writing original bindings plus the seal atomically. No web shim or environment
activation/fake policy selector exists. Commit acknowledgement failure is uncertain;
inspect/recover the committed seal, never compensate by deleting potentially
committed data. Missing seal with nonnull markers is partial state and capture refuses
it. Retry checks only already-marked pairs against the original count/digest/revision;
it NEVER marks newly added rows or updates original capture time. A count/digest
mismatch requires manual inspection. Restored startup verifies schema and leaves
all markers/seal exact; it does not adopt partial/unmarked data. Runtime with missing
seal fails closed for uncompleted users rather than inferring legacy ownership.
Global health/readiness validates schema, not operator completion of maintenance;
it is not permission to reopen private traffic before seal verification.

Normal application allow-lists have no legacy-binding/seal writer. These are trusted
server maintenance data, not defenses against a privileged DBA deliberately forging
rows or resetting the seal. Never clear/reset/rebuild the seal to admit later bare
rows. Any anomaly/repair requires a separately reviewed maintenance operation.
Ownership reassignment does not transfer the original User's legacy access.

## Central destination and access

`includes/owner_journey.php` is the small shared decision. Every caller first/repeatedly
validates the current internal active User/session/authz version. Correctly bound
completion joins receipt User → current owned Portfolio → original Profile belonging
to that same Portfolio. Current Profile edits/versions remain authoritative; historical
receipt data is never copied back. No current readiness, policy/attestation availability
or historical authz-version equality is required for ordinary accepted-owner access.

| Current authorized state | Fixed local destination / authority |
| --- | --- |
| Bound committed completion | `/owner.php`; ordinary Owner access |
| Sealed original legacy User/Portfolio pair | `/owner.php`; existing Owner rules, even no receipt or true readiness |
| Incomplete and cutover available | `/owner_onboarding_editor.php` only if draft/attestation/completion HTTP gates are exact `1` and all trusted immutable policy documents resolve; otherwise holding |
| Missing/partial maintenance state | Private holding, no inferred legacy access |

An unproven existing bare Portfolio is incomplete, follows the same capability-based
editor/holding decision and receives NO Owner context. The unchanged completion core
refuses existing Portfolio replacement/adoption; it cannot manufacture completion or
repair such a row. Required future repair is separate. No return URLs or client-owned
completion/ownership fields participate.

Callback routing runs only after existing validated purpose-bound identity resolution,
session rotation and readiness observation. New registration remains Google-only;
ordinary Sign In still rejects unknown identities. The common Owner boundary uses
the decision, then independently obtains the shared owned context. Shared
`requireOwnedPortfolioContext()` also checks cohort/receipt binding, including direct
profile CAS callers. `ownerHasPortfolio()` remains informational, never authority.

Legacy `/owner_onboarding.php` GET/HEAD uses the SAME destination. If holding is
selected it renders private no-store 503 with explicit CSRF-protected account-switch
and logout forms (existing progressive duplicate-submit behavior), never redirects
to itself. No draft/Portfolio write. POST is 405 before DB work, regardless of CSRF,
readiness or posted owner selectors. `createOwnedPortfolio()` independently validates
the current User and denies all legacy creation attempts, including concurrent and
alternate helper callers. Only the unchanged completion transaction creates a new
journey Portfolio/Profile/receipt.

## Bypass audit and preserved boundaries

All actual protected root handlers/public shims derive authority through the common
boundary: Dashboard, Profile/text/photo/skills, projects, experiences, publication,
private preview/media, messages, Evidence Hub and canonical project-evidence routes.
Canonical evidence route parsing/method admission remains intact before authority;
the test router exercises `/owner/projects/{id}/evidence`, not just its raw PHP file.
Profile CAS reauthorizes through the shared context under existing User/Portfolio
locks. No client can turn a static PHP context factory into a request selector.
Scoped query helpers consume trusted server contexts; they are not public APIs.
Completion's internal Profile creation remains valid before its receipt insert within
the same transaction; no new circular eligibility check is added inside that writer.

Retired global/admin/API routes remain retired. Controlled ownership backfill and
legacy media maintenance are not new first-creation alternatives: no maintenance
caller writes a legacy grant, and post-cutover bare creation cannot obtain Owner
access. No new public projection/publication policy or current-field requirements.
Existing published reads and contact visibility remain unchanged. Receipt GET stays
current-User authorized independently of current policies/readiness/cutover availability;
no automatic redirect after uncertain completion. Core mappings, atomicity, exact
retry ordering, optional disposition, draft freeze, catalogs and editor behavior stay.

## Tests, CI and intentional fixture changes

All twenty original CLI invocations/order/scoped credentials/assertions/traps and four
browser steps remain. CI appends the scoped twenty-first real-HTTP journey suite,
requires migration 020 and extends restored startup through 020. The guarded restored
fixture retains nonempty completion/Profile/draft/exact-attestation evidence plus a
sealed original owner set and a later excluded bare Portfolio; full dump equality
must survive startup. Production/default-off HTTP smoke is preserved.

Old fixture bare rows no longer imply Owner authority. Explicit guarded CLI fixture
factories mark ONLY their known synthetic legacy owners; no production test hook or
recurring adoption is added. Existing snapshots now serialize binary rows safely
because the seal contains a binary digest. Retired POST/helper positive-creation
assertions intentionally become 405/helper-denial/no-write/concurrent-zero assertions;
all false/unknown/missing/malformed/stale/mismatched readiness/session/CSRF/selector
cases remain. Grandfathered GET/HEAD still tests false/unknown/missing readiness and
no receipt. Static tests follow the delegated canonical destination/holding form,
keeping current-session/CSRF/duplicate-submit and scoped mutation assertions.

Focused suite has ten PASS groups: additive partial/repeated migration; capture
rollback/uncertain COMMIT/partial refusal/frozen retry; legacy owner access; strict
capabilities/null-policy holding/no loop; all bare-route and retired-helper bypasses;
completed Profile/receipt authority; correct User/Portfolio/Profile binding; account/
expiry/revocation/logout; sealed repeated/restored preservation; exact protected-data
and private source/session/process/log/file/database cleanup. Real HTTP uses persisted
synthetic sessions and guarded whole private source copies; original production null
resolvers are tested directly. Successful policy content exists only in guarded test
copies, never production selection paths.

Final commands/results, logs, retained failures/corrections, static/CI verification,
archive/source reconstruction and Desktop equality are retained outside source in
`.worktrees/registration-journey-routing-review`; the containing commit contains this
handoff. Final local result table is recorded below before commit.

## Retained failures/corrections

- External edit helper defaulted to Windows cp1252 and could not encode Arabic;
  the transient empty Owner flow file was immediately restored from baseline with
  explicit UTF-8 before any execution. No service used that transient file.
- Copied external shell runner had CRLF; stopped before PHP. Converted to LF.
- MySQL information_schema labels needed explicit lowercase aliases; migration
  stopped safely before ledger/capture. Corrected exact metadata aliases.
- Existing JSON full-DB snapshot could not encode binary seal bytes; switched only
  fixture representation to exact binary-safe serialization.
- Existing callback test expected relative `owner.php`; assert the new canonical
  `/owner.php` without relaxing destination or identity checks.
- New parent HTTP fixture omitted CSRF include; added it. Wrong POST expectation
  for GET-only messages corrected; canonical evidence router added while retaining
  direct-PHP 404. Protected cleanup passed on those failed attempts.
- Parent fixture inherited use_cookies=0 after core seeding; restore 1 before each
  persisted browser session, removing cookie-parameter warnings.
- Foundation static checks followed old inline form/redirect; now follow the
  delegated renderer/decision while retaining all behavioral checks.
- First Profile browser runner pointed to nonexistent copied Playwright path; fixed
  the external path, signaled orderly fixture stop and retained failure status/log.
  No browser assertions or application rules were weakened.

## Gaps and remaining Card 05 work

No hosted CI/push, live/canonical migration or actual legacy capture, real legal
policies/login/provider freshness, manual device/Safari/screen-reader certification,
physical database/network partition or production activation. Local browser coverage
uses existing Windows Chromium; Linux integrated non-root carrier/browser evidence
is not claimed locally (CI steps/ownership/0600/0700/cleanup remain unchanged).
PDO faults wrap real MySQL COMMIT; they do not simulate physical daemon loss.

Card 05 navigation/UI/account journeys and any separately reviewed post-cutover
anomaly repair remain. No Dashboard redesign, automatic uncertain-result redirect,
publication policy, optional-data import, services/framework, Auth0/Notion update,
deployment or launch. Both production document resolvers remain literal null;
all actual gates remain off and registration Google-only/default off.

## Final local commands/results — 2026-10-20

| Command/evidence | Actual result |
| --- | --- |
| `validate.ps1`, `validation-61b4f30b2c5e.log` | All 21 ordered CLI calls PASS, including ten journey groups; completion HTTP/core 12/10, attestation HTTP/core 12/5, draft core/HTTP 11/10, Profile core/HTTP 5/6, final Profile core/HTTP 5/7, renderer 3/preview 572 and all ownership/public/tenant/Experience/owner/mock OIDC/registration/routing regressions. Foundation, Sign In 62, readiness claim/session/retired real POST route PASS. No final failed/skipped groups or PHP warnings. |
| `editor.ps1`, `completion-browser.ps1`, `attestation-browser.ps1` final-source console/logs; `browser.ps1` final Profile run | Existing Windows Chromium suites all PASS: editor 11, completion 14, attestation 12, Profile 5. Explicit protected fixture data/private log/process/session/source/file/database cleanup and clean exits. Synthetic screenshots retained; no new UI/browser assertions relaxed. |
| `production-check.ps1`, `production-faebc9c1f34d.log`, `production-build-final.log`, `php-lint.log` | Production build/all PHP lint; Apache private/default-off completion GET/POST, health/readiness/bilingual/public/legacy/catalog smoke; repeated migrations/security; full 001–020 ledger PASS. Restored startup preserves entire dump with committed completion/Profile/draft/exact evidence, frozen owner set and later excluded bare Portfolio (SHA256 `b232c06419cfe57cb7dd620495e48c0cfd6a201aabbcf35b5a636903941645ac`). Generated restore DB/files, two containers, three volumes/network/credentials cleaned. |
| `static-checks.py`, `static-results.json`, `bash-syntax.log` | YAML, all 26 CI Bash blocks, 246 local documentation links, diff whitespace PASS. Original twenty calls/order/credentials/assertions/traps/cleanup and browser steps are exact after removing only the new call and migration/count labels. Twenty-two protected runtime/core/API/session/CSRF/catalog/registration/default-config files are unchanged; both resolvers literal null. |
| `final-image-source-hashes.txt`, `image-source-verification.json` | Fourteen final normalized runtime/schema/public-shim files in the built image exactly match reviewed source, including both null resolvers and unchanged completion core. |
| `canonical-before/after.json`, `cleanup-verified.json` | Exact unrelated canonical HEAD/status/file hashes preserved; only the three pre-existing canonical containers remain. No task container/network/volume/private carrier/credential file remains. Scoped test users/rows cleaned or removed with their generated disposable DB. |

Complete patch, source manifest/hashes, retained original failures, commands/results,
full reconstruction/ZIP/Desktop comparison live in the external review directory.
No hosted run or any forbidden action is claimed.
