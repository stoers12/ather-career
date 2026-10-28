# Private onboarding draft editor — 2026-10-18

Bounded successor: [29 — private editor attestation UI](29-ATTESTATION-EDITOR-UI-HANDOFF.md) supersedes only the authenticated editor’s disabled attestation-area description below. The guarded preview remains inert; Create/visibility remain disabled. Separate explicit recording does not alter draft persistence, exact retries, catalogs, completion or access. Earlier evidence remains historical.

Bounded later core: [handoff 27](27-FIRST-COMPLETION-CORE-HANDOFF.md) records atomic first creation/evidence and read-only completed drafts. Existing exact draft retries, current Profile authority and existing-owner access remain; HTTP/UI/routing/publication activation stays deferred. Earlier evidence below remains historical.

Catalog-only successor: [handoff 25](../../auth-onboarding/25-ONBOARDING-PROFILE-CATALOG-INTEGRATION.md) supersedes the editor sample-choice description below with the existing Profile catalogs and safe older-choice labels. The guarded design preview retains its samples; completion and activation remain unavailable.

Approved baseline: `7ddb34b1b34c342e502ec77aa2f188a55b5944b9` (PR #24 merged;
Main CI passed). Branch/worktree: `feat/onboarding-draft-editor-20261018`,
`.worktrees/onboarding-draft-editor`. No AGENTS.md was found in the repository,
worktrees or ancestor chain. Handoffs 19/20, approved preview/JS/CSS/copy, Owner
routing, packaging, session/CSRF and presentation tests were inspected first.
Canonical source and its unrelated required-gates change are preserved.

## Entry point and presentation

`/owner_onboarding_editor.php` and its production public shim provide a directly
addressable authenticated GET page. It uses the existing exact-1
`ATHERCAR_ONBOARDING_DRAFT_HTTP_ENABLED` gate. No new switch is added. Gate-off
requests return 404 before session/database use. Enabled anonymous, denied,
unsupported-method, malformed language/section and temporary failures use existing
private HTTP conventions. Page responses are private/no-store, noindex/nofollow.
Only `lang=ar|en` and `step=1..5` presentation selectors are accepted.

`includes/onboarding_presentation.php` is a production-safe renderer extracted
from the approved five-step markup. The guarded preview wrapper retains its guard,
preview-only CSP and original inert behavior. Production never includes or routes
through test code. Apache continues to own production CSP. The 572-check preview
suite passes unchanged. Editor-only copy, feedback and disabled controls reuse the
approved composition; no Dashboard redesign or new runtime framework is introduced.

The editor has its own small interaction script, `onboarding_editor.js`; the
preview keeps `onboarding.js`. The existing theme helper stores only the existing
light/dark preference. Private values, CSRF tokens and operation receipts are never
written to browser storage, history, URL query strings, logs or telemetry. Tokens
are carried only in private page memory/non-rendered context attributes and the
existing private HTTP exchange. Screenshots contain only synthetic displayed data;
tokens/cookies/receipts are not rendered or exported. No HAR, HTML/session dumps or
browser storage state is bundled.

Sign-in/callback destinations, existing Owner routing, `owner_onboarding.php`,
public registration and the reviewed persistence/HTTP core are unchanged. The
fixed acknowledged exit destination is `/owner.php`; its existing routing decides
whether an existing owner sees the Dashboard or a new user sees legacy setup.
The editor performs no Portfolio creation, including on exit.

## Exact control mapping

Only existing approved controls are connected; no optional inputs are added.

| Existing control | Core section | Core field |
| --- | --- | --- |
| Professional display name (`data-profile-name`) | `public_identity` | `display_name` |
| Country (`data-country`) | `public_identity` | `country_code` |
| Preferred profile-language radios / deliberate Clear preference | `public_identity` | `preferred_language` |
| Professional status (`data-career-status`) | `professional_snapshot` | `professional_status` |
| Specialization (`data-field`) | `professional_snapshot` | `specialization` |
| Headline (`data-headline`) | `professional_snapshot` | `headline` |
| Candidate slug (`data-slug`) | `profile_identity` | `slug_candidate` |

Country examples retain their ISO-shaped values; status examples retain the
preview tokens `student`, `graduate`, `early`, `experienced`, `independent`.
These remain candidates, not approved product catalogs. Saved country/status
values outside the samples are inserted as safe literal selected options. A saved
language outside ar/en is displayed as a safe additional radio choice. Switching
interface language does not change this candidate preference. A Clear preference
button permits an explicit null edit without introducing another field.

Unrepresented `name_ar`, `name_en`, `city`, `university`, `graduation_year`,
`biography` and `phone` are never sent by the editor. Existing values remain intact.
The core owns all field/aggregate bounds, version checks and persistence semantics;
see handoff 19. No trimming, Unicode normalization or case folding occurs. An
untouched empty/default control is omitted. Only a changed control deliberately
emptied by the user produces null. Strings containing only whitespace are not
silently treated as clearing; the core rejects them and the editor retains them.

## Save, retry, conflict and authenticated context

The page contains a current-session CSRF anchor after authoritative User validation.
The initial GET `/owner_onboarding_draft.php` acquires resume data and the token.
It must match that page anchor before any values are hydrated or editing is enabled.
A version-0 resume explicitly says no draft is saved yet; it creates no row.
All saves call that same reviewed endpoint with its validated CSRF header and
strict JSON envelope. Neither renderer nor JavaScript duplicates persistence or
server authorization logic, and neither accepts a User/Portfolio selector.

Continue and both Save-and-exit controls explicitly save dirty fields, grouped in
public-identity / professional-snapshot / profile-identity order. At most one
request is in flight; there is no background autosave. Acknowledgements advance
the local version/baseline. They do not hydrate over controls: newer edits remain
visible and are serialized in a subsequent necessary save with a new ID. Continue
moves/focuses the next heading only after the intended queue is acknowledged.
Save-and-exit navigates only when no intended edits/pending operations remain.
Previous/section editing keeps local values without claiming a passed gate.

An in-memory pending operation is an immutable JSON string containing its exact
payload and version-bound ID. An uncertain network result, body-read timeout or
503 retains it. Retry first resends those exact bytes/ID; later changed values use
a fresh operation only after that receipt is acknowledged. Retry itself does not
unexpectedly exit; the user can choose Continue/Save-and-exit afterwards. A 400/
413/415 rejects the operation, retains values for correction and permits a new
intentional save. A 409 stops saving without blind retry or overwriting edits.

Conflict/uncertainty recovery offers Load server draft with an explicit replace
confirmation (cancel keeps edits). During that GET controls are disabled so edits
made after the confirmation cannot be overwritten by a late recovery response.
Only an acknowledged same-context response replaces local edits and clears the
pending receipt. Language switching obtains authenticated localized markup and
checks the same anchor, then reconstructs the controls from current in-memory
values; it preserves dirty values, unknown choices and pending receipts. It never
uses a translated page to acquire another session's token for old values.

401/403 or a changed page/API anchor locks saving and requires an explicit fresh
page context. No automatic token refresh/re-authentication/resubmission occurs.
Back-forward-cache restoration also locks the editor. The localized status region
announces loading, unsaved, saving, saved, invalid, conflict, uncertainty,
unavailable and authentication-stop states. A browser unload warning protects
unacknowledged edits; a user can still force-close/navigate. Private RAM-only edits
and pending receipts do not survive a forced reload. Resume returns only durable
server data, not an invented local save.

## Product boundaries

Create stays disabled. Age, Terms, Privacy, email and phone visibility controls
are disabled with AR/EN explanations and are never included in draft requests.
No example email is presented as the current User, and no readiness/gate pass is
claimed. The link is a candidate only. Navigation/last-edited metadata proves no
eligibility, consent, readiness, completion, slug ownership or publication.
Registration remains Google-only/default off; real policies remain unavailable.
There is no core/schema/migration expansion, final catalog, activation, optional
field collection, real Auth0 login, production test bypass or public preview route.

## Validation and evidence

The local `.worktrees/draft-editor-review` retains exact scripts, timestamped
success/failure logs, safe browser results, screenshots, source hashes, changed
source manifest, this handoff and the complete baseline-to-head patch. Its manifest
records the final commit and passing run filenames; the adjacent ZIP verification
record records the Desktop path and SHA-256 equality. Generated credential/context
files are deleted and are not bundled.

Disposable MySQL 8.4.11 and PHP 8.3.33 run with a generated database-scoped
application user (global USAGE only). The new fixture/API/UI traffic uses that user;
root only provisions outer disposable resources. The unchanged readiness regression
retains its prior guarded, separate disposable fixture harness. Source is mounted
read-only and executed from a container-local copy. Synthetic sessions are created
only by guarded CLI fixtures, never by a production authentication bypass.

Focused checks include 50 AR/EN renderer assertions and private/production boundary
checks; nine real-browser groups against the real HTTP endpoint cover gate and
session boundaries, partial save/clear/reload, unchanged unrepresented fields,
safe literal text, Save-and-exit, two-tab conflicts with explicit recovery,
committed-but-lost responses with byte-identical retries, late edits/serial saves,
real missing-table 503 and exact retry, expired sessions, account switching during
save/language change, keyboard/focus/live status, AR/EN RTL/LTR, desktop/390/320px
and both themes. All five panels are checked for horizontal overflow across those
language/width/theme combinations. Sixteen representative screenshots are retained.

Existing eight HTTP, eight draft-core, five attestation, eight registration-core,
eleven routing, ownership/public lifecycle, Phase 2 foundation, Sign In, readiness
and 572-check presentation regressions are run. PHP/JavaScript, YAML, CI Bash,
PowerShell, Git diff/patch and unchanged-core checks are retained. Production
packaging is verified by a real Docker build and task-only Apache: page/public
shim/assets exist, editor/API GET/POST remain 404 with gate off, CSS/JS serve 200,
and the preview remains inaccessible. The migration entrypoint is deliberately
bypassed for this database-free off-gate smoke; no live migration is run.

CI adds focused renderer/preview rehearsals and a pinned test-only Playwright 1.58
browser step using the generated rehearsal database and a generated scoped user.
Its fixtures/child servers are private; cleanup disposes the user/container/context
and the existing unconditional job cleanup removes the database/Compose resources.
Production smoke additionally checks the off-gate page and packaged editor assets.
No runtime browser framework is added to the product.

Retained failures include the original extraction of a preview CSP header into
production (corrected by moving it back to the guarded preview), the review shell
script's final CRLF path (normalized to LF), an ambiguous browser test locator
(corrected to the displayed heading), and an early TCP connection refusal after a
Unix-socket-only MySQL readiness check (corrected to TCP readiness). They are not
represented as passes. Exact logs and final passing filenames are in the manifest.

Not run: remote CI, real authentication/production data, deployment, accessibility
screen-reader/device testing beyond automated keyboard/focus/live-region checks,
or restored-startup execution on this new head. Prior baseline Main CI includes
restored startup through 016; persistence/migration/HTTP core remains unchanged.
Production-like enabled behavior is exercised through the real PHP HTTP shims;
the built Apache smoke intentionally keeps the gate off.

## Remaining work

Final required/catalog rules, eligibility/consent/readiness/completion workflow,
Profile/Portfolio mapping, slug reservation, publication/visibility, retention/
withdrawal and launch policy/operator/contact/hosting work remain separate. No
Dashboard, sign-in destination, callback, legacy creation route or registration
entry point was changed. No push, PR, merge, deployment, canonical gate activation,
live migration, real authentication, Auth0, Notion or canonical service/data change
was performed.


Final local evidence: `validation-7752c30907fd.log` and
`browser-7752c30907fd.log` both completed successfully (exit 0).
`production-package-4ddad6bad714.log` records the successful final production
build and Apache packaging/default-off smoke. `static-checks.log` records syntax
and unchanged-core/Owner boundaries; `cleanup-check.log` confirms no task container,
network, image tag, credential or private context file remains. The review manifest
and source-hashes record bind these artifacts to the final local commit. Sixteen
final representative screenshots were inspected; no private token is displayed.
