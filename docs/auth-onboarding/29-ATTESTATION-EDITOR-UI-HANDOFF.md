# Card 04 — bounded private editor attestation UI

Independent successor: [30 — completion HTTP transport](30-COMPLETION-HTTP-HANDOFF.md) adds an off completion transport with no editor integration. Existing Create remains disabled; attestation UI and all draft operations stay unchanged. This does not finish Card 04 or activate the journey.

Base: `a459d785a54da1e06dfeb009d926a434ac57651e` (merged PR 30, verified Main CI).
Fetched remote main matched before isolation. Worktree:
`.worktrees/onboarding-attestation-editor`; branch:
`feat/onboarding-attestation-editor-20261020`. Applicable ancestor/repository
instructions, README maintenance contract, handoffs 21/25/27/28 and actual
renderer/JavaScript/HTTP/session/CSRF/browser fixture code were read first. No
applicable AGENTS.md exists. Canonical HEAD and unrelated dirty files are preserved;
their before/after hashes live in the external review evidence.

## Implemented; production still unavailable

Only the existing private editor's step-1 attestation area is connected to
`/owner_onboarding_attestation.php`. Existing composition, native checkboxes,
AR/EN copy, themes, responsive layout, draft saves and catalogs remain. No new
framework, route, shim, gate, schema/migration or policy resolver is introduced.

Both production resolvers remain their literal null functions. All actual gates
remain off; registration remains Google-only/default off. The endpoint cannot
present or record production documents now. This UI slice is local for review,
not activation, a completed Card 04 or a finished onboarding journey. Create
and contact visibility stay disabled. No completion HTTP/UI, Portfolio creation,
callback/Dashboard/legacy cutover, publication or new navigation/access gate.

The guarded presentation-only preview remains nonpersistent and uses its original
copy/script/behavior. Added document CSS is scoped to `data-draft-editor` only.
The ordered 13-country catalog, historical values and exact retry receipts remain
unchanged. No Profile, readiness, attestation/completion core or server API changes.

## Exact presentation, actions and recovery

After the draft GET matches the original page's CSRF anchor, a separate GET loads
all three authoritative server documents and exact-current evidence. There is
no language or bundle selector. The UI validates its response shape/token and
requires the presentation CSRF token to equal the loaded editor context; it never
refreshes that anchor to adopt a different session for old edits.

Each complete document is inserted through `textContent` into a plain text node,
with preserved whitespace/wrapping, its actual server `lang`, `dir=auto` and an
explicit document-language label. No innerHTML, content translation/rewrite,
invented legal text/link or inference from UI/profile preference is used. Interface
language switching localizes controls only and obtains a fresh presentation with
all transient actions reset. Document descriptors/digests remain server authority.

Three separate native, labeled actions start unchecked: age self-attestation,
Terms acceptance and Privacy Notice acknowledgement. The last is not blanket
processing consent; self-attested age is not verified age. Only a ready current
presentation plus all three positive actions enables Record my actions. Exactly
the token and three boolean true values are POSTed with the original CSRF header.
Checkboxes/navigation/language/page load/recovery never POST automatically.

Recording disables actions/read/record controls; repeated activation cannot send
another POST. The language switch does not replace the UI during recording.
Successful recording leaves all three exact documents visible with the original
server UTC microsecond timestamp, clears the token/actions and disables further
recording. A successful GET for already-existing evidence also shows original
status/time without prechecking boxes or fabricating fresh actions. Recording
grants no access/readiness/completion/publication authority.

Separate localized states are loading, ready, recording, recorded, unavailable,
stale, uncertain, temporary failure and authentication stop. Endpoint off/missing
policies leave draft editing/saving independent. A stale response retires documents,
token and actions and requires explicit fresh loading/review. A failed/unconfirmed
POST may have committed: the UI clears transient actions and offers an explicit
GET evidence re-read, never promises no record and never repeats the POST itself.
Fresh positive actions and an explicit click are necessary for any later recording.

## Request, context and draft separation

One attestation request generation/controller, original editor context, current
connected-root identity and shared authentication epoch guard all responses.
Language replacement retires/cancels old presentation work before requesting new
markup. New listeners bind only to the new root; old responses cannot hydrate it.
401/403 or a successful GET with another CSRF anchor stops BOTH protected boundaries.
No automatic authentication or token acquisition occurs. Late draft acknowledgements
and language/resume results now also check the original epoch/root before applying;
they cannot unlock or advance a context invalidated by the attestation boundary.

Pagehide retires the current context; persisted pageshow uses the existing explicit
authentication-stop behavior. Late responses cannot revive a restored document.
Signals/epochs cannot detect an otherwise unobserved cookie change instantaneously:
server reauthorization and CSRF remain authoritative on every protected request.
The UI stops when the changed/expired context is observed; no stronger provider or
background session-monitoring claim is made.

Presentation tokens, exact bodies and checkbox actions live only in private RAM/DOM.
They never enter draft fields/versions, pending immutable operation strings, browser
storage, URLs, telemetry or logs. Language changes preserve unsaved draft values,
historical choices, conflicts and exact pending retry bytes/IDs, independently of
resetting document actions. Existing acknowledged draft navigation is unchanged.
Forced reload still loses RAM-only edits; the existing explicit fresh-page warning
and draft recovery flow remain. No browser file-selection preservation is claimed.

## Test and CI isolation

The existing guarded editor fixture gains a CLI-only `--attestations` mode, behind
Docker/test-environment/disposable-namespace guards. It reuses the reviewed private
HTTP source-copy builder and creates successful synthetic documents only through
those exact reversible resolver/include substitutions. Original null-policy and
gate-off servers run separately using original production routes/resolvers. No
environment switch selects fake documents from a production path.

Synthetic documents deliberately contain CRLF/blank lines, mixed AR/EN, literal
HTML-like scripts/images/links and test-only labels. The browser verifies exact
server text and SHA-256, harmless text-node rendering and actual document language.
Fixture control is guarded CLI/private-file only; no fixture login/control HTTP
route is added. Sessions are persisted synthetic PHP sessions, not real login.

CI adds one focused browser step after the existing editor/Profile steps. All
nineteen CLI calls, migration prerequisites, existing assertions and credentials
remain. Its generated scoped database user, private 0600 carrier/0700 directory,
privileged assignment to the actual host UID/GID and ordinary non-root Node reader
follow the reviewed Profile handoff pattern. Browser status is retained; cleanup
signals stop, waits at most 45 seconds, verifies fixture exit and explicit protected
snapshot evidence, then removes container/user/carrier. Forced removal is a failed
timeout fallback. Final job cleanup fails if the fixture unexpectedly remains.

Fixtures verify protected existing database rows, unrepresented draft fields and
private logs; remove owned evidence/users/drafts and private source/session/files;
terminate child servers; and explicitly report final preservation/cleanup. No
background cleanup infrastructure is added.

Final local commands/results, retained failures and verification gaps follow.


## Final local verification and evidence

External review directory: `.worktrees/onboarding-attestation-editor-review`.
Disposable MySQL 8.4.11/PHP 8.3.33, generated database-scoped users, read-only
source mounts/container-local execution copies, persisted synthetic sessions and
existing pinned Playwright 1.58 Chromium were used. No canonical DB or real login.

| Command / retained final evidence | Result |
| --- | --- |
| `validate.ps1`, `validation-a85aa0d2b306.log` | All 19 ordered CLI invocations PASS; attestation HTTP 12/core 5, completion 10, draft 11/10, Profile 5/6, final Profile 5/7, renderer 3 with new assertions, preview 572; ownership/public/tenant/Experience/owner/mock OIDC/registration/routing and foundation/Sign In/readiness regressions PASS. Scoped user and fixtures cleaned. |
| `attestation-browser.ps1`, `attestation-browser-a9d2832c7fa7.log` / `validation-a9d2832c7fa7.log` | All 12 new browser groups PASS, including detached old listeners, delayed GET/POST/draft responses, context mismatch, exact pending retries, document retention after recording and protected fixture/private-file/session/process/database cleanup. Thirteen final redacted synthetic screenshots. |
| `editor.ps1`, `editor-browser-419008890f86.log` / `validation-419008890f86.log` | All 11 existing editor browser groups PASS; exact retries, conflicts, later edits, historical catalogs, account boundaries, RTL/LTR/responsive/theme/keyboard checks and fixture preservation/cleanup retained. Twenty existing representative synthetic screenshots. |
| `production-check.ps1`, `production-9d4acbb160dd.log` / `production-build-final.log` | Fresh final image build/lint, Apache default-off GET/POST private/no-store 404, health/readiness/bilingual assets/private catalog/legacy boundaries, migration/security PASS. Nonempty completion/Profile/draft/exact-attestation restored startup preserves the entire dump through 019: SHA-256 `615abc0013542ef1a232a9553f3ae7f12c0fb96f1a4165005af10e01a0e3c778`. Restored files/DB and all task containers/three volumes/network/credentials removed. |
| `static-checks.py`, `static-results.json`; `linux-permissions.sh`, `linux-syntax-permissions.log` | 96 protected source files unchanged, literal-null resolvers/default-off config, existing CI byte-identical after removing only the added step/fallback, 25 Bash blocks/YAML/PHP/Node/diff syntax and local documentation links PASS. Real Linux wrong-owner 0600 read denied, privileged ownership handoff permits ordinary non-root reader with 0700 directory; no contents printed. |

Final selected suites have zero failed or skipped groups. `commands-results.json`
lists every CLI PASS group, all final filenames, retained failed attempts and gaps.
Source/image hashes, cleanup audit, complete patch/reconstruction and ZIP/Desktop
equality are recorded in the external manifests, bound to the containing commit.
`runtime-static.json` additionally confirms 19 final functional UI/auth/HTTP/core
files and both public asset copies match the final production image (LF-normalized),
with literal-null policies and the endpoint off. Task image tags/owned untagged
predecessor were removed after verification; the preexisting cached test image and
three running canonical containers remain. Build caches were not globally pruned.
The existing Profile browser suite is preserved byte-for-byte in CI, but was not
rerun locally for this editor-only UI slice; its baseline Main CI is historical.

## Retained failures and corrections

- `validation-8aa8cfb13f35.log`: external generated shell had CRLF and stopped
  before tests; generator now explicitly writes LF. Disposable resources cleaned.
- `attestation-browser-5312311baf1a.log`: four groups passed; fixture after-COMMIT
  fault was enabled before GET, so presentation read failed too. Load the unchanged
  bundle first, then enable fault immediately before POST. No assertion/core weakened.
- `attestation-browser-0fc996b12f65.log`: eleven groups passed; final expiry case
  expected ready despite the prior scenario recording that same fixture bundle.
  Give expiry its separate unrecorded bundle; preserve original evidence assertions.
- `attestation-browser-1e10ba301504.log`: mobile screenshot succeeded, then the
  test attempted a desktop-only progress link still hidden at mobile width. Reset
  viewport before desktop navigation; retain all mobile captures/layout assertions.
- The initial successful `attestation-browser-b1c103cdce1c.log` screenshots exposed
  a UI issue: successful POST cleared documents. The final code keeps complete
  reviewed documents beside evidence/time, resets actions/token and focuses status.
  An added assertion and final regenerated screenshot verify the correction.
- `attestation-browser-9ec9cbfed4ee.log` and its images are intermediate successful
  evidence before listener/markup-handoff strengthening; final 530e screenshots
  and assertions supersede them. `production-1340afe389cb.log` and
  `production-build-before-final-ui.log` are earlier passing build/restore evidence;
  final 9d4 production run supersedes them. Earlier full CLI and editor passes are
  retained separately, not substituted for final selected runs.

- `attestation-browser-530e9e64678d.log` is an additional successful run before the final capture-only correction: scrolling to the page top avoids a fixed skip-link artifact in full-page screenshots. Final a9d images and all twelve groups were regenerated and inspected; no product CSS or acceptance assertion changed for that capture correction.

All started browser fixtures reported preserved protected data and complete scoped
cleanup even on browser failures; the CRLF attempt never started a fixture. No test
was skipped or assertion weakened to convert failure into success.

## Explicit verification gaps and next safe action

No hosted CI/push/PR, real policy approval, real authentication/provider freshness,
deployment/live migration/activation, physical network/daemon fault, Safari or
manual screen-reader/physical-device testing. Automated Chromium checks cover DOM
labels/focus/live regions, AR/EN RTL/LTR, themes and 1440/1024/768/390/320px layouts;
they are not accessibility certification. BFCache uses a persisted pageshow event
to exercise the existing boundary, not a claim about engine-specific cache eligibility.
Possible-commit tests wrap real MySQL COMMIT and abort/delay real HTTP responses.
Local successful policy cases use guarded private PHP source copies; Apache smoke
keeps gates off. Real Linux permission mechanics were tested with a non-root PHP
reader; the exact hosted non-root Node handoff is integrated/syntax checked, not
claimed as a local Ubuntu-hosted browser run.

Next safe action is owner review of this local commit and verified ZIP. Approved
immutable policy text/translations, operator launch preparation, final review,
completion HTTP/UI, routing/publication/retention decisions and activation remain
separate work. This slice does not complete Card 04 or the onboarding journey.
