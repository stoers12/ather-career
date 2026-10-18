# Optional private final-profile fields — bounded local handoff

Exact implementation base: merged `bd0ec90f395d99dc6f963c3ba00505e4aa993c46`
(PR 26). Its parents are `d2514104a405c26587350be84c28720ac87f3ff7` and approved
`043a0c469b853821e39b578894f8e5389054cbcc`; both original contract commits remain
ancestors. Remote main was fetched and matched this base before isolation.
Worktree: `.worktrees/final-profile-fields`, branch
`feat/final-profile-fields-20261018`. Original checkout remains at `82353dc…`
with its unrelated required-gates edit/hash preserved. No AGENTS.md was found
in the checkout/worktree or ancestor chain; repository maintenance instructions
and [contract 22](22-ONBOARDING-COMPLETION-CONTRACT.md) were read first.

This implements only the explicitly requested four-field storage/validator/
existing-Profile-control slice. [Catalog candidates and owner decisions](../../data/profile-catalog/README.md)
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

[Provenance JSON](../../data/profile-catalog/provenance.json) records immutable
URLs/release/commit identifiers, all raw-source/notice checksums and byte counts,
entry counts and generated checksum. [Offline generator](../../scripts/build-profile-catalog.py)
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
python3 scripts/build-profile-catalog.py --check
php scripts/run-final-profile-tests.php
php scripts/run-final-profile-route-tests.php
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
