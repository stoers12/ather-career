# Phase 2B prompt=login experiment

**Historical completion clarification (recorded 2026-10-22):** The candidate subsequently merged through PR #7. The temporary-activation statements below describe the earlier experiment only; see [finalization evidence](#finalization-evidence-2026-10-09-utc). This correction does not repeat that experiment or describe the present runtime.

## Historical experiment checkpoint — 2026-10-09

Historical status at this checkpoint: isolated implementation candidate; temporarily active in the private E: environment under owner authorization. No merge or permanent deployment had yet occurred.

The earlier deployed switch and recovery retry sent `prompt=select_account`. In the October 9 owner recording, Auth0 immediately showed consent for Momen without an account chooser. The isolated candidate changes only those two call sites to `prompt=login` and permits `login` in the existing OIDC prompt allowlist. Ordinary login remains without a prompt. CSRF, local session retirement and rotation, rate limiting, discovery validation, state, nonce, PKCE, callback validation, and exact issuer/subject identity resolution are unchanged.

On the first temporary activation, the owner saw immediate consent for Momen. The original three files were restored. The same three-line candidate was then reactivated for one repeat with Edge Network capture requested before navigation. The owner reports that the other known account showed no projects, and returning to Momen displayed four projects. A later direct, read-only lookup of the existing Edge History database found five Auth0 `/authorize` visits between 22:50:16 and 22:56:30 UTC with `prompt=login`; two adjacent ordinary-login visits had no prompt. No complete authorization URL was displayed or persisted. History confirms the recorded navigation parameter in this window, but is not a complete Network trace and does not expose Auth0-to-provider parameters or Auth0 session decisions.

A final SELECT-only reconciliation found migration ledger 001–014; five Users, five distinct exact identity bindings with no mismatch against legacy pairs, five portfolios with distinct owners and no orphan; the protected four-project portfolio with Evidence 12/12, four complete projects, 27 technology occurrences, 24 distinct mapped technologies, and zero unmapped occurrences. Its five referenced original images were readable. Protected row and media fingerprints were computed for the current state, but no pre-experiment fingerprints are available here for byte-for-byte comparison. These checks do not replace the owner's cross-account UI observations.

Keep the temporary E: activation distinct from this branch commit. Any permanent merge or deployment requires separate owner approval. The owner-visible account switch and return are accepted as owner-reported browser evidence, with the actual Auth0 navigation prompt corroborated by Edge History. No additional browser attempt is needed for this result.

## Finalization evidence (2026-10-09 UTC)

This retrospective section was recorded on 2026-10-22 to preserve the useful historical material from [Draft PR #8](https://github.com/stoers12/ather-career/pull/8), head `673e5373ecdaccbc7f5570c917bfade49ab2d136`, without merging its obsolete current-state and next-action text. It supersedes only the checkpoint's pending-merge/permanent-alignment status. PR #8 itself is not represented as merged.

### Public repository facts checked for this correction

- [PR #7](https://github.com/stoers12/ather-career/pull/7) merged approved head `d00198329c7443ea613f4f1f2ed59ff71d8b834e` at `82353dc2d2a709858d0b56852182acce20f06009` on 2026-10-09.
- [Main CI run 37979181209](https://github.com/stoers12/ather-career/actions/runs/37979181209) completed successfully with that exact merge as `head_sha`. Run/job/step metadata were read for this correction; the full historical execution logs were not reread and no jobs were rerun.
- The PR #7 change is fixed `prompt=login` for switch/retry; ordinary login remains unprompted. The known-account acceptance in its description is owner-reported evidence, not a newly performed experiment.

### Retained historical evidence and limits

| Evidence | Historical record | Limit / provenance |
| --- | --- | --- |
| Known-account switch and return | Another known account showed no projects; returning to Momen showed four | Owner-reported result retained in PR #7 and the experiment above; not repeated here and not a complete provider matrix |
| Authorization navigation | Edge History corroborated `prompt=login` on switch/retry visits and adjacent unprompted ordinary login | History is not a complete Network trace and does not establish Auth0-to-provider parameters, fresh upstream credentials or claim refresh |
| Permanent alignment and preservation | PR #8 reports the later finalization handoff aligned the private checkout to the merge, preserved runtime PHP bytes and protected database/media fingerprints, and retained recovery artifacts | Attributed to the earlier Agent handoff as summarized in PR #8; the private originals were not read or independently revalidated for this correction. No recovery paths or private manifests are reproduced |
| Review exception | PR #8 records an [owner exception for PR #7](https://github.com/stoers12/ather-career/pull/7#issuecomment-6087592180) at the approved head above; Copilot stopped before analysis with quota HTTP402 and Sourcery was skipped | This is the retained record, not a new check of those review logs. Neither review is called passed; no exception transfers to PR #8, this correction or another head |

The earlier pre-alignment observations remain as recorded; the later finalization account does not invent an older full-row or profile-image baseline. The old scoped account-switch fix is complete in the historical record. It did not close broader registration, account recovery, MFA, linking, onboarding or provider-freshness requirements.

Later implementations have separate contracts, including [authenticated entry D1](34-AUTHENTICATED-ENTRY-D1-HANDOFF.md) and [standard logout](CARD07-STANDARD-LOGOUT.md). The old description of local-only logout is not the current logout contract. The recovered Landing reference is already preserved in [LANDING-IMPLEMENTATION.md](../../LANDING-IMPLEMENTATION.md); the old instruction to start Landing is not reinstated. No runtime, migration, activation, provider result or current whole-project acceptance is asserted by this historical documentation correction.
