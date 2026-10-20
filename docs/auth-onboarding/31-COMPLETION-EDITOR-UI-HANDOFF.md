# Card 04 — bounded private editor completion review and recovery

Baseline fetched/verified: `38b4db27c608bc47a40d93c72742db3e5e918921`.
Worktree `.worktrees/onboarding-completion-editor`, branch
`feat/onboarding-completion-editor-20261020`. Applicable ancestor instructions,
README maintenance contract, contract 22 accepted overlay, handoffs 27–30 and
actual editor/core/HTTP/attestation/authorization/session/CSRF/browser fixtures
were read first. No applicable AGENTS.md exists. Canonical unrelated changes are
preserved; before/after hashes and commands live outside source in
`.worktrees/onboarding-completion-editor-review`.

## Bounded implementation

The existing private editor uses `/owner_onboarding_completion.php` without changing
its contract or the core. Current AR/EN design, native controls, themes and layouts
remain. No framework, capability gate, migration, catalog change, policy text or
resolver activation. Both production resolvers remain literal null and all actual
gates off; registration Google-only/default off. Guarded design preview remains
nonpersistent and Create-disabled. No routing/Dashboard/legacy/publication cutover,
optional import or existing-owner gate; Card 04/journey/launch remain unfinished.

Save and review may complete existing section saves using immutable pending draft
operations. It requires acknowledgement of all local changes with actual version;
uncertain/conflicted saves block review. It never chains into completion. Responses
render the saved server projection, not the live preview or mutable input. All
fourteen positions are visible: seven initial Profile/link fields and seven deferred
private historical draft fields. Values use textContent/pre-wrap/anywhere wrapping,
no truncation/normalization/default/HTML interpretation. Absent values are explicit;
server disposition remains exact bilingual text. Connected edit paths return to
existing controls. Unsupported country/long headline/invalid link require deliberate
correction and acknowledged save. Unrepresented optional values stay privately as
saved; this slice supplies no import/collection/edit mapping or retention decision.

Two separate initially unchecked confirmations explain private unpublished creation
and optional-data disposition; they do not replace policy actions. Create starts
disabled, and only a current server review/acknowledged version, both actions and no
pending/conflicting/busy operation enable it. An explicit click generates one
version-bound operation ID and serializes exactly action plus the five core intent
keys. Busy repeat activation is ignored; language replacement is blocked during
confirmation dispatch. Review/actions invalidate on edits/save/resume/document/
language/DOM/auth changes. Request/root/auth-epoch guards and abortable listeners
retire obsolete work; existing pagehide/persisted-pageshow stop remains.

Receipt GET starts independently of draft loading and verifies CSRF equals the
original editor anchor; it can recover even if historical draft reading fails, without
old tokens/session/readiness/policies. Original receipt/time is historical and never
hydrates current Profile. Known completion freezes new draft mutations/creation;
local unacknowledged values/pending bytes remain visible, never labeled saved or
promoted. Completion uncertainty retains exact dispatched bytes in RAM, offers
explicit receipt read and never automatically replays POST or creates another intent.
This UI deliberately offers no completion POST retry. A null read says an earlier
request may still finish; pending intent survives language replacement. Reload can
recover using GET without browser storage. No automatic Dashboard redirect.

Unavailable completion leaves independent draft/attestation behavior intact.
POST access_denied can mean unmet readiness/exact evidence and is not automatically
session expiry. GET authorization denial, invalid CSRF or observed context mismatch
stops all protected boundaries. No new readiness TTL/provider freshness claim.
Tokens, bodies/actions/intents stay RAM/DOM only, never browser storage/URLs/logs/
telemetry/HAR/raw HTML exports. Production core/API/mappings and exact retry order
remain unchanged.

## Verification and remaining gaps

Final commands/results, retained failures/corrections and exact gaps follow. Successful policies are only guarded private source copies with synthetic
sessions/disposable migrated MySQL. No real policy approval/login, physical device/
Safari/manual screen-reader or physical daemon/network partition is claimed.


## Final local commands and actual results

Disposable MySQL 8.4.11 / PHP 8.3.33 and persisted synthetic Owner sessions; read-only
source mounts and container-local execution copies. Browser: existing Playwright
1.58.0 Chromium on Windows. No real login, provider call or canonical DB/service.

| Command / evidence | Result |
| --- | --- |
| `validate.ps1`, `validation-b87ee870c149.log` | All 20 ordered CLI invocations PASS. Completion HTTP 12/core 10, attestation HTTP 12/core 5, draft core 11/HTTP 10, Profile core 5/HTTP 6, final Profile core 5/HTTP 7, renderer 3/preview 572; ownership/public/tenant/Experience/owner/mock OIDC/registration/routing/foundation/Sign In/readiness regressions PASS. Scoped user/fixtures/resources cleaned. |
| `completion-browser.ps1`, `completion-browser-db9d5e797a29.log`, `validation-db9d5e797a29.log` | All 14 new browser groups PASS: original null/off; exact fourteen saved positions/disposition and absence; harmless hostile text; immutable draft retries/newer edits; invalid headline/retired country/reserved slug deliberate correction; fresh/changed review/bundle/language and safe temporary/prerequisite errors; double-click/busy actions; explicit private creation; original receipt/reload/later session and later Profile CAS edit; retained unsaved values/freeze; possible COMMIT/lost/absent response explicit GET only, including failed draft read and original null policies; newer review versus obsolete receipt; delayed account/context/POST/bfcache/expiry; AR/EN/RTL/LTR/themes/1440/1024/768/390/320/keyboard/focus/live status. Sixteen final redacted synthetic screenshots. Protected-data snapshot, deferred fields, private logs, process/session/source/file/database cleanup and clean fixture exit PASS. |
| `editor.ps1`, `editor-browser-5829763f86d8.log`; `attestation-browser.ps1`, `attestation-browser-1f0f43c16bf5.log` | All existing 11 editor and 12 attestation browser groups PASS on final functional source; protected fixture data, private process/session/files and resources cleaned. Existing checkbox assertion now includes two new disabled confirmations rather than dropping the original five. |
| `production-check.ps1`, `production-f3926d076f93.log`, `production-build-final.log`, `php-lint.log` | Production build/PHP lint, Apache private no-store completion GET/POST default-off 404, health/readiness/AR-EN/public/legacy/private catalog boundaries PASS. Repeated migrations/security gate and exact 001–019 ledger PASS; nonempty completion/Profile/draft/exact attestation restored startup preserves full dump (`57f0ee74650d916c8bff246ba0ce33d939bc86dbeb31ebd8483009f31447d2a4`). Restore DB/files, two containers, three volumes/network/credentials removed. |
| `production-build-final-source.log`, `final-image-source-hashes.txt`, `image-source-verification.json` | Final cached source rebuild; eight functional root files and both public JS/CSS copies match final source SHA256 after LF normalization. Both production resolvers remain literal null. |
| `static-checks.py`, `static-results.json`, `bash-syntax.log`, `linux-node-carrier.log` | 144 protected source files unchanged, exact old CI steps/assertions/migration order/scoped credentials/cleanup after removing only new step/fallback, all twenty calls, YAML/26 Bash blocks/Node/diff and 235 documentation links PASS. Real Linux UID/GID 1001 Node denied root-owned 0600 file, then privileged ownership handoff preserved 0600/0700 and ordinary non-root O_NOFOLLOW readability; synthetic carrier/tool container removed. |

New CI step copies the proven Profile/attestation private carrier and orderly cleanup:
actual host UID/GID, 0600 file/0700 directory, privileged chown before ordinary Node
read; stop signal, bounded 45s wait, clean exit and exact fixed preservation evidence,
then container/scoped-user/carrier cleanup. Browser failure status is preserved;
missing evidence, timeout/forced removal or cleanup failure fails the step. Existing
twenty CLI calls, browser suites and final cleanup remain exact; a completion
fallback detects leftovers. Full hosted integration is not claimed locally.

## Retained failures and source-review corrections

Original failing logs remain alongside final evidence; no acceptance/assertion was
weakened and no failed attempt is presented as a successful preservation run.

- `f3dc22c3acdc`: off/null test reused the same User/value; the second no-op save
  correctly sent no patch. Distinct synthetic edits now prove independent saving.
- `ca2b4da3ccf7`, `e98b1e9618d8`: after switching to mobile for a screenshot, the
  test tried clicking a hidden desktop step jump while already on review. The
  navigation helper now keeps an already visible target panel. The precise failure
  stage was retained. This changed no application navigation or assertion.
- `bbb5968d9b85`: a later-session receipt could become completed before the helper
  observed transient saved state. Opening accepts acknowledged saved OR completed,
  then explicitly asserts original receipt; the UI never labels completion as saved.
- `348f2a64d9ec`: a repeat Windows Node process exited `-1073740791` before any test output (cause unconfirmed). The fixture was explicitly stopped and preserved/cleaned; the external harness now signals stop after every native exit. Serial rerun `66a821bff606` passed all 11 unchanged groups. Its redundant Docker stop command found the fixture already self-cleaned; wait/snapshot/cleanup were successful. The external helper now writes a host stop marker only after a failed browser exit, avoiding that benign race without changing assertions.
- Source review separated historical receipt reads from attestation unavailability,
  retained authorization denial handling ahead of ignored late draft acknowledgements,
  bound save-to-review continuation to its entry root/auth epoch, and invalidated
  older receipt reads on newer edit/save/review. The last case has its own real
  browser group. Receipt recovery is also tested while draft GET fails and through
  the original production null-policy resolver; no old token/browser storage is needed. Shared listeners now retire with replaced markup.
- Visual inspection reused existing `ao-check` cards for the two confirmations and
  replaced editor-only illustrative-preview wording with truthful saved-review copy.
  The guarded nonpersistent preview is unchanged. Final screenshots were regenerated.

Final selected suites have zero failed/skipped groups. `commands-results.json`
records every actual group, final filenames, retained failures and gaps. Full patch,
source hashes/reconstruction and archive/Desktop verification live outside source.

## Explicit gaps and next safe action

- No hosted CI run or integrated Linux ordinary Node+Chromium fixture run (no push).
  Local Windows Chromium browser tests and separate actual Linux Node permission
  checks are distinguished. CI syntax/order/preservation logic is checked, not
  represented as a hosted successful job.
- Existing five-group Profile browser step is unchanged in CI and was not rerun
  locally; Profile core/HTTP and final Profile core/HTTP passed in the twenty calls.
- Automated keyboard/focus/live-region/RTL/responsive tests do not certify manual
  screen readers, physical devices or Safari. Synthetic persisted-pageshow tests
  validate the boundary, not every engine's actual BFCache eligibility.
- PDO faults surround real MySQL transactions/COMMIT; lost response is browser
  transport interception, not a physical daemon/network partition. Delayed original
  intent can complete after explicit null recovery; the UI offers no POST retry.
- Production Apache smoke stays gate-off. Enabled success uses guarded private
  source-copy PHP HTTP servers. No real policies/legal approval, real authentication,
  provider freshness, deployment/live migration, gate activation or launch.

Next safe action: owner review of this local commit and verified bundle. Real
policies, routing/cutover, optional import/retention/deletion, publication and launch
remain separately bounded; this slice does not finish Card 04 or the whole journey.
