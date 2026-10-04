# Ather authentication and onboarding project record

**Authority and phase:** Owner-approved architecture, documentation expansion on 2026-10-04. This branch contains documentation only. The deployed product on main still has the legacy Owner sign-in and onboarding flow described in [current state](01-CURRENT-STATE.md). Proposed routes, schemas and screens in this directory are not implemented.

Ather is a broader professional platform, initially focused on students and graduates in Jordan. It preserves the story, decisions, role, evidence, lessons and impact behind work. The established green-and-gold identity carries into later design work. AI recommendations and recruiter/company accounts are future capabilities.

## Start here

Read current behavior before changing code; use the ADRs for approved choices, the threat model for failure modes, the data model and routes for proposed contracts, the implementation plan for sequencing, and the test and operations documents for gates. This directory supersedes the two Phase 0 files formerly under docs/architecture. Other documentation in that directory remains independent.

| File | Responsibility | Status | Last reviewed commit | Review date |
| --- | --- | --- | --- | --- |
| [README.md](README.md) | Navigation and maintenance contract | Approved | This documentation-expansion commit | 2026-10-04 |
| [01-CURRENT-STATE.md](01-CURRENT-STATE.md) | Verified baseline and limits | Verified | This documentation-expansion commit | 2026-10-04 |
| [02-ARCHITECTURE-DECISIONS.md](02-ARCHITECTURE-DECISIONS.md) | Stable owner-approved ADRs | Approved | This documentation-expansion commit | 2026-10-04 |
| [03-SECURITY-PRIVACY-THREAT-MODEL.md](03-SECURITY-PRIVACY-THREAT-MODEL.md) | Assets, boundaries and threats | Approved | This documentation-expansion commit | 2026-10-04 |
| [04-DATA-MODEL-AND-MIGRATIONS.md](04-DATA-MODEL-AND-MIGRATIONS.md) | Proposed schema and migration safety | Proposed | This documentation-expansion commit | 2026-10-04 |
| [05-USER-FLOWS-AND-ROUTES.md](05-USER-FLOWS-AND-ROUTES.md) | Proposed journeys and route contracts | Proposed | This documentation-expansion commit | 2026-10-04 |
| [06-IMPLEMENTATION-PLAN.md](06-IMPLEMENTATION-PLAN.md) | Phases and acceptance gates | Approved | This documentation-expansion commit | 2026-10-04 |
| [07-TEST-AND-ACCEPTANCE-MATRIX.md](07-TEST-AND-ACCEPTANCE-MATRIX.md) | Stable test requirements | Proposed | This documentation-expansion commit | 2026-10-04 |
| [08-OPERATIONS-ROLLBACK-AND-RECOVERY.md](08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) | Future delivery and recovery runbook | Proposed | This documentation-expansion commit | 2026-10-04 |
| [09-OFFICIAL-REFERENCES.md](09-OFFICIAL-REFERENCES.md) | Primary-source register | Verified | This documentation-expansion commit | 2026-10-04 |
| [CHANGELOG.md](CHANGELOG.md) | Durable phase and commit history | Approved | This documentation-expansion commit | 2026-10-04 |

“This documentation-expansion commit” identifies the containing commit without an impossible self-referential SHA. Its parent is 2c486f11de660a6587e8dd33e66b0ebfbe3ca5bd.

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

Auth0 Universal Login owns credentials; Ather owns local authorization and server-side sessions. Never infer identity ownership from email equality. Require verified email before Dashboard access, privileged MFA and recent step-up for security changes. Collect minimal data, keep drafts, portfolios, projects, contact data and Evidence private by default, and publish only by explicit owner action. Apply the same visibility decisions to HTML, JSON and media. Support Arabic and English, directionality, keyboard access and mobile layouts. Treat the 18+ public beta as provisional pending qualified Jordanian legal/privacy review.

**Maintenance contract:** Every future authentication/onboarding implementation commit must update every affected document in this directory and [CHANGELOG.md](CHANGELOG.md) in the same commit. A phase is incomplete if schema changes omit the data model, route changes omit flows, security decisions omit ADR/threat updates, gates omit tests, deployment changes omit operations, or this README does not show the current phase and next safe action. Preserve stable IDs; record supersession rather than silently repurposing them.

## Phase position and blockers

- Deployed main baseline: adbd1fd5772f6ce16b4844e29d0c6052e8eda221; current Owner product, migrations 001–013. Evidence and project aggregates remain protected baselines, not implementation evidence for new auth.
- Documentation branch: feat/auth-onboarding-foundation, local/unpushed. Phase 0 architecture decisions were committed at 2c486f11de660a6587e8dd33e66b0ebfbe3ca5bd; this expansion reorganizes their record without product changes.
- Last completed phase: Phase 0 documentation. Current phase: owner documentation review. Next safe phase: owner-accepted Phase 1 characterization tests, additive migration 014 and isolated identity-backfill rehearsal only.
- Known blockers for later phases: Auth0 plan/connection behavior and non-production validation; role/recovery and step-up policy details; policy text/version; legacy published-project backfill; slug retirement; independent accessibility measurement; qualified Jordanian legal/privacy review before public beta. The five owner-created legacy/test account records require a separate approved reconciliation operation.

This record is design guidance, not a claim of legal compliance or a claim that proposed functionality exists.
