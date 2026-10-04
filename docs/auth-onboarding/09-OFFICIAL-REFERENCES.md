# 09 — Official references

Access date for this register: **2026-10-04**. These are primary official sources retained from Phase 0. Source classification describes how Ather uses a source: **explicit requirement** means the cited standard says so; **official recommendation** means official guidance; **engineering inference** means Ather selected a design informed by a source, not mandated by it. Product decisions still require local implementation and verification. The Jordanian publication is a legal-review input, not legal advice. URLs are HTTPS and syntactically validated; live content and Auth0 plan entitlements require recheck before implementation.

| ID | Organization | Official title and URL | Decisions supported | Classification |
| --- | --- | --- | --- | --- |
| S1 | Auth0 | [Auth0 Universal Login](https://auth0.com/docs/authenticate/login/auth0-universal-login) | AUTH-ADR-001 | Engineering inference — hosted-login choice from documented capability |
| S2 | Auth0 | [Verify Emails using Auth0](https://auth0.com/docs/manage-users/user-accounts/verify-emails) | AUTH-ADR-002 | Engineering inference — Ather verification gate |
| S3 | Auth0 | [Link User Accounts](https://auth0.com/docs/manage-users/user-accounts/user-account-linking/link-user-accounts) | AUTH-ADR-003, AUTH-ADR-024 | Official recommendation — authenticate both accounts |
| S4 | Auth0 | [Log Users Out of Applications](https://auth0.com/docs/authenticate/login/logout/log-users-out-of-applications) | AUTH-ADR-004, AUTH-ADR-005 | Engineering inference — two-session logout and switch design |
| S5 | Auth0 | [Adaptive MFA](https://auth0.com/docs/secure/multi-factor-authentication/adaptive-mfa) | AUTH-ADR-006, AUTH-ADR-008 | Engineering inference — plan-dependent adaptive MFA choice |
| S5B | Auth0 | [WebAuthn as Multi-Factor Authentication](https://auth0.com/docs/secure/multi-factor-authentication/webauthn-as-mfa) | AUTH-ADR-008 | Engineering inference — preferred factor choice |
| S6 | NIST | [SP 800-63B-4, Authentication and Authenticator Management](https://pages.nist.gov/800-63-4/sp800-63b.html) | AUTH-ADR-006, AUTH-ADR-007, AUTH-ADR-009, AUTH-ADR-010 | Explicit requirement — selected password provisions; session durations are engineering inference |
| S7 | Auth0 | [Attack Protection](https://auth0.com/docs/secure/attack-protection) | AUTH-ADR-009, AUTH-ADR-013 | Engineering inference — Ather abuse configuration |
| S8 | OWASP | [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) | AUTH-ADR-010, AUTH-ADR-011 | Official recommendation — session renewal and handling |
| S9 | OWASP | [ASVS 5.0 V7 Session Management](https://github.com/OWASP/ASVS/blob/master/5.0/en/0x16-V7-Session-Management.md) | AUTH-ADR-006, AUTH-ADR-007, AUTH-ADR-010 | Official recommendation — ASVS verification criteria; target level unresolved |
| S10 | OWASP | [ASVS 5.0 V6 Authentication](https://github.com/OWASP/ASVS/blob/master/5.0/en/0x15-V6-Authentication.md) | AUTH-ADR-001, AUTH-ADR-020, AUTH-ADR-021 | Official recommendation — ASVS verification criteria |
| S10B | OWASP | [ASVS 5.0 V10 OAuth and OIDC](https://github.com/OWASP/ASVS/blob/master/5.0/en/0x19-V10-OAuth-and-OIDC.md) | AUTH-ADR-001, AUTH-ADR-003 | Official recommendation — ASVS OIDC criteria |
| S11 | OWASP | [Cross-Site Request Forgery Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) | AUTH-ADR-012, AUTH-ADR-016 | Official recommendation — CSRF controls |
| S12 | GOV.UK Service Manual | [Structuring forms](https://www.gov.uk/service-manual/design/form-structure) | AUTH-ADR-015, AUTH-ADR-016 | Official recommendation — form structure |
| S13 | GOV.UK Design System | [Question pages](https://design-system.service.gov.uk/patterns/question-pages/) | AUTH-ADR-015, AUTH-ADR-019 | Official recommendation — question pages |
| S13B | GOV.UK Design System | [Error summary](https://design-system.service.gov.uk/components/error-summary/) | AUTH-ADR-019 | Official recommendation — error-summary behavior |
| S14 | W3C Internationalization | [Structural markup and right-to-left text in HTML](https://www.w3.org/International/questions/qa-html-dir) | AUTH-ADR-019 | Official recommendation — direction markup |
| S15 | Jordan Ministry of Digital Economy and Entrepreneurship | [Personal Data Protection Law No. (24) of 2023, official English publication](https://www.modee.gov.jo/EBV4.0/Root_Storage/AR/11/PDP_Law_-_English_Version1.pdf) | AUTH-ADR-014, AUTH-ADR-023 | Engineering inference — legal-review input, not legal advice |
| S15B | Jordan Ministry of Digital Economy and Entrepreneurship | [Law, regulations and instructions index](https://modee.gov.jo/EN/List/The_law_regulations_and_instructions) | AUTH-ADR-014, AUTH-ADR-023 | Engineering inference — legal-publication review input |

Source-review procedure: before changing an ADR, re-open the relevant primary source, record access date and exact applicable provision or feature behavior, check Auth0 plan/connection support in a non-production tenant, and distinguish a mandate from Ather's product choice. If a current official source contradicts an approved decision, stop the affected rollout and return to owner review.
