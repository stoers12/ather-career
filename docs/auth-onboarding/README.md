# Ather authentication and onboarding project record

Card 08 bounded ONBOARD-UI-003 evidence follow-up: the integrated synthetic HTTP fixture now checks incomplete Dashboard/direct add GET routing, then follows the actual Dashboard Add Project href for a completed owner with zero projects and with one disposable fixture project. Add-mode controls, Projects empty-state presence/absence and whole-row preservation are asserted. No project-creation POST, banner, new route or production change; the sealed-legacy exception remains. Next: local review of this evidence slice; original Card 08 scope remains unresolved and Card 07 stays paused.

Card 08 first bounded acceptance slice: the existing registration/OIDC route suite now connects one synthetic registration-purpose callback to an initially empty draft, acknowledged HTTP saves, pause/resume, explicit document attestations, saved review/confirmation, receipt/Dashboard access and later Profile preservation. It reuses guarded private policy copies and disposable fixtures; production resolvers remain null and actual gates off. Original approved Card 08 scope was not located in the bounded local records reviewed: this is the owner's current provisional journey checklist plus existing accepted contracts, not a whole-card acceptance claim. See [focused acceptance coverage](07-TEST-AND-ACCEPTANCE-MATRIX.md). No production behavior or provider/browser investigation changes.

Card 05 owner-approved D1: [34 — authenticated entry D1](34-AUTHENTICATED-ENTRY-D1-HANDOFF.md) implements current-authorized signed-in presentations on signin/signup/direct owner_login, fixed explicit Dashboard/Resume or unavailable account actions. No authenticated visit starts OIDC or consumes its start budget. Anonymous entry and explicit Google registration remain; D2 public registration discovery/launch messaging is deferred. Production policies stay null and actual gates off. Next: owner review of local D1 evidence; provider/hosting/manual accessibility and controlled cutover remain separate, not launch-ready.

Card 05 bounded navigation successor: [33 — pause/resume and explicit Dashboard navigation](33-JOURNEY-NAVIGATION-UI-HANDOFF.md). Acknowledged Save and exit now reaches a private pause view; explicit Resume uses current server routing. Confirmed receipt recovery offers Dashboard without automatic navigation or suppressing unsaved-work protection. This finishes only these editor navigation gaps; public/account navigation review, real policies, controlled cutover preparation and activation remain separate. Production resolvers remain null, actual gates off. Next safe action: local review of this slice and its evidence.


Card 05 first bounded server slice: [32 — server-side journey routing](32-REGISTRATION-JOURNEY-ROUTING-HANDOFF.md). Frozen maintenance-only legacy bindings and correctly bound completion now control fixed Owner/editor/holding destinations; alternate bare first creation is retired. Local review/testing only. Production resolvers stay null, actual gates off; no capture/deployment/launch or completed Card 05 claim. Next: review evidence, then separately bounded navigation/UI and controlled cutover preparation.

Card 04 bounded editor completion: [31 — private final review and completion UI](31-COMPLETION-EDITOR-UI-HANDOFF.md). Final review now uses acknowledged saved server data, two explicit confirmations and the existing completion HTTP gate. Original receipt recovery is independent of current policies/readiness. Both production resolvers stay null, actual gates off; the guarded preview remains nonpersistent/Create-disabled. Next safe step: owner review of this local commit/evidence. No launch, routing/publication cutover or finished Card 04 is claimed.

Card 04 bounded completion transport: [30 — completion HTTP transport](30-COMPLETION-HTTP-HANDOFF.md). Private receipt recovery, saved review preparation and explicit confirmation are implemented locally behind an independent exact-`1`, default-off capability. Production policy/content resolvers remain literal null. No final-review/Create UI, routing/publication cutover or activation. Next safe action: review this local commit and its evidence; Card 04 remains incomplete.

**Isolated presentation update (2026-10-15):** PR #16 merged at `5e5196cedfa837fd9f3cf3e4a5e389c7ed836eaa` with exact-merge Main CI success. This local `feat/onboarding-steps3-5-20261015` batch extends the approved preview to all five visual sections. It preserves transient choices through step navigation and bounded same-page language switches, provides illustrative status/link/privacy controls and review edits, and leaves Create disabled. The fixture remains outside production routes. It records no consent, saves no draft, reserves no link, changes no visibility, and creates no portfolio. Phone and legal policy, functional gates, deployment, and owner acceptance of steps 3–5 remain separate.

**Focused local correction (2026-10-15):** Repeated pointer activation of Continue no longer consumes a second step. Review Edit returns through Continue to the review panel; a top-step jump or Back clears that return. Step-3 desktop status/specialization labels and controls align, with natural mobile stacking. Presentation-only limits and pending owner review remain.

**Step-2 visual follow-up (2026-10-15):** The owner reports that the earlier navigation and step-3 corrections work as expected. The country select and adjacent preferred-language cards now share a 60px control height in the Public identity preview; owner visual acceptance of this separate alignment correction is pending.

**Current status (2026-10-06):** Phase 2A is merged, deployed, and accepted after two intentional signed Owner callbacks. Canonical main is `e1cd58d41d9996d3a97df425e9fd098d98258fbb`; Migration 014 and five exact bindings remain. Phase 2B account selection is being developed only in an isolated branch; see [its scoped contract](11-PHASE2B-ACCOUNT-SELECTION.md). No Phase 2B runtime or Auth0 change is authorized. The older phase records below retain their historical dates.

Ather is a broader professional platform, initially focused on students and graduates in Jordan. It preserves the story, decisions, role, evidence, lessons and impact behind work. The established green-and-gold identity carries into later design work. AI recommendations and recruiter/company accounts are future capabilities.

## Start here

Card 04 bounded editor integration: [29 — private editor attestation UI](29-ATTESTATION-EDITOR-UI-HANDOFF.md).
Complete exact server documents, explicit actions and safe evidence recovery are implemented
locally for review. Production policies remain null; all capabilities remain default off.
Create/completion UI and HTTP, routing cutover and activation remain separate. Card 04 is not finished.

Card 04 first bounded slice: [28 — exact attestation HTTP presentation/recording](28-ATTESTATION-HTTP-HANDOFF.md).
Authenticated GET/POST and its public shim are implemented locally behind an exact-`1`,
default-off gate. Production bundle/content resolvers remain null: no approved legal
documents, usable production presentation, activation, final UI or completion route.
Next step is review of this local slice; real policies and journey integration remain separate.

Read current behavior before changing code; use the ADRs for approved choices, the threat model for failure modes, the data model and routes for proposed contracts, the implementation plan for sequencing, and the test and operations documents for gates. This directory supersedes the two Phase 0 files formerly under docs/architecture. Other documentation in that directory remains independent.

| File | Responsibility | Status | Last reviewed commit | Review date |
| --- | --- | --- | --- | --- |
| [README.md](README.md) | Navigation and maintenance contract | Approved | This documentation-expansion commit | 2026-10-04 |
| [01-CURRENT-STATE.md](01-CURRENT-STATE.md) | Verified baseline and limits | Verified | This documentation-expansion commit | 2026-10-04 |
| [02-ARCHITECTURE-DECISIONS.md](02-ARCHITECTURE-DECISIONS.md) | Stable owner-approved ADRs | Approved | This documentation-expansion commit | 2026-10-04 |
| [03-SECURITY-PRIVACY-THREAT-MODEL.md](03-SECURITY-PRIVACY-THREAT-MODEL.md) | Assets, boundaries and threats | Approved | This documentation correction | 2026-10-05 |
| [04-DATA-MODEL-AND-MIGRATIONS.md](04-DATA-MODEL-AND-MIGRATIONS.md) | Deployed 014 and proposed later schema | Deployed and verified | This documentation correction | 2026-10-05 |
| [05-USER-FLOWS-AND-ROUTES.md](05-USER-FLOWS-AND-ROUTES.md) | Proposed journeys and route contracts | Proposed | This documentation correction | 2026-10-05 |
| [06-IMPLEMENTATION-PLAN.md](06-IMPLEMENTATION-PLAN.md) | Phases and acceptance gates | Approved; Phase 1 deployed | This documentation correction | 2026-10-05 |
| [07-TEST-AND-ACCEPTANCE-MATRIX.md](07-TEST-AND-ACCEPTANCE-MATRIX.md) | Stable test requirements and scoped evidence | Scoped verification recorded | This documentation correction | 2026-10-05 |
| [08-OPERATIONS-ROLLBACK-AND-RECOVERY.md](08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) | Recovery and canonical bind-mount runbook | Maintenance boundary recorded | This documentation correction | 2026-10-05 |
| [09-OFFICIAL-REFERENCES.md](09-OFFICIAL-REFERENCES.md) | Primary-source register | Verified | This documentation-expansion commit | 2026-10-04 |
| [10-PHASE2A-CALLBACK-RESOLUTION.md](10-PHASE2A-CALLBACK-RESOLUTION.md) | Local callback cutover contract and recovery boundary | Verified locally; runtime corrected | This documentation correction | 2026-10-05 |
| [11-PHASE2B-ACCOUNT-SELECTION.md](11-PHASE2B-ACCOUNT-SELECTION.md) | Local session switch and fixed account chooser contract | Isolated review branch | Phase 2B local implementation | 2026-10-06 |
| [CHANGELOG.md](CHANGELOG.md) | Durable phase and commit history | Approved | This documentation correction | 2026-10-05 |

“This documentation-expansion commit” identifies the containing commit without an impossible self-referential SHA. Its parent is 2c486f11de660a6587e8dd33e66b0ebfbe3ca5bd.

Local persistence-only follow-up: [18 — onboarding-attestation persistence
handoff](18-ONBOARDING-ATTESTATION-PERSISTENCE-HANDOFF.md). Migration 015 is narrow;
policy availability and registration activation remain off.

Local private draft follow-up: [19 — onboarding draft persistence/resume
handoff](19-ONBOARDING-DRAFT-PERSISTENCE-HANDOFF.md). Migration 016 is persistence
only, without completion/profile/route integration or policy activation.

Local HTTP follow-up: [20 — authenticated onboarding draft HTTP
handoff](20-ONBOARDING-DRAFT-HTTP-HANDOFF.md). The JSON resume/save boundary is
default off and independent of registration; onboarding UI/completion remain deferred.

Local editor follow-up: [21 — private onboarding draft editor](21-ONBOARDING-DRAFT-EDITOR-HANDOFF.md).
The approved design now supports authenticated explicit save/resume behind the same
default-off gate; completion, consent, publication and final catalogs remain deferred.

Completion review: [22 — bounded onboarding completion contract](22-ONBOARDING-COMPLETION-CONTRACT.md).
Documentation-only recommendations await owner acceptance; the lifecycle revision covers all
14 draft fields through current profile editing and publication. The proposed first slice is
profile save preservation/concurrency; no completion behavior is enabled.

Bounded R11/R12 implementation: [23 — profile preservation and concurrency](23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md).
Only profile versioning, scoped saves, omission/visibility preservation and media outcome handling
are integrated locally; completion, publication/routing changes and activation remain deferred.

Bounded optional final facts: [24 — private final-profile fields](24-FINAL-PROFILE-FIELDS-HANDOFF.md).
Four nullable final columns, candidate catalogs/strict validators and existing Profile controls
are developed together locally. They remain private and independent of onboarding drafts,
completion/publication and gate activation; catalog labels/meanings remain owner-review items.

Bounded draft catalog integration: [25 — Profile catalogs in the authenticated editor](25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md).
New edits reuse Profile rules; stored historical candidates and exact retry receipts remain
compatible. Existing native controls and guarded preview remain. Completion/policies/activation
stay unavailable; the next safe action is owner review of this local slice.

Bounded first completion: [27 — atomic core and durable receipt](27-FIRST-COMPLETION-CORE-HANDOFF.md).
The owner accepted only the explicit overlay in contract 22. Production policies remain unavailable;
no completion HTTP/UI, routing cutover or publication activation exists. Next step is local owner review.

## Status vocabulary

Owner-approved country amendment: [26 — ordered launch catalog](26-LAUNCH-COUNTRY-CATALOG.md).
Profile and private draft new edits support exactly `JO PS SA AE QA KW BH OM IQ LB SY YE EG`.
Full 249-entry ISO provenance and historical countries/receipts remain preserved.
Completion and activation remain separate from this catalog-only commit.

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

**Maintenance contract:** Every future authentication/onboarding implementation commit must update every affected document in this directory and [CHANGELOG.md](CHANGELOG.md) in the same commit, unless the owner explicitly requests a separate review and documentation follow-up, as for `bd7510c`. A phase is incomplete if schema changes omit the data model, route changes omit flows, security decisions omit ADR/threat updates, gates omit tests, deployment changes omit operations, or this README does not show the current phase and next safe action. Preserve stable IDs; record supersession rather than silently repurposing them.

## Phase position and blockers

- Deployed baseline: main `43b36f3811ea64c23687b84d8b45327a88cf169d`; canonical ledger 001–014 with five exact legacy bindings. Phase 0 decisions are at `2c486f11de660a6587e8dd33e66b0ebfbe3ca5bd` and `d3fd2a00f536657718da0cc38c1b256356889a2a`; Phase 1 implementation is at `bd7510c00181957fd9f1d4c3ceb3ee4789738c0e`.
- Isolated Phase 2A branch: `feat/auth-callback-identity-resolution`, local and unpushed. It requires the verified 014 table and removes the candidate callback's unknown-user creation path. The bind-mounted canonical checkout briefly exposed this code during development; retained logs showed no callback request or successful Owner authentication in the observed interval. Maintenance restored canonical main. Registration, linking, provider and tenant changes remain disabled. Next safe action: owner review of the corrected boundary, then a separately approved cutover plan. Auth0 capability testing remains a later gate.
- Known blockers for later phases: Auth0 plan/connection behavior and non-production validation; role/recovery and step-up policy details; policy text/version; legacy published-project backfill; slug retirement; independent accessibility measurement; qualified Jordanian legal/privacy review before public beta. The five owner-created legacy/test account records require a separate approved reconciliation operation.

This record is design guidance, not a claim of legal compliance or a claim that proposed functionality exists.
