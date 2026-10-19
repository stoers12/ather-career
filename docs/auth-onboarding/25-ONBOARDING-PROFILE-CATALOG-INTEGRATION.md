# Authenticated onboarding editor / Profile catalog integration

Owner-approved catalog amendment (2026-10-19): [handoff 26](26-LAUNCH-COUNTRY-CATALOG.md) supersedes the earlier all-249 product offering with the ordered 13-country launch list. Full ISO/CLDR provenance and historical stored choices/receipts remain preserved; earlier evidence below retains its original scope.

Baseline: merged `8e886688bfab531e3a1021b3c4b6de918145223c` (PR 27). Remote main
was fetched and matched before work. New isolated worktree:
`.worktrees/onboarding-profile-catalogs`; branch
`feat/onboarding-profile-catalogs-20261019`. Handoffs 19–24 and applicable repository
maintenance instructions were read first; no AGENTS.md exists in applicable
repository/worktree/ancestor locations. Canonical checkout stays at `82353dc…`;
its unrelated required-gates file hash is unchanged. All previous worktrees/commits
are preserved. No push, PR, merge or activation is part of this slice.

## Bounded behavior and authority

The authenticated editor reuses [Profile catalog](../../data/profile-catalog/catalog.json)
revision `ather-profile-candidates-2026-10-18-r1`: all 249 assigned country/territory
codes, exact current-locale AR/EN labels, `ar`/`en`, and six status tokens
(`student`, `graduate`, `employed`, `self_employed`, `seeking_work`, `other`).
The [provenance/notices](../../data/profile-catalog/README.md) and generated checksum
`b4f9e13579d12d62389ca9ed23db42ea2399ef9c669c64f078062fe4be31a6c2` are unchanged.
No new catalog, downloads, dependency, widget framework or service is added.
Catalog naming/meanings/maintenance remain reviewable owner decisions; reuse for
private draft editing does not approve completion or eligibility requirements.

Country is a self-declared professional location, language a profile preference,
status one current self-description with no overlap priority/access meaning, and
specialization a field of work/study distinct from professional title. New edits
use exact tokens and [the existing specialization validator](../../includes/final_profile_fields.php):
valid UTF-8, up to 150 Unicode code points and 600 bytes, non-whitespace when
nonempty, no Unicode Cc, and no trimming/normalization/truncation. Empty form text
maps to explicit draft null only when deliberately edited/cleared; empty or
whitespace strings sent directly remain invalid. Incomplete drafts remain valid.

Only the authenticated renderer selects the catalog. Native selects/radios,
existing seven-field structure, preview/section layout and theme behavior remain.
The guarded design preview retains its five-country/five-status examples and
original inert script/output. No optional inputs are added. Historical extra
options/radios are literal text, labeled “Older saved choice” / “اختيار محفوظ
سابقًا”; unsupported unsaved entries are identified as unlisted. Values are never
mapped to the first option, normalized, deleted or interpreted as current tokens.
Interface-language switching localizes labels and preserves both saved preference
and unsaved choices; it performs no preference write.

## Compatibility, new writes and receipts

[Draft core](../../includes/onboarding_drafts.php) keeps
`onboardingDraftFields`, structural `onboardingDraftValidatePatch`, decoder and
canonical fingerprint input unchanged. Structural compatibility remains the
historic 2-letter uppercase country shape, 2–35-byte language-tag shape, status
80 code points / 320 bytes and all prior field/text/16-KiB payload rules.
Unknown/corrupt data still fails closed; compatibility is not arbitrary JSON
acceptance. There is no schema or payload migration, backfill or receipt rewrite.

`saveOnboardingDraft` now follows this narrow order:

1. Existing strict version/operation-ID and structural section/patch validation;
   compute exactly the existing SHA-256 fingerprint, including supplied nulls.
2. Existing owned transaction, current active User/session authorization, User lock
   and authorization/lifetime/context revalidation. HTTP retains its normal session,
   CSRF and strict envelope/size/type boundary before entering the service.
3. Match the bounded latest-operation receipt. Exact input returns its compatible
   original result/time/version even if a saved choice is now unsupported. Same
   ID with changed structurally valid input conflicts. Malformed input fails before
   lookup; unauthorized or wrong-CSRF requests cannot retrieve the receipt.
4. For a new operation, require the current expected version/non-exhaustion, then
   validate only supplied four-field values through Profile catalog/validator.
   New unsupported values reject the whole patch, including an explicitly resent
   unchanged historical token. Unrelated omitted historical fields are not checked
   against current catalogs. The editor already sends changed fields only, so its
   normal unrelated save does not resend historical choices.
5. Apply the existing exact partial merge: omission preserves, null unsets, and
   fields/receipt/version/timestamps commit together. Existing same-User locking,
   CAS/creation race handling, one-receipt eviction and rollback remain unchanged.

Receipt handling grants no authorization or new-write validation bypass. A later
save evicts the receipt and old retries conflict; changing expected version while
reusing the old ID remains invalid. No additional retry history/version field,
receipt service, profile write or synchronization framework is introduced.

[HTTP handler](../../owner_onboarding_draft.php) and [transport helper](../../includes/onboarding_draft_http.php)
are unchanged: the existing safe generic 400/409 envelopes and private/no-store
responses remain. Editor feedback asks for listed choices and valid text without
printing server exceptions. Immutable pending operations, exact retry, late-edit
serialization, conflict/recovery, account-switch stop and browser-storage/privacy
boundaries are preserved.

## Evidence and acceptance

All rows below are local disposable validation, not remote CI or live data.
Fixtures require guarded CLI/test mode and generated database-scoped credentials;
root only provisions task resources. Source mounts are read-only; execution uses
container-local copies. Historical rows are seeded only by a guarded test helper
using the unchanged structural encoder/fingerprint; no production bypass exists.

| Path | Actual result |
| --- | --- |
| Focused `validate.ps1 -Suite focused.sh`, `validation-92660e8132ac.log` | 11 draft-core, 10 real HTTP, three renderer groups and unchanged 572 preview checks pass. |
| Full `validate.ps1` / `validation-6f45774234b0.log` | All 17 CI rehearsal invocations, current/legacy draft rules, Profile/final-profile/core/HTTP concurrency, ownership, tenant/media/Experience/OIDC mocks, registration, attestations, editor, routing, foundation, Sign In 62 and readiness claim/session/POST pass. Includes current catalog concurrent saves and old-receipt races. |
| `editor.ps1`, `editor-browser-e4398054edbd.log` and matching fixture log | All nine original plus two new browser groups pass (11 total). Exact 249/six/two label sets in both locales; older choices, unrelated omission, replacement/clears, server-rejected 151 code points, accepted 150/600, retained edits/fresh corrected operation, saved/unsaved language independence, and incomplete reload. All original conflict/recovery/auth/session/CSRF/privacy/uncertain-retry assertions remain. |
| Profile `browser.ps1`, `browser-ee9d2e5be7bd.log` | All five existing Profile browser groups plus fixture preservation/cleanup pass. |
| `production-check.ps1`, `production-build-final.log`, `production-3e86febee923.log` | Fresh local production build and all PHP syntax; Apache health/readiness/assets, catalog exclusion, off-gate editor/API/legacy routes, security gate and repeated migrations pass. Full ledger stays 001–018, existing final defaults/profile versions unchanged. Restored-entrypoint full-dump before/after SHA-256 equals `95ae4e7bf47ac2ce1d567c7abc7a640cb6b7ce0e905fbda14bcf9066ec28accf`. |

The 20 final editor screenshots use synthetic values only, with names and slugs
masked; no cookies, tokens, operation receipts, HAR/HTML dumps or storage state
are exported. AR/EN, RTL/LTR, 1440/390/320px and both themes are covered. Arabic
390px older choices, Arabic 320px invalid input and desktop 1440px controls were
visually inspected. Native Back/Continue are used on mobile, where top-section
links are intentionally hidden by the existing design.

CI keeps every existing invocation/assertion, credential scope, migration ordering
and cleanup. Existing core/HTTP/renderer/browser suites now contain the meaningful
new checks; JavaScript syntax additionally covers the expanded browser test.
The Profile private handoff/ordered cleanup introduced by PR 27 is untouched.
Final static verification covers compatibility/fingerprint byte comparison,
protected source/catalog/schema/gates, all old browser assertions, YAML/24 Bash
blocks, JavaScript/PowerShell syntax, links and complete patch reconstruction.
Full commands, logs, source hashes/manifests and result records are in the review
bundle. Task containers, databases, private logs/sessions/files, volumes, networks
and generated credential/carrier files are removed; the task image tag is removed
after recording its ID. Cached preexisting images/tooling are preserved.

## Retained failures and explicit gaps

Two editor attempts (`editor-browser-12e5f84c9bc5.log`,
`editor-browser-b0219e4d3f4f.log`) passed all nine existing groups but failed the
new catalog scenario: it tried the desktop-only hidden top-section navigation
at mobile width. The second safe diagnostic identified the shared jump helper's
line without printing private data. The added scenario now uses visible native
Back/Continue; no assertion or production CSS/design was weakened. Both failed
runs still completed fixture preservation and resource cleanup; both logs remain.
An initial image-inspection path used the Profile run ID rather than the editor
run ID; corrected inspection verified the actual final editor screenshots. This
was an artifact lookup error, not a test failure.
An initial static scope probe disabled Git's configured CRLF conversion and
therefore reported unchanged checkout files as modified. The corrected probe uses
the repository's normal diff semantics; the bounded change list is 20 files.
No unrelated source was changed to resolve that probe.

No failed/skipped test step remains in the final local runs. No remote CI/push,
real authentication/provider freshness, physical network/daemon fault injection,
real-device/Safari/screen-reader audit, deployment or live migration is claimed.
Existing production-like default-off smoke is Apache GET; strict GET/POST off-gate
behavior is exercised by the real HTTP suite. Chromium viewport/keyboard checks
are bounded automation, not accessibility certification. No new production
Linux ownership mechanism is introduced; the inherited CI handoffs remain.

Create, consent and visibility remain disabled; headline stays 200/800, slug stays
its existing incomplete 1–63 candidate syntax, optional fields remain uncollected,
and profile/Portfolio/publication/legacy routing are unchanged. Registration is
Google-only/default off; all gates remain default off and real policies unavailable.
Completion, final promotion, publication/cutover, policies/retention and remaining
owner decisions stay separate. No Auth0/Notion/canonical service/data operation.
