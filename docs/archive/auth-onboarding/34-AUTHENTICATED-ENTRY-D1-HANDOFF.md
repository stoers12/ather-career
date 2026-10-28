# Card 05 — authenticated entry presentation (D1 only)

Implementation base / fetched main: `17bbc2e0886a5b9dd2e62f2d50e78e285ead723f`.
Isolated branch `feat/authenticated-entry-d1-20261020`, worktree
`.worktrees/authenticated-entry-d1`. The preceding read-only Card05 audit on this
baseline identified D1 and D2 as owner/product decisions, not an authorization
bypass. Its Desktop evidence ZIP SHA-256 is
`6a878a1c72556ad6d4e5358eb649388d1a2f101eccc07f1dbf6b571632196549`.
Only D1 is now explicitly approved. No applicable ancestor/repository AGENTS.md was
found. README maintenance contract, audit report/matrix, handoffs32/33 and actual
entry, journey/authorization/session, OIDC/recovery/CSRF, public shims and fixtures
were read first. Canonical HEAD and both unrelated modified files are preserved;
before/after fingerprints and logs are outside the source worktree.

## Implemented boundary

[Shared presentation](../../../includes/authenticated_entry.php) is called by
[signin](../../../signin.php), [signup](../../../signup.php) and the
[direct login entry](../../../owner_login.php). A session cookie only triggers
inspection, never authorization. Anonymous public signin/default-off signup do
not create a session or load/connect to the database unnecessarily; an existing
anonymous session also requires no database validation. Enabled anonymous signup
keeps its existing CSRF-protected Google-only registration form.

A present malformed/null internal record is retired with403 and explicit sign-in
recovery. Valid-shaped sessions must pass the existing current User, active-account,
authz-version and lifetime checks. Expired/revoked/disabled/missing Users receive403,
not authenticated controls; a subsequent explicit public entry can recover as an
anonymous visitor. Authorization dependency failures return503 without claiming
signed-in authority. Session inspection/authorization must succeed before account
actions appear. An already-authorized User whose journey dependency is unavailable
gets a503 signed-in unavailable view with CSRF account actions, without a usable
Dashboard/Resume action. No raw database/session/request details enter the response.

Reuse of `ownerJourneyDestination()` selects only these explicit actions:

| Current server result | Presentation |
| --- | --- |
| `/owner.php` (correct completion binding or frozen legacy owner) |200, signed in, fixed Dashboard `/owner.php` |
| `/owner_onboarding_editor.php` |200, signed in, Resume `/owner_onboarding.php?lang=ar\|en` for a fresh routing decision |
| holding / unavailable capabilities or policies |503, signed in, honest unavailable state and account controls |

All authenticated views offer exact current-session CSRF POST forms for
`/owner_switch_account.php` and `/owner_logout.php`. No arbitrary return URL,
Portfolio existence, request/session cohort flag, local step or displayed action
grants authority. Dashboard/Resume and account handlers authorize again. These are
GET-time presentations, not an instant cross-tab/provider-session monitor; a stale
page never authorizes its target. No automatic navigation or OIDC call is added.
AR/EN, RTL/LTR, keyboard controls, themes and layout reuse signin.css/landing.js;
no new CSS framework, client request machinery or state storage.

Authenticated/error/dependency responses use private,no-store and noindex/nofollow;
entry representations Vary on Cookie. signin/signup retain GET/HEAD, direct login
retains GET-only and validates its existing method selector before the presentation.
Authorized login GET exits before discovery, rate-limit consumption or OIDC builder.
Existing pending state is not replaced/consumed; User-session activity/CSRF follows
existing session conventions. An invalid session is safely retired instead.

## Preserved and deferred

Callback refusal of implicit identity replacement, authenticated register/retry
refusals, exact known identity resolution, session rotation, CSRF, state/nonce/PKCE,
rate limiting and unknown ordinary Sign In rejection remain unchanged. Local logout
only destroys Ather's session; it does not terminate provider/Auth0 SSO. No linking,
provider logout, new providers, readiness TTL or provider-freshness claim.

**D2 is deferred:** no public signup discovery links or landing launch-copy change.
Both production policy/content resolvers remain literal null and all actual gates
remain off. No schema/migration, capture, route authority/core/API, catalog,
Profile/publication, Dashboard redesign, deployment or launch activation change.
Existing-owner access needs no new readiness or attestations. Bare Portfolios remain
unproven, never adopted or repaired. Card05/full journey is not declared launch-ready.

## Verification and CI

Existing CI calls remain:21 CLI rehearsals, migration ordering, scoped credentials,
all old browser assertions and private-carrier cleanup. Additions live in the existing
[journey HTTP suite](../../../scripts/test/run-owner-journey-route-tests.php),
[registration/OIDC suite](../../../scripts/test/run-registration-routing-tests.php), and
[completion browser fixture](../../../scripts/test/run-onboarding-completion-browser-fixture.php)/
[browser suite](../../../scripts/test/run-onboarding-completion-browser-tests.mjs).
Workflow changes are two explanatory comments; these already-invoked suites run the
new checks unconditionally. No assertions, traps, credentials or calls removed.

Local disposable MySQL8.4/PHP8.3 evidence (logs in external review ZIP):

- Full21 CLI calls pass (`validation-30934e733d9b.log`), plus foundation,
  Sign In62, account-entry387, recovery77 and readiness claim/session/legacy POST.
  Completion core/HTTP10/12, attestation HTTP12, draft core/HTTP11/10,
  Profile core/HTTP5/6, final Profile core/HTTP5/7, renderer3/preview572 and
  existing registration/authorization/public/ownership regressions remain passing.
- Journey suite now16 PASS groups (old12 +D1 four); focused final failure-path
  rerun `validation-1ea852f1fe24.log` also covers null/malformed retirement,
  missing users table, actually unreachable DB, anonymous DB independence,
  unavailable journey lookup and target reauthorization after revocation/gate loss.
  Every read/rejected write compares all protected database row hashes.
- Local TLS discovery/token mock is stopped during repeated authorized visits:
  all three entries still render; pending transaction remains identical and the
  original five anonymous starts remain available. Actual forms from each entry
  exercise wrong method/CSRF, register/retry refusals, switch retirement/rotation,
  a different known identity's fixed return, and local logout without linking.
- Browser:14 original completion +4 navigation +3 D1 groups pass. D1 checks all
  three private presentations, no automatic POST/external authentication request,
  current Profile Dashboard, exact acknowledged saved Resume, AR/EN RTL/LTR,
  themes and1440/390/320px layouts, keyboard navigation and synthetic screenshots.
- Packaged Apache production image:74 requests, including24 bilingual D1 cases
  (three entries ×four User classes ×two languages), existing root/shim/nested
  route boundaries, default-off behavior, no-store, methods/CSRF/query and cleanup.
  All Apache application flags remain0 with original null policies; positive
  Resume uses only the guarded isolated PHP fixture, not a production policy hook.
- Production build, all PHP syntax, JS syntax, YAML parse/Bash syntax, diff check,
  migration/security and HTTP smoke pass. Restored startup through020 preserves
  exact nonempty completion/Profile/draft/attestation dumps, frozen legacy set and
  exclusion of the later bare Portfolio. Test-only capture is disposable, never live.

## Retained failures/corrections

1. An edit helper read an existing Arabic test assertion using Windows cp1252 and
   rewrote it incorrectly; first journey suite failed "Holding lost recovery".
   Restored exact original UTF-8 assertions from baseline, retaining only additive
   D1 tests. `validation-584964272091.log` retained; subsequent full/focused runs pass.
   A separate failed UTF-8 read aborted browser-test preparation before a write;
   that early browser run proves only the old18 groups, not the later D1 additions.
2. First added keyboard Resume assertion expected151 emoji from an intentionally
   rejected unsaved edit. Server correctly returned acknowledged data. Corrected
   only the new assertion to compare the actual saved draft and wait for resume
   acknowledgement. `completion-browser-d2c740c6da7b.log` retained; no production
   acceptance rule or preexisting assertion was relaxed. Final run has21 groups.
3. Screenshot QA removed a contradictory inherited "Sign in to continue" sentence
   from the authorized story panel in favor of current-account wording. Final
   browser evidence/build use that copy; earlier successful runs are distinguished.

## Limits and next safe action

No hosted CI/push/PR was run. Existing standalone editor/attestation/Profile browser
suites were not rerun for this entry-only slice; their relevant CLI/HTTP regressions
and the full completion/navigation browser suite were run. No real provider login,
account-picker/credential freshness/MFA/provider email refresh or SSO logout test.
No physical devices, Firefox/WebKit or manual screen-reader certification. Local
Windows Node uses a private fixture carrier; this does not independently prove
host Linux POSIX ownership enforcement. The existing CI host UID/GID,0600/0700,
ordinary non-root reader and bounded fixture cleanup pattern stays unchanged.
Dedicated Apache evidence is local, not hosted smoke. Real policies, provider/hosting
verification, manual accessibility and exclusive-maintenance launch preparation stay
separate. Next safe step: owner reviews the bounded local D1 commit and evidence;
D2 remains deferred. No Notion or canonical-resource update.
