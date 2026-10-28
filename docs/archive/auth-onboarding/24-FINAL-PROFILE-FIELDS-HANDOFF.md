# Optional private final-profile fields — bounded local handoff

Bounded later core: [handoff 27](27-FIRST-COMPLETION-CORE-HANDOFF.md) records atomic first creation/evidence and read-only completed drafts. Existing exact draft retries, current Profile authority and existing-owner access remain; HTTP/UI/routing/publication activation stays deferred. Earlier evidence below remains historical.

Owner-approved catalog amendment (2026-10-19): [handoff 26](../../auth-onboarding/26-LAUNCH-COUNTRY-CATALOG.md) supersedes the earlier all-249 product offering with the ordered 13-country launch list. Full ISO/CLDR provenance and historical stored choices/receipts remain preserved; earlier evidence below retains its original scope.

Subsequent private draft integration: [handoff 25](../../auth-onboarding/25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md) reuses these catalogs and validators without changing final-profile data, editing, storage or public projections. Historical Profile implementation evidence below retains its original scope.

Exact implementation base: merged `bd0ec90f395d99dc6f963c3ba00505e4aa993c46`
(PR 26). Its parents are `d2514104a405c26587350be84c28720ac87f3ff7` and approved
`043a0c469b853821e39b578894f8e5389054cbcc`; both original contract commits remain
ancestors. Remote main was fetched and matched this base before isolation.
Worktree: `.worktrees/final-profile-fields`, branch
`feat/final-profile-fields-20261018`. Original checkout remains at `82353dc…`
with its unrelated required-gates edit/hash preserved. No AGENTS.md was found
in the checkout/worktree or ancestor chain; repository maintenance instructions
and [contract 22](../../auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md) were read first.

This implements only the explicitly requested four-field storage/validator/
existing-Profile-control slice. [Catalog candidates and owner decisions](../../../data/profile-catalog/README.md)
remain distinct from approved completion requirements. This does not approve or
implement other recommendations in contract 22. There is no completion service,
receipt, routing/publication gate, optional-draft promotion, consent flow or new
readiness restriction. Google-only registration and draft/editor gates remain
default off; current attestation policy bundle remains unavailable.

## Storage and exact field contract

Migration `018_final_profile_fields` is the next available additive migration.
One ALTER adds all four nullable columns, default NULL. The normal advisory-locked
runner validates exact type/nullability/default/extra/charset/collation on apply
and repeat. A compatible completed ALTER without a ledger entry is recoverable;
partial or incompatible definitions stop without repair/backfill. Existing rows,
timestamps, ownership, visibility and `profile_version` are unchanged. No draft or
Auth0/provider value is imported. Runtime schema readiness lists the four columns;
this is schema compatibility, not a new user-readiness condition.

| Field | Meaning and optional final storage | Validation when deliberately changed |
| --- | --- | --- |
| `country_code` | Self-declared country/territory of professional location; `CHAR(2) CHARACTER SET ascii COLLATE ascii_bin` | Exact supported uppercase assigned alpha-2 token; no trim/fold/inference. Not nationality or replacement for `location`. |
| `preferred_language` | Profile preference; same CHAR/ASCII definition | Exactly `ar` or `en`; no interface/policy language change or translation. |
| `professional_status` | One chosen self-description; `VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin` | Exactly one of six candidate tokens; no priority between overlapping descriptions and no access/eligibility meaning. |
| `specialization` | Field/discipline of work or study; `VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci` | Valid UTF-8, 1–150 Unicode code points and at most 600 UTF-8 bytes when nonempty; some non-whitespace text; no Unicode Cc. Preserve exact spaces, combining sequences, case and punctuation. No normalization/trimming/truncation. Distinct from `professional_title`. |

The validator is pure: explicit field/value/catalog inputs and no database,
request, normalization or filesystem work. The separate server-owned catalog
loader reads the checked-in generated JSON only. Internal storage NULL is valid;
HTML input must be a string, explicit `''` maps to NULL, omission preserves.
Malformed/unsupported input rejects the complete patch with safe AR/EN feedback.
Invalid UTF-8 is rejected; replacement glyphs may render it safely in the error
form only, never in storage. Name/headline and all legacy validators keep their
existing bounds/trimming behavior; this slice does not adopt R1/R10 globally.

Unchanged unsupported/retired catalog values are shown as selected saved choices,
escaped, and omitted from the patch when posted byte-for-byte unchanged. A new
or changed unsupported token is rejected. Such preservation still uses the
submitted expected version and atomic CAS; it cannot overwrite a concurrent
replacement. No first-option fallback or automatic old-token mapping.

## Editing, concurrency and privacy

Scoped reads load all four facts with the current version in the same SELECT.
The existing form has three bilingual selects and one free-text control, optional
labels/help, associated field errors and exact submitted values. The Profile shell
remains its existing English UI; selecting `ar` does not change that shell.
HTML maxlength is intentionally absent on specialization because it counts UTF-16
units, not Unicode code points; server validation enforces both requested bounds.

Writes reuse the existing owner/session/CSRF checks and versioned patch service.
All supplied new fields are validated again at the scoped storage allow-list;
unrelated fields are preserved. Stale/error responses retain valid submitted
text/choices and the original token for explicit reload/review, without replay.
Malformed arrays cannot be rendered as literal form text. Contact-presence/value,
photo staging/retirement and uncertain-commit behavior remain unchanged. Tests
exercise non-null final facts across existing text/photo/direct-media races.

No public projection changes: the explicit public SELECT, renderer, Project JSON
and contact projection remain unchanged and exclude these four fields. They do
not contribute to the existing Profile completion percentage or publication
rules. The completed/current profile remains authoritative for its own facts.
Draft persistence, preview catalogs and editor controls are untouched. Later
onboarding integration needs a separately accepted completion mapping, catalog
decision/revision handling, policies/evidence and current-draft/version boundary;
it must never hydrate over current Profile edits from an old draft.

## Catalog provenance and decisions

[Provenance JSON](../../../data/profile-catalog/provenance.json) records immutable
URLs/release/commit identifiers, all raw-source/notice checksums and byte counts,
entry counts and generated checksum. [Offline generator](../../../scripts/tools/build-profile-catalog.py)
reproduces exact primary CLDR AR/EN labels filtered through an independent
249-entry ISO snapshot. CLDR labels alone never define membership. Macroregions,
reserved/user-assigned/non-ISO/retired examples and alternate labels are excluded.
Full source/Unicode/LGPL/REUSE notices accompany the data. No runtime downloader,
service, scheduler or package framework is added.

Awaiting owner review: exact country/territory coverage and display naming,
language labels, six status labels/meanings and maintenance responsibility. The
status choices are a single optional self-description, with no invented priority
between student/graduate/employed/self-employed/seeking-work/other overlaps.
This slice's candidate membership is usable only for optional private Profile
edits; it is not acceptance of all contract recommendations or activation of
completion/publication. No stronger ongoing requiredness is invented.

## Commands, results and retained failures

The Desktop review ZIP includes exact commands/harnesses, all completed run logs,
synthetic screenshots, source URL verification, static results and both failed
and successful attempts. Guarded CLI tests require the existing disposable
`ather_career_test_<24hex>` namespace and test environment; no test login endpoint
or production bypass is added. Browser contexts use private CLI-created sessions.

Focused CI integration uses the existing generated database-scoped user for:

```sh
python3 scripts/tools/build-profile-catalog.py --check
php scripts/test/run-final-profile-tests.php
php scripts/test/run-final-profile-route-tests.php
```

The 018 fixture runs before owner-flow edits, after both version suites. Existing
CLI regressions remain in the complete 17-rehearsal block; foundation/renderer/contact/Sign In/readiness checks and
the new Profile browser step remain required. Browser testing reuses the existing
pinned Playwright toolchain and task-only loopback fixtures. Restored-startup
expectation advances to 001–018 with the same full-dump preservation assertion.

Initial successful local run `validation-8d179f715fe7.log` records all 17 CLI
invocations plus foundation, Sign In and readiness regressions in PHP 8.3.33 /
MySQL 8.4.11. New core has five PASS groups; HTTP adds new-field transport,
retired/stale/Unicode/public-projection coverage while repeating existing
text/photo/contact/account/CSRF regressions. Browser run `browser-4c8091ff3b0b.log`
passes five groups at 1440/1024/768/390/320px with synthetic screenshots and
fixture/process/session/data cleanup. Final verification results are recorded
in the review bundle's commands/results and machine-readable validation records.

Final successful CLI run `validation-cd736bab576f.log` includes the strengthened
non-null four-field writer/race assertions, explicit pure-catalog inputs, all nine
pinned source/notice checksums, exact CLDR language labels, invalid UTF-8 feedback,
actual public contact 422 rendering and new-field account/CSRF attempts. All 17
rehearsals plus foundation, Sign In (62) and readiness claim/session/creation pass.
Final Profile browser run `browser-79807155818a.log` passes all five groups with
12 synthetic screenshots (full page, focused viewport and error/conflict states).
The existing draft-editor browser suite also passes all nine groups in
`editor-browser-1e454a5df59a.log`, including its fixture preservation/cleanup.
No draft production source or preview catalog is changed.

`production-build-final.log` and `production-a06f3d251b65.log` record a fresh local
production-image build, all PHP syntax, Apache health/readiness/entry assets,
private catalog/default-off route exclusion, complete 001–018 ledger, unchanged
NULL final defaults/version 1, repeated migration and production security gate.
A separate restored database starts through the same production entrypoint:
before/after full-dump SHA-256 is exactly
`d24512a7e8f7664c736fc9676a9ea32b98f828707d8ecca1a675c1313629d89d`.
The restored database and private dump/option files, then task web/database
containers, three named volumes, network and generated credentials are removed.
These are task-only local resources, not deployment/live migration. The earlier
production harness attempt `production-5e59f139d1f5.log` passed startup/HTTP/security
but failed its external ledger query (`SEPARATOR CHAR(44)`); that harness-only
SQL syntax was corrected to the default comma separator. Its partial log and
failure summary are retained without claiming a full pass.

Retained failures: `validation-78b6e047db9e.log` exposed unsupported MySQL BEFORE
syntax in the initial ALTER; corrected to supported AFTER clauses without data
updates. `validation-65d878b81a17.log` exposed the new HTTP fixture's old hardcoded
location expectation after an intentional unrelated edit; corrected expectation,
not weakened production behavior. `browser-2ef68e01860a.log` failed startup because
the container fixture listened only on its own loopback; browser-only test mode
now binds inside the isolated container for its host-loopback port mapping.
All are retained as failures, not passes. External preparation/quoting/download
probes are listed separately in review notes.

Limits: no remote CI/push/PR, live migration/deployment, real authentication,
Auth0/Notion/canonical-resource change or gate activation. Chromium viewport
checks are not real-device/Safari/screen-reader certification; no full Profile
translation or new production user journey is claimed. Existing injected
uncertain-commit tests are not a physical network partition. Catalog snapshots
do not claim automatic currency after their pinned releases. Remaining product
and completion decisions stay separate.

## Bounded review follow-up 2026-10-19

Exact implementation base: `17801c6d1e99d22af8e43cfe0c720b79d67a9dbc`, clean in
the existing isolated `feat/final-profile-fields-20261018` worktree. The merged
`bd0ec90f395d99dc6f963c3ba00505e4aa993c46` baseline and both completion-contract
documentation commits remain ancestors. Canonical checkout changes are preserved.

Only the new bilingual labels/help/error text changes: country/territory of work
or study, preferred profile language, the closest current professional description,
and specialization distinct from professional title. Optional labels, the
150-character hint and explicit private visibility remain. Product text no longer
describes storage encoding or catalog mechanics; invalid choices ask for an option
from the list, and invalid specialization asks for visible text within the limit,
without line breaks, or an explicit empty field. These are action prompts, not a
replacement technical specification. Valid UTF-8, at most 150 Unicode code points
and 600 UTF-8 bytes, a non-whitespace character when nonempty, no Unicode Cc
controls, and no silent trimming/normalization/truncation remain exactly as before
in the validator and this handoff's field contract. Null/omission/retired values,
catalog contents, privacy/public projections, authorization and CAS are unchanged.

The previous `profile-conflict-mobile.png` filename did not establish a mobile
capture: the tab still had its default 1440px width. The browser test now retains
its original desktop assertions, then explicitly sets and checks **390×844** before
checking all four retained values (`EG`, `ar`, `student`, unsaved specialization),
the original version and the visible reload/review link. Only then does it capture
the replacement screenshot. Existing 1440/1024/768/390/320px keyboard, bounding-box,
overflow, desktop validation and responsive screenshots/assertions remain intact.

Actual focused commands/results (PHP 8.3.33, MySQL 8.4.11, existing Playwright
1.58.0/Chromium tooling; synthetic fixtures only):

- `validate.ps1 -Suite followup-focused.sh`: `validation-08a76adfdd38.log`, all
  **seven HTTP PASS groups** including strict/malformed values, omission/clears,
  retired values, original tokens/conflicts, public exclusion, account/CSRF/
  authorization, text/photo/contact behavior and fixture preservation/cleanup.
- `browser.ps1 -Suite followup-focused.sh`: `browser-d9368630055f.log`, all **five
  Profile browser PASS groups**; `validation-d9368630055f.log` confirms fixture
  snapshot/private log/session/file/process cleanup. Twelve synthetic screenshots
  regenerated; mobile conflict PNG is 390px wide. Visually reviewed that capture
  plus the 320px and 1440px control viewport captures.
- Changed PHP files pass `php -l`; browser script passes `node --check`; external
  shell runner passes Bash syntax. Scope comparison confirms only error literals
  changed in the validator and every existing browser assertion is preserved.
  Documentation links/anchors, `git diff --check`, ancestry and the unrelated
  canonical-file hash pass. The review bundle retains commands, logs and hashes.

Both runs initialize a new generated database namespace from the immutable SQL,
migrate through 003, bind a synthetic preserved owner using the existing backfill,
then migrate through 018. This is fixture setup only; no unrelated rehearsal suite
is rerun. Database-scoped generated credentials run the application/tests. Task
containers, anonymous volumes, networks and credential files are removed, and the
private browser cookie carrier is deleted by fixture cleanup.

Retained failed attempt: `validation-f8d2fb4599a2.log` passed fixture setup and PHP
syntax but did **not** start the HTTP suite: a trailing Windows CR in the external
runner made PHP look for a filename ending in CR. The external script's line
endings were corrected; no production/test acceptance rule changed. Its original
script and failure log remain in the bundle. Sandbox-denied Docker inventory and
Git Bash probes were environment restrictions; Bash passed after approved retry
and Docker cleanup was checked outside that restriction.

No failed/skipped test step remains in the two final affected-suite runs. The
earlier broad core/draft/browser/build/security/restored-startup results above are
historical evidence for the base, **not reruns on this follow-up**. Remote CI,
unrelated suites, production build and real-device/accessibility certification are
not claimed here. No push, PR, deployment, live migration, authentication, gate
activation or external/canonical-resource change is included.

## Draft PR 27 — bounded CI fixture handoff correction

Reviewed parent: `23974d11796f9911cd3716bf6a6e39cb09f1a0ae`, clean isolated
`feat/final-profile-fields-20261018`. Fetched remote main remains expected
`bd0ec90f395d99dc6f963c3ba00505e4aa993c46`; original contract ancestry and the
unrelated canonical-file hash remain preserved. [PR 27](https://github.com/stoers12/ather-career/pull/27)
stays Draft. This correction changes only CI, this handoff and the changelog.

The original [push run](https://github.com/stoers12/ather-career/actions/runs/39173908408)
and [PR run](https://github.com/stoers12/ather-career/actions/runs/39173911887)
failed before any Profile browser group: host Node could not open the container's
root-owned `0600` private context (`EACCES`). Both passed deterministic catalogs,
5 final-profile core/7 HTTP, existing 5 profile-core/6 HTTP, the remaining CLI
regressions, production build and all 9 draft-editor browser groups. Both skipped
rehearsal DB removal, production-like startup/health, migration/security gate,
restored startup through 018, public HTTP fixture, HTTPS discovery mock and HTTP
smoke. Always-run resource cleanup succeeded; Profile fixture preservation evidence
appeared only in the PR run because the old trap removed the container without
waiting. Those failures are retained, not reclassified as passes.

After a nonempty carrier with mode `0600` is ready, CI uses container root only
to assign that file to the actual host `id -u`/`id -g`. The ordinary host Node
process verifies its own non-root UID/GID, the regular file's ownership/mode,
the runner-owned directory's `0700` mode and an `O_NOFOLLOW` read-only open/close.
It does not read or print carrier contents/tokens and does not run as root.
Neither file nor directory permissions are broadened. Existing credentials,
database scope, browser assertions and fixture implementation remain unchanged.

One EXIT handler now preserves the original browser/error status, signals stop,
waits at most 45 seconds, inspects the fixture's stopped/exit-zero state and
collects only its fixed safe PASS lines. Success requires the existing complete
process/session/file/database preservation/cleanup assertion in its logs. Only
then does normal container removal run, followed by generated-user and private
carrier/stop-file deletion. Fixture, log, removal or cleanup failure makes a
successful browser run fail; it cannot mask an already failing browser status.
Timeout is an explicit failure with unconfirmed preservation and forced removal
as fallback. The global fallback reports any leftover Profile container as failure
and no longer force-removes a running fixture outside that timeout path.

Local validation: YAML parses; all 24 extracted Bash blocks and the Profile step
pass Linux `bash -n`; the exact Node preflight passes syntax checking. Eleven
cleanup-control-flow simulations cover success, browser failure, combined browser/
fixture failure, fixture nonzero exit, missing evidence, log/wait errors, timeout,
database-user removal, container removal and private-file deletion failures.
They verify original failure preservation, no false success, timeout-only force
and suppression of a synthetic private diagnostic. These are simulations, not
claims of actual application fixture preservation.

A disposable Linux tmpfs probe reproduces denial of a root-owned `0600` file to
UID/GID 1001, then verifies non-root open/close after privileged ownership transfer
with file `0600` and directory `0700`. Its PHP reader never reads/prints contents.
The first probe failed because its extra parent directory was still root-owned;
only external probe setup was corrected, and its failed log remains retained.
The first external Node-code extraction also included a closing shell quote;
corrected extraction passes without changing CI code. The Windows local host
does not exercise the ordinary Linux Node reader/full GitHub fixture workflow:
that gap must be closed by exact-checkout push and PR CI after normal push.
Local commands/results are retained in the external `ci-handoff-correction`
review evidence; remote reruns and explicit fixture evidence must be reviewed
before claiming a complete pass. No application/catalog/schema/assertion change,
merge, deployment, live migration, activation or real provider login is included.
