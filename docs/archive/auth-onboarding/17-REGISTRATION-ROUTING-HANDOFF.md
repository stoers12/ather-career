# Purpose-bound registration routing handoff — 2026-10-17

Baseline: `f05afb2aee954a1405417797dbe48b278537f828` (PR #20 merged; Main CI passed). Branch: `feat/registration-routing-20261017`. Worktree: `ather-career-worktrees/registration-routing`. Applicable parent/root instructions were checked; no AGENTS.md was found. The registration-core handoff (16), authorization/callback callers, session and recovery code, public shims, Apache/Caddy routing, and registration/data-model contracts were inspected. The canonical checkout and its unrelated required-gates script change were preserved.

## Switch and entry points

The sole production switch is **`ATHERCAR_REGISTRATION_ENABLED`**, enabled only by the exact string **`1`**. Absent, empty, `0`, `true`, and other values are disabled. Compose forwards it with default `0`. It was enabled only in disposable local application processes for testing; no canonical or live configuration was changed.

Default off: signup keeps the existing AR/EN disabled presentation and starts no session. Direct GET/POST requests to `owner_register.php` return 404 before discovery, account creation, or session establishment. A registration-purpose callback arriving after the switch is disabled consumes/rejects its transaction before token exchange, database resolution/creation, or authenticated-session establishment. Ordinary Sign In remains available for exact existing active bindings and denies unknown identities.

When explicitly enabled in isolation, signup supplies one Google-only form posting to `/owner_register.php`. The POST accepts only a CSRF token and no query/upload fields, rejects any existing internal-session key (including malformed/expired authority), and shares the existing OIDC start limiter. It clears a prior pending transaction after input authorization, validates the existing OIDC configuration/discovery, retires/rotates the anonymous session, and starts a fixed Google registration purpose. Microsoft/email stay disabled. There are no local passwords, new provider configuration, client-selected return URLs, or second callback URI.

`public/owner_register.php` is a standard production shim to the application route. Docker's existing public-root copy includes it; Apache serves that PHP file normally. Caddy's existing configured-issuer form-action policy already permits the OIDC POST redirect. No rewrite or CSP expansion was needed. A separate network-disabled disposable Apache check verified the actual shim's default-off 404 response body, disabled signup, and absence of the test token issuer from the production document root.

## Purpose and dispatch

Every `beginAuth0Authorization()` transaction now stores an allow-listed `sign-in` or `registration` purpose alongside state, nonce, PKCE verifier, and creation time. Its default is the explicit stored `sign-in` value. All existing production callers (`owner_login.php`, authenticated `owner_switch_account.php`, and anonymous CSRF-protected `owner_auth_retry.php`) retain that sign-in purpose and existing prompt/connection behavior. The new registration entry passes a fixed registration constant and Google method; request fields cannot supply either.

Consumption validates the purpose, state, transaction shape/time and code; expiry remains 600 seconds. Missing or malformed purpose fails closed and cannot become registration. Pending transactions issued by older code without a purpose must be restarted after rollout. Cancellation and successful callbacks remain one-time; failure cleanup retires pending authority. No purpose is sent to or read back from the provider. Query/POST purpose fields, provider claims, requested connection, subject prefixes and email addresses never select registration.

`completeAuth0AuthorizationWithPurpose()` returns an immutable completion containing the validated identity and consumed server purpose. The callback calls the registration core only for that completed registration purpose; sign-in uses the unchanged ordinary resolver. The existing identity-only completion helper remains sign-in-only and rejects discarding a registration purpose. Its preexisting synthetic validator seam remains for helper tests; the actual callback passes no validator override and exercises production token verification.

A callback with any existing internal-session key is refused before validation/dispatch, with its current identity retained and the pending transaction cleared. Explicit account switching still retires the old authenticated session first. No implicit linking, reassignment or account replacement occurs. Successful resolution/registration rotates into a fresh internal session, records the existing trusted readiness observation, checks the fixed owner destination, verifies session persistence, then redirects only to `owner.php` or `owner_onboarding.php`.

## Failures after account commit

Registration failure copy never claims that no data changed. Once the core operation is attempted, dependency/session failures conservatively say that an account may already exist and offer fixed registration/Sign In links. No compensating User/binding deletion occurs. A new explicit registration attempt safely resolves an already committed consistent active binding.

Callback failure cleanup retires authentication/readiness/transaction state and starts a fresh anonymous session where storage works; a broken store also clears in-memory authority and attempts session destruction. Session-write warnings/failed closure are treated as failures before redirect. This is best-effort cleanup during an irrecoverable session-store outage, not a new session registry or a promise of distributed atomicity between MySQL and PHP storage. Tests cover regeneration failure, write failure, and a real database query failure after the account commit; all clear authentication and recover through an explicit later attempt without duplicate/deleted Users.

## Actual validation

Local dependency-complete image: `ather-career-ci-web:2fb7e5227f334f754b5c8234e0e8497f24b2cc62`, PHP 8.3.33, Auth0 SDK 8.19.0, Guzzle 7.15.5; MySQL 8.4.11. The cached image's Composer lock matches this repository after Windows CRLF normalization (SHA-256 `62332B10322346E8C833B8EAA8944EFB812C96D948138F80134389ECDB54D51F`). No dependency or migration changed.

Actual PASS results:

- `scripts/test/run-registration-routing-tests.php`: eleven PASS groups covering default off; strict switch parsing; CSRF/POST/input boundaries; valid registration/repeat resolution; fresh session/readiness binding; completed-transaction replay; authenticated-session refusal; unknown ordinary Sign In with the switch on/off and purpose/claim/prefix tampering; missing/malformed/expired/future/mismatched/cancelled/replayed transactions; PKCE exchange rejection; exact SDK signature/issuer/audience/nonce/expiry rejection reasons; nonpositive/missing-email new identities; disabled/legacy/binding conflicts; switch-off pending callback before token exchange; existing owner access with false readiness; switch/retry sign-in purpose; rate limits; honest post-commit/session failures; protected data and provider-metadata preservation; private resource cleanup.
- Existing registration-core migrated rehearsal; ownership, public lifecycle, tenant authorization, Owner flow, Experience and Auth0 callback/identity/session/limiter migrated rehearsals.
- Full Phase 2 foundation, Sign In (62), readiness claim/session and real readiness POST route, account-entry presentation (387), recovery presentation (77), onboarding preview presentation (572).
- PHP lint for all changed/new PHP files; CI and Compose YAML parsing; changed CI Bash syntax; one focused CI invocation with existing generated scoped credentials and cleanup; `git diff --check`.
- Actual production-document-root Apache default-off shim check; eight desktop/mobile AR/EN signup captures for switch-off/on, with viewport width equal to document scroll width.

The focused suite uses real HTTP requests to copied public shims with the application source read-only. A separate loopback HTTPS issuer supplies freshly generated RS256 signed tokens and certificate-backed JWKS; the production SDK validates them. TLS verification remains enabled with a private test CA configured only for the disposable child process. PKCE, client, redirect and one-time code checks occur in the private issuer fixture. The suite requires a disposable Linux Docker process with writable container-local hosts mapping and loopback port 443. It restores that mapping and terminates its child servers; private keys, token fixtures, sessions and raw private access logs are removed with its run namespace. No authentication bypass/test environment hook was added to production routing or validation.

The application HTTP listener uses loopback and an explicit test-only insecure-cookie setting; production Secure-cookie behavior is unchanged and covered by existing contracts. Direct manipulation of malformed transactions and simulated session faults occurs only in the private test session store/test middleware, never through an application bypass endpoint. Existing helper tests use synthetic identity constructors/callbacks and are distinct from the new real route/SDK token coverage.

Resolved fixture/setup failures are retained as failures in the review evidence: initial parent session inspection tried to reset ini options after output; the first issuer used an SDK-unsupported IP/domain and then a nonstandard JWKS port/bare RSA JWKS. The fixture now uses a supported private hostname, container loopback 443 and `x5c` signing certificates, without changing production validation. The initial Apache overlay omitted the signup shim from the older cached image; copying the current shim fixed that fixture. Final checks passed. No required local check remains blocked; remote CI has not run because no push is authorized.

UI evidence is a light-theme Chrome render of actual HTTP signup responses with CSRF values redacted, existing CSS and bundled fonts. Desktop 1440×1000 and mobile 390×844 captures cover AR/EN and switch-off/on. These are layout evidence, not a real Auth0 browser journey or Google freshness/provenance proof.

## Launch dependencies — switch must remain off

This is purpose-bound routing, not permission to launch public registration. A new strictly verified identity can become an active internal User under the reviewed core contract. An existing consistent active binding resolves without a new positive-email requirement; its current readiness observation still controls first Portfolio creation. The previously approved existing-owner access and first-creation readiness policies are unchanged.

**The legacy `owner_onboarding.php` GET/HEAD/POST creation flow still exists.** A newly registered, strictly verified session can reach that route and its readiness-guarded private-Portfolio creation. It does not enforce age attestation, stored/versioned Terms acceptance or Privacy acknowledgement, completed required profile fields, five-step completion, or a complete onboarding state. Portfolio existence must not be treated as onboarding completion. The five-step preview remains disabled/nonfunctional; no consent/draft/workflow state schema was invented.

Before any live switch activation, separately review the missing consent/eligibility/onboarding gates, legacy-route disposition, access/activation/rollback policy, provider readiness/provenance and freshness requirements, and full HTTPS/browser integration. A requested Google connection or Google-shaped subject is not proof of completed Google authentication, fresh credentials/MFA, email verification time or claim propagation. Optional provider/connection metadata stays unset. This slice does not perform or settle that provider investigation.

## Changed files and review delivery

Production: `includes/auth0_oidc.php`, `owner_register.php`, `public/owner_register.php`, `owner_oidc_callback.php`, `includes/owner_auth_recovery.php`, `signup.php`, `includes/signup_copy.php`, `docker-compose.production.yml`.

Tests/CI: `scripts/test/run-registration-routing-tests.php`, `tests/phase2/support/registration-oidc-mock.php`, `scripts/test/run-owner-readiness-claim-tests.php`, `tests/phase2/cases/HttpValidationContractTest.php`, `.github/workflows/ci.yml`. This handoff is `docs/archive/auth-onboarding/17-REGISTRATION-ROUTING-HANDOFF.md`.

The Desktop review bundle exports the complete baseline-to-head patch, this handoff from the commit, retained logs/commands, and redacted UI evidence. No credentials, raw sessions, codes, tokens, signing keys, or unredacted access logs belong in it. Disposable resources/credential files are removed after validation. No push, merge, deployment, live activation, real authentication, Auth0 change, canonical checkout/container/data change, or Notion update occurred.
