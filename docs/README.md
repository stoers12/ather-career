# Documentation map

Use living references for maintained guidance, architecture decisions/contracts for approved choices, and historical records for the evidence of a dated implementation or review. A historical record does not establish current deployment or launch readiness.

This index lists every other file under this directory exactly once. Some historical records stay outside the archive because tracked files elsewhere reference them; the authentication/onboarding changelog also stays in place.

Documents marked “classification review” mix current guidance with older scope or evidence. They are conservatively retained as Living reference for Owner review.

## Living reference

- [EVIDENCE_HUB_0_LITE.md](EVIDENCE_HUB_0_LITE.md) — Authoritative Evidence Hub rules, versioned contracts and private Owner boundary. (kept in place — referenced from `tests/phase2/cases/EvidenceHubContractTest.php`, `AGENTS.md`)
- [HTTP_CONTRACTS.md](HTTP_CONTRACTS.md) — Active HTTP methods, validation, redirects and public JSON contracts. (kept in place — referenced from `tests/phase2/cases/PublicProjectJsonContractTest.php`)
- [P2J09_AUTH0_SETUP.md](P2J09_AUTH0_SETUP.md) — Auth0 application configuration and real-provider verification prerequisites.
- [PHASE2_OWNERSHIP_MIGRATION.md](PHASE2_OWNERSHIP_MIGRATION.md) — Ownership migration order and disposable rehearsal procedure. (classification review — retained as Living reference)
- [PHASE2_TESTING.md](PHASE2_TESTING.md) — Phase 2 foundation invocation, disposable namespaces and safety rules.
- [PRODUCTION_RUNBOOK.md](PRODUCTION_RUNBOOK.md) — Production operation, configuration, recovery links and failure handling.
- [STAGE2C_OWNERSHIP_AND_INTEGRITY.md](STAGE2C_OWNERSHIP_AND_INTEGRITY.md) — Owner authority, transactions and tenant integrity boundaries.
- [STAGE3A_OWNER_EXPERIENCE.md](STAGE3A_OWNER_EXPERIENCE.md) — Owner workspace forms, feedback and progressive-enhancement conventions.
- [STAGE3B_RESPONSIVE_ACCESSIBILITY.md](STAGE3B_RESPONSIVE_ACCESSIBILITY.md) — Responsive layout, focus and accessibility engineering conventions.
- [STAGE3C_PERFORMANCE_RESILIENCE.md](STAGE3C_PERFORMANCE_RESILIENCE.md) — Current delivery/resilience conventions with dated local performance evidence. (classification review — retained as Living reference)
- [STAGE4A_RUNTIME_OBSERVABILITY.md](STAGE4A_RUNTIME_OBSERVABILITY.md) — Runtime topology, health/readiness probes and structured event contract.
- [STAGE4B_EDGE_SECURITY.md](STAGE4B_EDGE_SECURITY.md) — Response security policy, proxy trust and abuse-protection boundaries.
- [STAGE4C_RECOVERY_OPERATIONS.md](STAGE4C_RECOVERY_OPERATIONS.md) — Recovery formats, backup/restore safety and operational-security procedures. (kept in place — referenced from `tests/phase2/cases/OperationalRecoveryStaticTest.php`)
- [auth-onboarding/01-CURRENT-STATE.md](auth-onboarding/01-CURRENT-STATE.md) — Maintained authentication/onboarding baseline and scoped successor notes.
- [auth-onboarding/03-SECURITY-PRIVACY-THREAT-MODEL.md](auth-onboarding/03-SECURITY-PRIVACY-THREAT-MODEL.md) — Threats, trust boundaries, mitigations and release blockers.
- [auth-onboarding/04-DATA-MODEL-AND-MIGRATIONS.md](auth-onboarding/04-DATA-MODEL-AND-MIGRATIONS.md) — Current schema additions, historical proposals and migration constraints.
- [auth-onboarding/05-USER-FLOWS-AND-ROUTES.md](auth-onboarding/05-USER-FLOWS-AND-ROUTES.md) — Authentication, onboarding and publication route responsibilities.
- [auth-onboarding/06-IMPLEMENTATION-PLAN.md](auth-onboarding/06-IMPLEMENTATION-PLAN.md) — Phased implementation sequence, dependencies and acceptance boundaries.
- [auth-onboarding/07-TEST-AND-ACCEPTANCE-MATRIX.md](auth-onboarding/07-TEST-AND-ACCEPTANCE-MATRIX.md) — Stable acceptance requirements and scoped integration evidence.
- [auth-onboarding/08-OPERATIONS-ROLLBACK-AND-RECOVERY.md](auth-onboarding/08-OPERATIONS-ROLLBACK-AND-RECOVERY.md) — Maintenance/cutover constraints, recovery and canonical mount boundary.
- [auth-onboarding/09-OFFICIAL-REFERENCES.md](auth-onboarding/09-OFFICIAL-REFERENCES.md) — Primary-source register and procedure for reviewing design evidence.
- [auth-onboarding/11-PHASE2B-ACCOUNT-SELECTION.md](auth-onboarding/11-PHASE2B-ACCOUNT-SELECTION.md) — Account-switch contract with historical completion and successor notes. (classification review — retained as Living reference)
- [auth-onboarding/14-OWNER-PORTFOLIO-CREATION-READINESS.md](auth-onboarding/14-OWNER-PORTFOLIO-CREATION-READINESS.md) — Earlier creation-readiness policy; consult current routing successors. (classification review — retained as Living reference)
- [auth-onboarding/README.md](auth-onboarding/README.md) — Authentication/onboarding navigation, maintenance rules and scoped status.

## Architecture decision / contract

- [auth-onboarding/02-ARCHITECTURE-DECISIONS.md](auth-onboarding/02-ARCHITECTURE-DECISIONS.md) — Owner-approved authentication/onboarding ADRs and explicit non-goals.
- [auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md](auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md) — Accepted bounded completion overlay, receipt and Profile lifecycle contract.
- [auth-onboarding/26-LAUNCH-COUNTRY-CATALOG.md](auth-onboarding/26-LAUNCH-COUNTRY-CATALOG.md) — Approved ordered launch-country subset and historical-choice compatibility. (kept in place — referenced from `data/profile-catalog/README.md`)

## Historical record

- [EVIDENCE_HUB_TEXT_110_CALIBRATION.md](EVIDENCE_HUB_TEXT_110_CALIBRATION.md) — Historical implementation, review or verification record: EH-06 synthetic calibration.
- [LANDING-IMPLEMENTATION.md](LANDING-IMPLEMENTATION.md) — Historical implementation, review or verification record: Approved Landing implementation — review record. (kept in place — referenced from `landing.css`)
- [P2J08_IMAGE_CALIBRATION.md](P2J08_IMAGE_CALIBRATION.md) — Historical implementation, review or verification record: P2J-08 image calibration.
- [RECOVERY-PRESENTATION-IMPLEMENTATION.md](RECOVERY-PRESENTATION-IMPLEMENTATION.md) — Historical implementation, review or verification record: Forgot and Reset Password presentations — 2026-10-14.
- [SIGNIN-IMPLEMENTATION.md](SIGNIN-IMPLEMENTATION.md) — Historical implementation, review or verification record: Approved Sign In implementation — 2026-10-10. (kept in place — referenced from `signin.css`)
- [SIGNUP-VERIFICATION-IMPLEMENTATION.md](SIGNUP-VERIFICATION-IMPLEMENTATION.md) — Historical implementation, review or verification record: Sign Up and email verification presentation — 2026-10-11.
- [VERIFICATION-ERROR-DISABLED-IMPLEMENTATION.md](VERIFICATION-ERROR-DISABLED-IMPLEMENTATION.md) — Historical implementation, review or verification record: Verification error and disabled-account presentations — 2026-10-12.
- [VERIFICATION-RESENT-EXPIRED-IMPLEMENTATION.md](VERIFICATION-RESENT-EXPIRED-IMPLEMENTATION.md) — Historical implementation, review or verification record: Verification resent and expired presentation — 2026-10-12.
- [auth-onboarding/10-PHASE2A-CALLBACK-RESOLUTION.md](auth-onboarding/10-PHASE2A-CALLBACK-RESOLUTION.md) — Historical implementation, review or verification record: Phase 2A — Owner callback identity resolution.
- [auth-onboarding/12-PHASE2B-ACCOUNT-SELECTION-REDIRECT-INVESTIGATION.md](auth-onboarding/12-PHASE2B-ACCOUNT-SELECTION-REDIRECT-INVESTIGATION.md) — Historical implementation, review or verification record: Phase 2B account-selection redirect investigation.
- [auth-onboarding/13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md](auth-onboarding/13-PHASE2B-PROMPT-LOGIN-EXPERIMENT.md) — Historical implementation, review or verification record: Phase 2B prompt=login experiment.
- [auth-onboarding/15-OWNER-READINESS-ENFORCEMENT-HANDOFF.md](auth-onboarding/15-OWNER-READINESS-ENFORCEMENT-HANDOFF.md) — Historical implementation, review or verification record: Owner creation readiness slice handoff — 2026-10-17.
- [auth-onboarding/16-REGISTRATION-CORE-HANDOFF.md](auth-onboarding/16-REGISTRATION-CORE-HANDOFF.md) — Historical implementation, review or verification record: Registration core handoff — 2026-10-17.
- [auth-onboarding/17-REGISTRATION-ROUTING-HANDOFF.md](auth-onboarding/17-REGISTRATION-ROUTING-HANDOFF.md) — Historical implementation, review or verification record: Purpose-bound registration routing handoff — 2026-10-17.
- [auth-onboarding/18-ONBOARDING-ATTESTATION-PERSISTENCE-HANDOFF.md](auth-onboarding/18-ONBOARDING-ATTESTATION-PERSISTENCE-HANDOFF.md) — Historical implementation, review or verification record: Versioned onboarding-attestation persistence core — 2026-10-17.
- [auth-onboarding/19-ONBOARDING-DRAFT-PERSISTENCE-HANDOFF.md](auth-onboarding/19-ONBOARDING-DRAFT-PERSISTENCE-HANDOFF.md) — Historical implementation, review or verification record: Private onboarding draft persistence/resume core — 2026-10-18.
- [auth-onboarding/20-ONBOARDING-DRAFT-HTTP-HANDOFF.md](auth-onboarding/20-ONBOARDING-DRAFT-HTTP-HANDOFF.md) — Historical implementation, review or verification record: Authenticated onboarding draft HTTP boundary — 2026-10-18.
- [auth-onboarding/21-ONBOARDING-DRAFT-EDITOR-HANDOFF.md](auth-onboarding/21-ONBOARDING-DRAFT-EDITOR-HANDOFF.md) — Historical implementation, review or verification record: Private onboarding draft editor — 2026-10-18.
- [auth-onboarding/23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md](auth-onboarding/23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md) — Historical implementation, review or verification record: Bounded profile preservation and concurrency — 2026-10-18.
- [auth-onboarding/24-FINAL-PROFILE-FIELDS-HANDOFF.md](auth-onboarding/24-FINAL-PROFILE-FIELDS-HANDOFF.md) — Historical implementation, review or verification record: Optional private final-profile fields — bounded local handoff.
- [auth-onboarding/25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md](auth-onboarding/25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md) — Historical implementation, review or verification record: Authenticated onboarding editor / Profile catalog integration. (kept in place — referenced from `data/profile-catalog/README.md`)
- [auth-onboarding/27-FIRST-COMPLETION-CORE-HANDOFF.md](auth-onboarding/27-FIRST-COMPLETION-CORE-HANDOFF.md) — Historical implementation, review or verification record: Atomic first completion and durable receipt — local core only.
- [auth-onboarding/28-ATTESTATION-HTTP-HANDOFF.md](auth-onboarding/28-ATTESTATION-HTTP-HANDOFF.md) — Historical implementation, review or verification record: Card 04 — exact attestation HTTP presentation and recording.
- [auth-onboarding/29-ATTESTATION-EDITOR-UI-HANDOFF.md](auth-onboarding/29-ATTESTATION-EDITOR-UI-HANDOFF.md) — Historical implementation, review or verification record: Card 04 — bounded private editor attestation UI.
- [auth-onboarding/30-COMPLETION-HTTP-HANDOFF.md](auth-onboarding/30-COMPLETION-HTTP-HANDOFF.md) — Historical implementation, review or verification record: Card 04 — bounded authenticated completion HTTP transport.
- [auth-onboarding/31-COMPLETION-EDITOR-UI-HANDOFF.md](auth-onboarding/31-COMPLETION-EDITOR-UI-HANDOFF.md) — Historical implementation, review or verification record: Card 04 — bounded private editor completion review and recovery.
- [auth-onboarding/32-REGISTRATION-JOURNEY-ROUTING-HANDOFF.md](auth-onboarding/32-REGISTRATION-JOURNEY-ROUTING-HANDOFF.md) — Historical implementation, review or verification record: Card 05 — bounded server-side registration/journey routing.
- [auth-onboarding/33-JOURNEY-NAVIGATION-UI-HANDOFF.md](auth-onboarding/33-JOURNEY-NAVIGATION-UI-HANDOFF.md) — Historical implementation, review or verification record: Card 05 — bounded authenticated pause/resume and Dashboard navigation.
- [auth-onboarding/34-AUTHENTICATED-ENTRY-D1-HANDOFF.md](auth-onboarding/34-AUTHENTICATED-ENTRY-D1-HANDOFF.md) — Historical implementation, review or verification record: Card 05 — authenticated entry presentation (D1 only).
- [auth-onboarding/CARD06-PER-PROJECT-PUBLICATION.md](auth-onboarding/CARD06-PER-PROJECT-PUBLICATION.md) — Historical implementation, review or verification record: Card 06 — Bounded per-project publication.
- [auth-onboarding/CARD06-PER-TYPE-CONTACT-VISIBILITY.md](auth-onboarding/CARD06-PER-TYPE-CONTACT-VISIBILITY.md) — Historical implementation, review or verification record: Card 06 — Per-Type Contact Visibility.
- [auth-onboarding/CARD06-PUBLICATION-MUTATION-SAFETY.md](auth-onboarding/CARD06-PUBLICATION-MUTATION-SAFETY.md) — Historical implementation, review or verification record: Card 06 — Publication Mutation Authorization and Transaction Safety.
- [auth-onboarding/CARD07-STANDARD-LOGOUT.md](auth-onboarding/CARD07-STANDARD-LOGOUT.md) — Historical implementation, review or verification record: Card 07 — Standard Ather and Auth0 logout.
- [auth-onboarding/CHANGELOG.md](auth-onboarding/CHANGELOG.md) — Dated authentication/onboarding phase and commit history. (kept in place — required by ENG-01)
