# Owner-approved launch country subset

Base: merged PR 28, `fd116782128e9cadf1e9ce017dca90d1495c4b39`; remote main
was fetched and matched before isolation. Worktree `.worktrees/onboarding-completion-core`,
branch `feat/onboarding-completion-core-20261019`. Canonical HEAD and its unrelated
required-gates edit are preserved. This catalog correction is committed separately
before the completion core; no earlier core work was discarded.

The owner's amendment supersedes the all-249 product offering in handoffs 24/25
and the original R3 recommendation. Current product revision is
`ather-profile-launch-2026-10-19-r2`. Exact supported order:

`JO PS SA AE QA KW BH OM IQ LB SY YE EG`

Jordan is first, not selected/inferred automatically. All 13 ISO codes and exact
pinned primary AR/EN labels are reused. Language remains ar/en; the six status
tokens remain unchanged. Country means professional location, not nationality,
eligibility or an IP/geolocation restriction. Status grants no authority.

The source ISO snapshot still has 249 assigned entries; the generated product
catalog has 13. Full pinned ISO/CLDR sources, releases, notices and source hashes
are unchanged. Generator verifies the full source, then the exact approved ordered
subset, labels and reviewed checksum. See [provenance](../../data/profile-catalog/README.md).
Generated SHA-256: `9ea1bd7f2e9ec1223a905a95509ebc43f4b9cc5f3bce471d35bb1d8fe9116f07`.

Profile and authenticated editor use the same generated catalog and validators.
Older unsupported countries remain escaped/selected and explicitly labeled as
older saved choices. Profile's byte-identical old choice is omitted from its patch;
draft omission preserves the old value. New draft operations cannot resubmit an
unsupported country, but previously accepted exact receipts are honored after
normal authorization and structural validation. No stored data/fingerprint is
rewritten. Null/explicit optional clear remains deliberate. First completion must
use a supported country; it cannot use preservation as new-completion validation.

Tests now seed formerly supported `US`, not merely an invented ISO-shaped code,
and cover exact old country receipts, unchanged/omitted preservation, replacement,
clearing, rejected new US/CA choices, exact order and no default selection. Existing
assertions remain, with supported positive examples changed to launch countries.
The guarded design preview, authentication, owner access and public projections
are unchanged. No schema migration is required for this catalog correction.

Actual local validation before the separate catalog commit:

- Offline generator `--check`: exact ordered 13-country output/hash, all pinned
  sources/notices unchanged; no network downloads.
- `validation-d5d26ac1eed5.log`: all 17 ordered rehearsal commands, Profile and
  final-field core/HTTP, 11 draft-core/10 draft-HTTP/three renderer groups, 572
  preview and registration/attestation/routing/foundation/readiness regressions pass.
- `editor-browser-7b69363eb3bc.log`: 11 browser groups; matching fixture log proves
  preservation/private logs/process/session cleanup, with 20 redacted synthetic images.
- `browser-649df3545115.log`: five Profile browser groups including approved order,
  older US choice and later editing; matching fixture final snapshot/cleanup passes.
- Generated credentials, disposable containers/networks/volumes and carriers are
  removed by all harnesses. No remote CI or production execution is claimed here.

Retained first run `validation-2a75ad551ad0.log` rejected the older positive `NZ`
fixture at the first draft save. Replaced that example with supported `KW`, retaining
the assertion and all old-value compatibility tests. Final full run passes. An
external preparation script initially used Windows' default text encoding; corrected
to explicit UTF-8 before generating data. Neither required weaker validation.
Final completion-core validation and review bundle are a later distinct local commit.

No push/PR/merge/deployment/live migration/activation/real authentication,
Auth0/Notion or canonical service/data operation is part of this work.
