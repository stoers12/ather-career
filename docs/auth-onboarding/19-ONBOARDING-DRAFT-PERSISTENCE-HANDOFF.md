# Private onboarding draft persistence/resume core — 2026-10-18

Subsequent bounded integration: [handoff 25](25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md) applies current Profile catalog rules only to new writes. The structural/storage bounds and exact receipt format recorded below remain compatible with historical data.

Baseline: `969e7f3a0467e97105817b4427c4f74a0c4e774b` (merged PR #22; Main CI passed).
Branch/worktree: `feat/onboarding-draft-core-20261018`, `.worktrees/onboarding-draft`.
No AGENTS.md was found in the checkout/worktree/ancestor chain. Handoffs 14–18,
the field matrix, preview, authorization/session helpers and migration conventions
were inspected. Canonical source and its unrelated required-gates edit are preserved.

## Field/API contract recorded before implementation

All values below are private draft **candidates**, never final completion validation.
The five preview countries and professional-status examples are not catalogs.

| Logical section | Supported fields and bounds |
| --- | --- |
| `public_identity` (matrix step 2) | `display_name`, `name_ar`, `name_en`: 150 Unicode code points / 600 UTF-8 bytes each; `city`: 100 / 400; `country_code`: exactly two uppercase ASCII letters, ISO-shaped candidate only; `preferred_language`: 2–35 byte language tag with 2–3 lowercase primary letters and optional 2–8 alphanumeric subtags |
| `professional_snapshot` (step 3) | `professional_status`: 80 / 320, free text candidate; `specialization`: 150 / 600; `headline`, `university`: 200 / 800 each; `graduation_year`: exactly four ASCII digits, candidate only; `biography`: 2000 / 8000, permits LF newlines |
| `profile_identity` (step 4 candidate inputs only) | `slug_candidate`: 1–63 lowercase ASCII letters/digits/hyphens, including incomplete leading/trailing hyphens; no availability, reserved-name or reservation authority; `phone`: 40 / 160, private text candidate with no identity/recovery/visibility meaning |

Step 1 eligibility/readiness/attestations and step 5 confirmation/completion have
no draft section. Media, links, skills and contact/publication controls are deferred.
Non-null field values must be strings containing a non-whitespace character, valid
UTF-8 and no Unicode Cc control characters (except LF in biography). No trimming,
case folding or Unicode normalization occurs. Empty string is rejected: **null
explicitly clears** a field. Numeric/boolean/structured field values are rejected.
Canonical UTF-8 JSON for the entire draft is bounded to 16,384 bytes.

`readOnboardingDraft(PDO, AuthenticatedUserContext): array` returns only the
current User's `{version, fields, last_edited_section, created_at, updated_at}`.
Absence is explicit: version **0**, empty fields, null section/timestamps.
Read/resume never creates a draft, selects another User or copies profile content.
Last-edited section is navigation metadata, not evidence that prior gates passed.

`saveOnboardingDraft(PDO, AuthenticatedUserContext, expectedVersion,
operationId, section, fields): array` applies only supplied allow-listed fields
of that section. Omitted fields and other sections are preserved. Null removes
only its named field. An empty patch is valid and records the edited section.
No required-completion fields are demanded on save. Unknown keys, wrong-section
fields and authority fields fail the whole operation without partial writes.

Expected version must be an actual integer 0–2,147,483,647. Initial creation
requires **0** and commits version **1**. Each accepted new operation increments
once, including a no-change patch; versions never wrap. A stale version or an
exhausted counter throws `OnboardingDraftConflictException` without data changes.

Operation ID is exactly `<expectedVersion>:<32 lowercase hex characters>`;
the caller generates a fresh opaque suffix for each intentional save. One bounded
latest-operation ID and SHA-256 of canonical exact input (including version,
section, supplied keys and explicit nulls) are retained with the draft. A retry of
the latest operation with identical input returns its unchanged result/time/version;
different input throws the same defined conflict. Key order is immaterial. After
a later save, the earlier receipt is gone and its expected version is stale:
retry conflicts and cannot replay/erase later work. Binding the ID prefix to its
expected version prevents reuse with different input at a newer version. This is
a last-successful-operation retry window, not indefinite idempotency history.

Both operations own their transaction and require exception-mode PDO and an active
PHP session. They independently use existing authorization helpers, reject a
mismatched context, lock the current User, and repeat account/version/lifetime
validation before reading/writing. No target User ID comes from request fields.
Caller-owned transactions are refused; failures roll back owned work and unrelated
SQL failures propagate. No request routes or CSRF/UI integration are added.

Migration 016 will create only the bounded private store, with unique User ownership,
explicit FK update/delete RESTRICT, server UTC created/updated timestamps and no
backfill, deletion or retention automation. Final completion must separately resolve
approved country/status/language catalogs, required fields, slug semantics and all
eligibility/consent/privacy/workflow requirements. Operator/contact/hosting details
and legal-policy drafting remain deferred to official-launch preparation by owner
decision; this slice does not reopen them. Registration stays Google-only/default
off, and real policies remain unavailable.

## Actual schema and recovery

016 was the next unused migration at the approved baseline. It is deliberately
narrower than the old “drafts and profile fields” proposal: only `onboarding_drafts`.

| Column | Exact type/rule |
| --- | --- |
| `user_id` | `INT UNSIGNED NOT NULL PRIMARY KEY`; FK `fk_onboarding_drafts_user` to `users(id)`, update/delete RESTRICT |
| `version` | `INT UNSIGNED NOT NULL`; enforced check 1–2,147,483,647 |
| `payload` | `VARBINARY(16384) NOT NULL`; canonical exact UTF-8 JSON object; enforced JSON-valid/object check |
| `last_section` | `VARBINARY(24) NOT NULL`; enforced allow-list of the three logical sections |
| `last_operation_id` | `VARBINARY(43) NOT NULL`; latest version-bound operation ID |
| `last_operation_digest` | `BINARY(32) NOT NULL`; raw SHA-256 of canonical operation input |
| `created_at`, `updated_at` | Each `DATETIME(6) NOT NULL`; server `UTC_TIMESTAMP(6)` |

The InnoDB table has no defaults, auto-update timestamps, extra indexes/triggers,
status column, profile references or per-operation history. User locking serializes
creation and updates; the User PK enforces one draft. UPDATE additionally matches
the previously read version and requires one affected row. Retry receipt, fields,
version, section and timestamps commit together. Created time never changes through
the API, and an identical latest retry does not update timestamps or version.

The normal advisory-locked migration runner creates the whole table and verifies
its exact column shape, attributes, full primary key, engine, FK and enforced CHECK
expressions before recording 016. Expression comparison preserves literal values,
parentheses and semantics while accounting for MySQL metadata quoting. Weakening a
check with `OR TRUE`, disabling it, extra constraints/indexes, partial columns or
changed types fails closed. An interrupted CREATE is recoverable only with an empty
compatible table and absent 016 ledger. Unexpected pre-ledger rows stop for manual
review; there is no destructive repair/backfill. Repeats verify the table even when
016 is already recorded. Earlier migration files and records are not changed.

Readiness still requires the complete checked-in migration manifest/ledger and now
requires the draft version column. CI's restored-startup expectation advances to
001–016 without weakening full dump-content preservation or cleanup checks. The
focused draft test runs once in the existing generated database-scoped-user sequence.

## Validation and retained commands/results

The local review folder `.worktrees/draft-review` retains `validate.ps1`,
`validate.sh`, timestamped outputs, schema-only failure diagnostics, static checks,
the complete baseline-to-head patch and exported handoff. The Desktop review ZIP
contains those artifacts; its exact commit, path and SHA-256 copy verification are
recorded in the review manifest. No credentials, private sessions or real profile
content belong in the bundle. Synthetic private draft values are not printed by
the tests; only PASS groups and existing opaque security-event metadata are logged.

Validation uses disposable MySQL 8.4.11 and cached dependency-complete PHP 8.3.33
(`ather-career-ci-web:2fb7e5227f334f754b5c8234e0e8497f24b2cc62`) with read-only
source mounts and container-local writable copies. Generated credentials, databases,
PHP sessions, processes, network and anonymous volumes are removed. The focused
test itself removes its own rows/sessions and verifies snapshots; outer CI/container
cleanup guarantees disposal even if DDL or an assertion fails before local cleanup.

The eight focused PASS groups cover:

- Populated 001–015 upgrade, repeat/interrupted CREATE recovery, incompatible/partial
  schema, exact enforced checks and unchanged existing rows.
- Partial saves, clear/omission semantics, section isolation, countries/statuses
  outside preview examples, exact Unicode resume and UTC with a non-UTC connection.
- Malformed/structured/oversized text, total encoded payload bounds, wrong-section
  and authority-key rejection without partial writes.
- Explicit absent version 0, strict version/operation-ID formats, stale conflicts,
  identical/reordered retries, null versus omission, changed input/section conflicts,
  and refusal after the one-receipt retry window advances.
- Anonymous/inactive/disabled/revoked/idle/absolute/malformed/cross-User denial for
  both read/save, plus revocation between initial authorization and User locking.
- Caller-owned transaction preservation, silent-PDO refusal, proven real INSERT
  and UPDATE rollback on commit failure, unrelated MySQL 1146/1062 propagation,
  counter exhaustion, JSON/section/version checks and FK RESTRICT.
- Two independent processes synchronized before User locking: identical and
  competing absent-row creations, competing and identical updates, and same-ID
  different-input races. Losers conflict without lost fields or duplicate versions.
- Existing Users, bindings, attestations, ownership/content and migration rows
  preserved; all private fixtures/sessions removed.

Focused checks also run on a separate empty-fresh database through 016 (only that
test database's classroom seed is removed). The populated ownership rehearsal
exercises preservation of the baseline seed and representative content. Existing
attestation (five groups), registration core (eight), real routing/SDK (eleven),
ownership/public lifecycle/tenant authorization/Owner flow/Experience/Auth0,
full Phase 2, Sign In, readiness claim/session/real POST and 572-check onboarding
presentation regressions are included. PHP lint, CI YAML parse, changed Bash syntax
and Git patch checks are retained. No remote CI is run because this slice is not pushed.

The first two disposable attempts stopped at migration 016's overly strict
comparison of MySQL's escaped CHECK metadata. The schema-only diagnostic identified
that mismatch; comparison now accounts for metadata quoting while retaining complete
expressions, parentheses and exact literal content. Those failed runs are retained,
not represented as passes. A host lint command initially used the canonical working
directory instead of the isolated worktree; the final worktree/container lint passed.

## Remaining integration and lifecycle boundaries

No production routes/UI, preview controls, browser storage, public projections,
logs of draft payloads, profile/Portfolio writes, media uploads, slug reservation,
publication, account-state changes, completion or eligibility gates are added.
Drafts cannot replace the separate attestation store or trusted readiness. Saved
navigation metadata never proves preceding gates. Existing-owner access is unchanged.

Future integration needs reviewed CSRF/input/size handling, authenticated private
resume/save routing and safe escaped rendering, conflict/retry UX, approved final
completion validation and catalog resolution, profile mapping, slug reservation,
visibility/contact controls, and workflow/access/activation/rollback review. Payload
schema changes need explicit compatibility/migration review; corrupt or unknown saved
fields fail closed rather than being returned. There is no draft expiry/deletion,
withdrawal or retention automation: lifecycle policy remains unresolved. Clearing a
field is a user edit, not a legal erasure/withdrawal workflow. Operator/contact/hosting
details and legal-policy drafting stay deferred by the owner's launch-preparation
decision. No new questions about them are introduced by this slice.

No push, merge, deployment, live migration, registration/policy activation, Auth0
change, canonical resource change or Notion update was performed.

## Interrupted-task resumption and final local result

The interruption was an agent access-token refresh failure. It did not establish
an Ather authentication failure. Work resumed in the existing branch/worktree at
the same baseline without resetting, cleaning, recreating or discarding work.

Last complete retained pass: `validation-285d3bc24929.log` (local 00:40:32).
File times place subsequent edits in `scripts/run-onboarding-draft-tests.php`
(operation-ID reuse with a newer expected version must reject without changing
rows) and review `validate.ps1`/`validate.sh` credential configuration (00:41).
No source snapshot was retained for that run, so this attribution uses timestamps
and inspected source rather than claiming a byte-exact old source diff. All listed
regression suites were rerun on final source.

The first resumed run `validation-da8f89eca370.log` passed the populated scoped-user
suite and regressions, then failed at fresh-resource provisioning with MySQL 1064:
shell quoting removed host literal quotes from GRANT. It is retained as a failed
overall run. The corrected fresh harness uses `fresh.php`, a separately generated
database-scoped user and root only for disposable provisioning/cleanup. Application
migrations, counts and focused tests use that user. Final grants match CI:
`ALL PRIVILEGES ON <disposable database>.*` and global USAGE only. No global
application privileges were added. Earlier historical root-run evidence is retained,
but the final scoped-user run supersedes it.

During review CHECK comparison was tightened further: whitespace and identifier
quotes are ignored only outside SQL literals. Literal whitespace/backticks remain
significant. A new test changes `public_identity` to `public_ identity` and proves
migration verification refuses it. The original two metadata-escaping failures and
diagnostics remain retained. Their precise correction strips MySQL metadata
backslashes immediately before quote delimiters and known charset introducers,
retaining full expression parentheses and literal case/content. Weakened predicates
and disabled checks still fail closed.

Final complete run: `validation-99da1cedb379.log`, exit 0. It passed populated
upgrade/preservation rehearsals, all eight focused groups, all listed regressions,
seven changed PHP-file lints, and separate fresh baseline through 016 plus all
eight draft groups. Both focused runs include operation-ID reuse rejection.
SHOW GRANTS records the scoped application privileges. Disposable containers,
volumes, network and temporary credential file were removed. `static-checks.log`
records YAML parse, PowerShell harness parse, changed Bash and all CI run-block
Bash syntax, and fresh PHP harness lint. Git diff checks and source hashes are
recorded in review logs/manifest. Host Bash initially failed to create its Windows
signal pipe inside the sandbox; the authorized outside-sandbox syntax rerun passed.

The review manifest identifies the local commit and all 13 changed files.
`baseline-to-head.patch` exports the complete approved-baseline-to-commit diff.
The ZIP contains changed source, handoff, commands, hashes and retained success and
failure logs. Desktop copy SHA-256 equality is recorded beside the ZIP; the ZIP
cannot contain its own final hash.

Limitations: local Docker validation only. Remote CI, production-image rebuild,
actual restored-startup and production HTTP smoke were not run in this resumption.
The restored-startup edit updates the expected ledger/text and passed Bash syntax;
repeat/recovery/preservation behavior passed real MySQL validation. UI/request
integration and lifecycle policy remain deferred. Registration stays default off;
policies stay unavailable. No push, merge, deployment, live migration, Auth0,
canonical-resource or Notion change was made.
