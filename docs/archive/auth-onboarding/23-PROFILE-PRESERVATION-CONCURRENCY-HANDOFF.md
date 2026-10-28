# Bounded profile preservation and concurrency — 2026-10-18

Bounded later core: [handoff 27](27-FIRST-COMPLETION-CORE-HANDOFF.md) records atomic first creation/evidence and read-only completed drafts. Existing exact draft retries, current Profile authority and existing-owner access remain; HTTP/UI/routing/publication activation stays deferred. Earlier evidence below remains historical.

Exact implementation base: `0b818ea3babece756bc866259d10b590b478c573`.
Its parent is contract commit `7ef0db943e4fefc5e2a01f1ba6a3fc7ed9ffe71c`,
whose parent is reviewed main `d2514104a405c26587350be84c28720ac87f3ff7`.
Both documentation commits remain ancestors. The original checkout is at
`82353dc2d2a709858d0b56852182acce20f06009`, with an unrelated required-gates
edit; it and existing worktrees are preserved. No AGENTS.md was found in the
checkout, isolated worktree or ancestor chain. Worktree:
`.worktrees/profile-preservation-concurrency`.

This implements only the user-authorized R11/R12 portion of
[contract 22](../../auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md#small-profile-write-concurrency-contract-r11).
It does not approve or implement the other completion/lifecycle recommendations.
Registration remains Google-only/default off, draft/editor gates remain default
off and `currentOnboardingAttestationBundle()` remains null. No completion facts,
catalogs, policy text, publication predicates or routing change is included.

## Schema and migration

017 was the next available migration at this exact base. The guarded
`017_profile_version` path adds only
`personal_info.profile_version INT UNSIGNED NOT NULL DEFAULT 1` and enforced
`CHECK (profile_version > 0 AND profile_version <= 2147483647)` in one ALTER.
Existing rows receive 1; profile values, ownership, visibility and timestamps
are preserved. No old migration/bootstrap schema is rewritten. New rows use 1.

The normal advisory-locked migration runner verifies the exact type, nullability,
default, extra attributes and enforced check on apply and repeat. A compatible
completed ALTER without a ledger entry can be verified and recorded on retry;
a partial/incompatible column/check stops for inspection, without destructive
repair or resetting versions. Runtime readiness requires the additional column
and the complete checked-in ledger. CI restored-startup expectation advances
from 001–016 to 001–017. No live migration or deployment was performed.

## Save contract and authorization

Scoped profile reads load the values and version in the same SELECT. Existing
text/contact, image upload and image removal forms carry that loaded token.
An absent profile is version 0. POST accepts only canonical decimal strings
`0` or `[1-9][0-9]{0,9}`, bounded to 2,147,483,647. Missing/invalid tokens return
a safe 409 requiring explicit reload; no POST-time read supplies a replacement.

`saveOwnedProfilePatch()` owns an exception-mode PDO transaction. It independently
checks the current active authenticated User/session, locks User then Portfolio,
revalidates authorization/version/lifetime/ownership, and compares the supplied
version in the scoped SQL UPDATE. The current row read is used for required-name
validation and the previous image reference, never to rewrite expected version.
Existing-owner readiness policy is unchanged: email observation is not a new
profile edit gate. No User/Portfolio selector in request data grants authority.

`updateAuthorizedPersonalInfo()` now requires an explicit fifth expected-version
argument. Every successful UPDATE increments once, including unchanged-value and
empty-patch saves. The predicate includes profile ID, authorized Portfolio,
expected version and non-exhaustion. Zero updated rows is not success; the former
existence-based fallback is removed. Values at maximum version cannot wrap.
This low-level capability helper remains SQL-only; authenticated form writes use
the revalidating transaction service. Regression/fixture callers now supply a
version explicitly rather than using an unversioned compatibility path.

Version-0 creation is serialized under the owner locks and rechecks absence.
The existing unique Portfolio/profile key is the final race boundary. A form
opened before another request creates a profile conflicts; it never upserts
or overwrites the winner. Skills/slug/publication live in separate rows and do
not receive a fake profile increment. Historical ownership backfill is a guarded
pre-contract metadata operation, not a concurrent live profile editor.

## Preservation, visibility and conflict feedback

Text saves patch only supplied editable fields. Omitted represented fields and
future fields outside this form remain untouched. Text saves never replay
`profile_image_path`. Existing nonempty trimming, UTF-8/character bounds and
email/HTTP-URL checks remain; an explicitly supplied empty optional text clears
only that column to NULL. A required name cannot clear, and new creation needs
its own supplied nonblank name. Malformed values or unsupported/uneditable keys
reject the entire patch; omission does not force unrelated legacy values through
new validators. R1/R10's broader final-field validator is not implemented.

Contact visibility accepts explicit string `0` or `1`. The existing checkbox has
`public_contact_present=1`; that exact marker plus an unchecked/absent checkbox
means deliberate false. When both control and marker are absent, visibility is
preserved. Invalid marker/value fails the whole patch. Photo/text operations
without an explicit visibility intent cannot toggle it. The aggregate semantics
and existing-owner settings remain; separate preview switches are not enabled.

Authorized stale submissions return 409 with the submitted text, valid visibility
choices and original token retained in the response, plus a fixed reload/review
link. There is no PRG success, automatic merge, automatic stale replay or fresh
token attached to old inputs. Inputs remain in the page only, not browser storage
or telemetry. A browser file selection may require reselection after reload.
Existing authentication/CSRF denials remain separate from authorized conflicts.
Pre-handler upload denials (including 429) also retain the original submitted
version instead of binding a rejected old request to a newly loaded version.

## Media outcome handling

`saveOwnedProfileImage()` owns its newly staged key, uses the same versioned
transaction and retires the previous key only after commit returns success.
It does not retire an unchanged key. Confirmed rollback/conflict cleans only
request-owned staged originals/derivatives. The direct legacy-media profile
UPDATE also compares/increments the selected version and retains its old-key
predicate; it now owns the profile transaction and refuses ambient transactions.
The legacy source is not deleted by migration. Project media logic is unchanged.

`runProfileWriteTransaction()` treats an acknowledged pre-COMMIT rollback as
confirmed failure. Once COMMIT has been attempted, any exception/false result is
uncertain even if a later rollback is acknowledged; that cannot prove the earlier
commit did not land. Loss of rollback acknowledgement is also uncertain. It throws
`ProfileWriteUncertainException`; both image paths retain potentially referenced
staged files and do not retire old files. The form reports 503 honestly: the
change may already be saved; explicitly reload/review before another change.
No compensating reference deletion, fresh-version replay or background cleanup
system is added. Retained unreferenced files after uncertainty are an intentional
safe limitation, subject to existing separately authorized recovery operations.
Cleanup after a confirmed commit is best effort and cannot cause compensation of
the committed new image. Structured failure logs retain fixed codes/opaque
metadata, not request bodies, tokens, SQL messages or private media paths.

## Validation and retained evidence

Focused CI commands use the existing generated database-scoped application user:

```sh
php scripts/run-profile-version-tests.php
php scripts/run-profile-version-route-tests.php
```

The core suite covers upgrade/interrupted/repeated migration and unchanged
protected fingerprints, missing/disabled check rejection, strict version parsing,
same-value updates, stale/exhausted versions, account context isolation, concurrent
same-value saves and absent creation. Child PHP processes begin real overlapping
MySQL transactions behind a barrier; this is not a sequential “race” simulation.
It also covers text/image, remove/upload and direct media-migration races, same-key
image preservation, confirmed rollback cleanup, COMMIT=false and exception cases,
commit-then-lost-acknowledgement and rollback-acknowledgement-loss retention.

The route suite runs the actual production public shim/application handler on
private loopback PHP with persisted CLI-created sessions, without a test login
route or production bypass. It covers loaded tokens, two-tab stale text/visibility
retention, same-value HTTP saves, omission/NULL clears, visibility presence/value
rules, missing/invalid/exhausted versions, malformed fields, account isolation,
CSRF, idle/absolute expiration, revocation/disabled accounts, actual multipart
upload/remove, text/image preservation and real SQL CHECK rollback/file cleanup.
Private HTTP logs are checked for body/token/credential leakage. Processes,
sessions, files and generated rows are removed and preexisting snapshots checked.

The media-migration fixture temporarily excludes older synthetic baseline image
and project references whose source files are not present, then restores their exact paths
and timestamps without changing versions; the full final snapshot must match.
Fault-injection PDO subclasses and worker barriers exist only in guarded CLI
test files; production constructors/routes accept no fault override.

Final successful main run: `validation-a50bbf5b97b9.log`, PHP 8.3.33/MySQL 8.4.11,
including both focused suites, Phase 2 foundation, owner/tenant/Experience/OIDC,
draft/editor/attestation/registration and readiness regressions, Sign In (62) and
onboarding presentation (572). `validation-5e5a5e2130e5.log` records additional
Stage-2C integrity, MySQL contact and publication-lifecycle E2E regressions in
separate disposable databases. `validation-5e11c66ed0aa.log` records the extended
migration-versus-text/upload/remove cleanup races, also included in the final main
run. All PHP files linted. Final static checks cover local links, YAML, CI Bash
and shell/PowerShell syntax, Compose defaults and private-resource cleanup.

The review folder/ZIP retains harness commands, all completed run logs, failures,
final results, changed-file hashes and complete baseline-to-head patch. The final
validation record identifies successful runs and explicit gaps; failed attempts
are not represented as passes. No private sessions, credentials, keys or request
body dumps are included. The test harness mounts this worktree read-only, executes
a container-local copy, uses cached dependency-complete PHP/MySQL images and
removes only task-created containers/network/volumes and generated secrets.

Original local validation gaps (before initial PR CI): no remote CI,
real login/provider journey, live migration, deployment
or canonical gate/service/data operation. No new publication rule or completion
workflow is implemented/tested; existing publication regressions passed. No new
production-image build, actual Apache smoke or restored-startup execution is
claimed (the updated startup expectation is syntax-checked and integrated in CI).
Built-in PHP HTTP covers form transport and rendering;
database concurrency is proven separately with real parallel PHP/MySQL workers.
Lost COMMIT acknowledgement is injected around real commits via test-only PDO
subclasses, not a physical network partition. No new browser automation or
screen-reader/device accessibility audit is claimed.

## Draft PR 26 — bounded CI ordering correction

Follow-up base: reviewed head `82bdaa8d210eae0c8aaa239e9089d1cfe390b1a6`.
Remote main was fetched and remains `d2514104a405c26587350be84c28720ac87f3ff7`;
the existing isolated branch is clean before this correction and preserves both
documentation ancestors. [Draft PR 26](https://github.com/stoers12/ather-career/pull/26)
remains a draft. No production source, schema, assertion or gate is changed.

The initial [push CI](https://github.com/stoers12/ather-career/actions/runs/39158083099)
and [PR CI](https://github.com/stoers12/ather-career/actions/runs/39158095832)
both failed at the core suite's first migration precondition:
`Migration upgrade fixture must precede profile editing.` The former checked out
the reviewed head; the latter checked out temporary merge
`d03b764789a9ad2965fadf9304fc16b379000f56`, whose parents were expected main and
reviewed head and whose tree exactly matched the reviewed source. Production
build, foundation/renderer/preview/readiness and preceding rehearsals passed.
Zero of the five core or six HTTP PASS groups were reached. The remaining CLI
commands (HTTP, registration, attestation, drafts, editor, repeated presentation
and routing), browser, web startup/health, migration/security, restored-startup,
public fixture, discovery mock and HTTP smoke did not execute. The dedicated
rehearsal deletion step was skipped; final always-run cleanup instead succeeded,
including database deletion and container/volume/network removal. Full original
logs and the complete failed/skipped-step report are retained outside Git in the
review folder and in the PR description.

Cause: the shared-database owner-flow rehearsal successfully saves a profile and
increments its version before the 017 upgrade fixture requires version-1 rows.
The successful original local harness had run that fixture before owner-flow.
This correction moves only the existing owner-flow invocation immediately after
both profile-version suites and adds a brief ordering comment. Its original
application credentials remain unchanged; both profile suites still use the
generated database-scoped user. Every prerequisite, assertion, trap and cleanup
remains intact. No versions are reset and no tests are omitted or weakened.

Actual pre-push validation: PyYAML parses the workflow; an exact transformation
check confirms only that move/comment; all 22 extracted CI Bash blocks and the
disposable harness pass Bash syntax. `validation-ee43b3c4859b.log` records PHP
8.3.33/MySQL 8.4.11 running all 15 rehearsal invocations in the corrected workflow
order, extracted directly from YAML. All five profile-core and six real HTTP PASS
groups, owner-flow and every remaining rehearsal pass. Migration checkpoints
001–004 and the complete ledger through 017/required project columns pass.
The original application user is retained for owner-flow; the profile/registration
suites use a separate generated database-scoped user, removed by the exit trap.
Fixture preservation and cleanup checks pass; task containers, anonymous volumes,
network and generated credential file are removed. Source is mounted read-only
and executed from a container-local copy; no canonical service/data is touched.

Retained local preparation failures: the first transformation check used a
Windows-default text encoding and was corrected to explicit UTF-8 in the external
harness; the sandbox denied Git Bash's signal pipe, then the identical syntax
checks passed outside the sandbox. Neither required a repository change.

At this commit, corrected remote push/PR CI has not yet run. Its exact checkout
SHAs, temporary merge tree, all PASS groups, browser/build/security/startup/HTTP
results and every failure/skip must be checked in the resulting runs and recorded
in the PR verification report. Local success does not substitute for those gates.
Real provider login, live migration/deployment, device/accessibility audit and
physical network-partition testing remain outside this correction. All other
completion-contract recommendations remain unapproved and unimplemented.
