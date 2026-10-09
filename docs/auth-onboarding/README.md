# Ather authentication and onboarding project record

**Current status (2026-10-09 UTC):** Phase 2A is merged, deployed and accepted after two intentional signed Owner callbacks. Phase 2B account switching is permanently finalized through [PR #7](https://github.com/stoers12/ather-career/pull/7) at approved main `82353dc2d2a709858d0b56852182acce20f06009`. [Main CI run 37979181209](https://github.com/stoers12/ather-career/actions/runs/37979181209) passed on that exact merge SHA. The temporary E: edits have been finalized; see [finalization evidence and limits](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md#finalization-evidence-2026-10-09-utc). Landing, full registration/verification/recovery journeys, MFA, identity linking and five-step onboarding remain incomplete. Migrations 015–017 remain proposals. Next: locate the approved Landing references before any implementation.

Ather is a broader professional platform, initially focused on students and graduates in Jordan. It preserves the story, decisions, role, evidence, lessons and impact behind work. The established green-and-gold identity carries into later design work. AI recommendations and recruiter/company accounts are future capabilities.

## Start here

Read current behavior before changing code; use the ADRs for approved choices, the threat model for failure modes, the data model and routes for proposed contracts, the implementation plan for sequencing, and the test and operations documents for gates. This directory supersedes the two Phase 0 files formerly under docs/architecture. Other documentation in that directory remains independent.

| File | Responsibility | Status | Last reviewed commit | Review date |
| --- | --- | --- | --- | --- |
| [README.md](README.md) | Navigation, current status and next action | Finalization recorded | `82353dc` | 2026-10-09 UTC |
| [01-CURRENT-STATE.md](01-CURRENT-STATE.md) | Implemented behavior and remaining limits | Verified scoped baseline | `82353dc` | 2026-10-09 UTC |
| [02-ARCHITECTURE-DECISIONS.md](02-ARCHITECTURE-DECISIONS.md) | Stable owner-approved ADRs | Approved; implementation boundary clarified | `82353dc` | 2026-10-09 UTC |
| [03-SECURITY-PRIVACY-THREAT-MODEL.md](03-SECURITY-PRIVACY-THREAT-MODEL.md) | Assets, boundaries and threats | Scoped controls recorded | `82353dc` | 2026-10-09 UTC |
| [04-DATA-MODEL-AND-MIGRATIONS.md](04-DATA-MODEL-AND-MIGRATIONS.md) | Deployed 014 and proposed later schema | 014 deployed; 015–017 proposed | `82353dc` | 2026-10-09 UTC |
| [05-USER-FLOWS-AND-ROUTES.md](05-USER-FLOWS-AND-ROUTES.md) | Implemented routes and proposed journey contracts | Separated by implementation status | `82353dc` | 2026-10-09 UTC |
| [06-IMPLEMENTATION-PLAN.md](06-IMPLEMENTATION-PLAN.md) | Phases and acceptance gates | 2A/2B accepted; broader Phase 2 open | `82353dc` | 2026-10-09 UTC |
| [07-TEST-AND-ACCEPTANCE-MATRIX.md](07-TEST-AND-ACCEPTANCE-MATRIX.md) | Stable requirements and scoped evidence | Finalization recorded; broader gates open | `82353dc` | 2026-10-09 UTC |
| [08-OPERATIONS-ROLLBACK-AND-RECOVERY.md](08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) | Recovery and canonical bind-mount boundary | Finalized deployment recorded | `82353dc` | 2026-10-09 UTC |
| [09-OFFICIAL-REFERENCES.md](09-OFFICIAL-REFERENCES.md) | Primary-source register | Historical register retained unchanged | `82353dc` | 2026-10-09 UTC |
| [10-PHASE2A-CALLBACK-RESOLUTION.md](10-PHASE2A-CALLBACK-RESOLUTION.md) | Callback cutover and recovery boundary | Merged, deployed and accepted | `82353dc` | 2026-10-09 UTC |
| [11-PHASE2B-ACCOUNT-SELECTION.md](11-PHASE2B-ACCOUNT-SELECTION.md) | Local switch and recovery retry contract | Permanently finalized through PR #7 | `82353dc` | 2026-10-09 UTC |
| [12-PHASE2B-ACCOUNT-SELECTION-REDIRECT-INVESTIGATION.md](12-PHASE2B-ACCOUNT-SELECTION-REDIRECT-INVESTIGATION.md) | Historical CSP investigation and activation plan | Superseded by deployed fixes | `82353dc` | 2026-10-09 UTC |
| [13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md) | Historical experiment and finalization evidence | Accepted and permanently finalized | `82353dc` | 2026-10-09 UTC |
| [CHANGELOG.md](CHANGELOG.md) | Durable phase and commit history | Finalization recorded | `82353dc` | 2026-10-09 UTC |

All 15 documents were reviewed against approved main and the finalization record; only affected sections were edited. The reference register retains its 2026-10-04 access date; this review does not claim new source or tenant validation. Earlier “this commit” entries identify their containing historical commit.

## Status vocabulary

| Status | Meaning |
| --- | --- |
| Proposed | Contract or plan awaiting implementation or a specific approval. |
| Approved | Owner-approved decision; still may need engineering validation. |
| In progress | Work started in a reviewed implementation slice. |
| Implemented | Behavior or schema exists in code; verification gate may remain. |
| Verified | Evidence was checked for the stated scope and date. |
| Deferred | Explicitly outside the current rollout. |
| Blocked | A required decision, capability or safety gate prevents progress. |

## Product and security principles

Auth0 Universal Login owns credentials; Ather owns local authorization and server-side sessions. Never infer identity ownership from email equality. The approved future gates require verified email before Dashboard access, privileged MFA and recent step-up for security changes; those gates are not completed by 2A/2B. Collect minimal data, keep drafts, portfolios, projects, contact data and Evidence private by default, and publish only by explicit owner action. Apply the same visibility decisions to HTML, JSON and media. Support Arabic and English, directionality, keyboard access and mobile layouts. Treat the 18+ public beta as provisional pending qualified Jordanian legal/privacy review.

**Maintenance contract:** Every future authentication/onboarding implementation commit must update every affected document in this directory and [CHANGELOG.md](CHANGELOG.md) in the same commit, unless the owner explicitly requests a separate review and documentation follow-up, as for `bd7510c`. A phase is incomplete if schema changes omit the data model, route changes omit flows, security decisions omit ADR/threat updates, gates omit tests, deployment changes omit operations, or this README does not show the current phase and next safe action. Preserve stable IDs; record supersession rather than silently repurposing them.

## Phase position and blockers

- Deployed baseline: approved main `82353dc2d2a709858d0b56852182acce20f06009`; ledger 001–014 and five exact identity bindings. Phase 2A removed unknown-user creation from the callback and is accepted. Phase 2B includes the switch/retry input protections, deployed CSP redirect fix and finalized `prompt=login` change. Ordinary login remains unprompted; local logout remains local-only.
- Acceptance and preservation: the owner switched to another known account with no projects, returned to Momen and saw four projects. Agent finalization evidence reports clean E: at the merge SHA, matching runtime hashes, three healthy services, unchanged database tables 10/10 and storage files 13/13. Four project originals match historical recovery hashes; the fifth current reference is a profile original with no older profile baseline claimed. Browser History corroborates `prompt=login`, not a complete Network trace. [13](13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md#finalization-evidence-2026-10-09-utc) records provenance and limits.
- Review exception: [the PR #7 exact-head owner exception](https://github.com/stoers12/ather-career/pull/7#issuecomment-6087592180) covers only `d00198329c7443ea613f4f1f2ed59ff71d8b834e`. Copilot failed before analysis with HTTP 402 and Sourcery was skipped; neither passed. The exception does not carry to this documentation PR or any other head.
- Next safe action: locate the approved Landing references and confirm their intended scope before implementation. This documentation task does not begin Landing work or close the broader account/security phase.
- Known blockers for later phases: Auth0 plan/connection behavior and non-production validation; role/recovery and step-up policy details; policy text/version; legacy published-project backfill; slug retirement; independent accessibility measurement; qualified Jordanian legal/privacy review before public beta. The five owner-created legacy/test account records require a separate approved reconciliation operation.

This record is design guidance, not a claim of legal compliance or a claim that proposed functionality exists.
