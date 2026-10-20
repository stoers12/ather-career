# Card 05 — bounded authenticated pause/resume and Dashboard navigation

Baseline / fetched remote main verified before editing: `d30cd5ba7c58cb19bdabab95d1d32349c1b18e50`.
Isolated branch `feat/registration-journey-navigation-20261020`, worktree
`.worktrees/registration-journey-navigation`. No applicable ancestor/repository
AGENTS.md exists. README maintenance contract, handoffs 29–32, accepted completion
contract overlay, actual journey/shared authorization, editor/renderer/copy,
draft/attestation/completion transport and private fixture code were reviewed.
Canonical HEAD and unrelated changes remain preserved; before/after hashes and
logs live outside source in `.worktrees/registration-journey-navigation-review`.

## Bounded behavior

Reuses `/owner_onboarding.php` and its existing packaged public shim. Strict raw
query admits only exact pause presentation and ar/en interface language (no duplicate,
array, encoded alias, return URL or authority override). Normal legacy routing remains;
validated language alone can accompany the fixed editor destination. POST remains
retired before database work. All modes independently authorize the current User
and reuse `ownerJourneyDestination()`; no authority/capture change.

Pause describes acknowledged saves only. It creates nothing, records no policy
acceptance, does not log out or claim completion. Current server editor destination
permits an explicit Resume through the normal server routing; current accepted-owner
Dashboard destination permits only its fixed Dashboard link. Unavailable capabilities,
null policy documents or unavailable dependencies give a private/no-store holding
response and safe account actions, without usable Resume/Dashboard or loops. Current
legacy owners retain Dashboard without new policy/readiness requirements. Unproven
bare Portfolios are never repaired/adopted. No new route, migration or state table.

Save and exit uses the existing save loop and serialized immutable operation bytes.
It awaits all intended acknowledgements, including newer edits during dispatch,
then navigates to fixed pause+validated UI language. Failed, rejected, conflicted,
uncertain or invalidated saves retain input/pending work in the editor. Explicit retry
does not revive an old exit. Resume obtains saved server fields, not local step state.

The editor has one initially hidden localized Dashboard link in global feedback.
Only a validated successful completion or context-matched authorized receipt reveals
it. It targets `/owner.php`, which independently reauthorizes. No automatic redirect
or POST replay occurs on receipt, reload, null, uncertainty or late responses.
Historical receipt never hydrates current Profile. Completion elsewhere freezes
mutation while retaining local values/pending work; the feedback explicitly says
unacknowledged local edits were not included. Dashboard leave keeps the existing
native beforeunload protection, allowing cancellation without losing input.

Existing language/DOM/root/request/auth epochs, pagehide/persisted-pageshow and
independent draft/attestation behavior remain. No automatic editor GET redirect,
return URL, public registration redesign, provider change or new access gate.
Guarded preview remains nonpersistent/Create-disabled. Production policy/content
resolvers remain literal null; actual gates and Google-only registration stay off.
Completion core/API, migration 020, maintenance capture, catalogs and public outputs
are unchanged. This is not launch or completion of the entire Card 05.

## Test isolation and CI

Existing real-HTTP journey suite gains two pause groups without dropping its ten
original groups or legacy/bare bypass checks. Existing completion browser suite
keeps all fourteen groups, adds four journey-navigation groups and strengthens
retained-input/uncertain/late checks. Existing eleven editor groups retain all
assertions with the newly intended fixed pause destination and a newer-edit save
race. Renderer requires one initially hidden fixed Dashboard action. CI executes
these same scripts; all twenty-one CLI calls, migration order/scoped credentials,
other suites, non-root private-carrier checks, 0600/0700 and orderly cleanup remain.

Successful pause browser cases reuse the guarded routing-fixture complete private
source copy with reversible synthetic resolver substitutions. They never affect
production selection. The fixture creates an explicitly empty synthetic cohort seal
only if absent and removes only that owned seal before exact final snapshot comparison;
it never captures/adopts owners. Existing completion source copies and persisted
synthetic sessions remain; additional revocation is guarded CLI-only. Private source,
carrier/session/files/processes/database cleanup and existing data preservation are
verified on fixture exit. No real authentication, policies or canonical DB access.

## Commands, results, retained failures and gaps

Final local evidence is recorded below before commit. No hosted CI, manual physical
device/Safari/screen-reader testing, real policy/provider validation or actual
production activation is claimed. Browser automation uses synthetic sessions and
existing Chromium; private controls/tokens/bodies are not exported to HAR, raw HTML
or storage. Screenshots contain redacted synthetic visible data only.

## Remaining Card 05 work

This slice closes only pause/resume and explicit post-receipt Dashboard navigation.
Broader public entry/account-navigation review and separately scoped remaining
Card 05 work require owner review. Real immutable policies, provider-environment
end-to-end verification, launch/retention decisions and controlled exclusive-maintenance
cutover preparation remain. No deployment/live migration/capture/activation or
Auth0/Notion/canonical-service/data change was authorized or performed.

## Final local verification

| Command / final retained evidence | Actual result |
| --- | --- |
| `validate.ps1`; `validation-c4baaab513b5.log` | All 21 ordered CLI invocations PASS; journey 12 (original 10 plus 2 pause), completion core/HTTP 10/12, attestation HTTP 12, draft core/HTTP 11/10, Profile core/HTTP 5/6, final Profile core/HTTP 5/7, renderer 3/preview 572; ownership/public/tenant/Experience/mock OIDC/registration/routing/foundation/Sign In/readiness regressions PASS. Generated scoped user and disposable resources removed. |
| `completion-browser.ps1`; `completion-browser-a00bed58eb71.log`, `validation-a00bed58eb71.log` | All original 14 completion groups plus 4 navigation groups PASS. Newer edits drain before exit; exact saved pause/resume; rejected/lost acknowledgement remains editor; exact immutable retry does not revive exit; explicit keyboard Dashboard under null policies and later Profile edits; other-tab local edits/AR-EN replacement/native beforeunload cancellation; null/uncertain/obsolete/auth outcomes; private pause 320px/390px/desktop and revocation. 22 final redacted synthetic screenshots. Protected/deferred data, private logs, processes/sessions/source/files/database and fixture cleanup PASS. |
| `editor.ps1`; `editor-browser-8bde2c1f2db6.log` | All 11 editor browser groups PASS, including newer slug edit during exit save and exact acknowledged fixed pause target. Twenty redacted screenshots and protected fixture cleanup. |
| `attestation-browser.ps1`; `attestation-browser-10c6aa29b300.log` | All 12 existing attestation browser groups PASS, including account/late/localized markup/auth boundaries. Thirteen redacted screenshots and protected fixture cleanup. |
| `browser.ps1`; `browser-7e8c453b8fbf.log` | All 5 existing Profile browser groups PASS with 12 redacted screenshots and private fixture preservation/cleanup. |
| `validate.ps1 -Suite focused.sh`; `validation-d4e591e88579.log` | After preserving ordinary holding's existing bilingual recovery text, all 12 journey HTTP groups and existing readiness/legacy POST route PASS again. No full browser rerun for this final ordinary-holding-only text preservation; pause/editor branches and JavaScript were unchanged. |
| `production-check.ps1`; `production-e15334914f05.log`, `production-build-final.log`, `php-lint.log` | Final production build/all PHP syntax; packaged existing public pause shim GET/HEAD authentication/no-store, POST 405 and strict query 400; default-off completion GET/POST and existing smoke; repeated migration/security gate PASS. Exact full restored dump through 020 preserves nonempty completion/Profile/draft/exact attestation, frozen legacy set and excluded later bare row. Disposable databases/option/dump files, containers/three volumes/network/credentials removed. |
| `verify-image.py`; `image-source-verification.json` | Fifteen functional root/shim files plus packaged public shim and JS match current source SHA256 after LF normalization. Production image tag removed after audit; shared caches were not globally pruned. |
| `static-checks.py`, `static-results.json`, `bash-syntax.log`, `verify-cleanup.py`, `cleanup-verified.json` | YAML/26 CI Bash blocks/Node/diff and 255 local documentation links PASS. Exact existing CI steps/order/scoped credentials/assertions/permissions/cleanup differ only by explanatory comment; tests execute via the same scripts. Core/API, authorization/journey decision, all migrations/capture/catalogs/null resolvers/default configuration and existing shim/Dockerfile unchanged. Canonical HEAD/status/two dirty hashes and preexisting container IDs/names/states preserved; no task resources or private carriers remain. |

Final selected suites have zero failed or skipped groups. Full command/result/group
lists and hashes live in external `commands-results.json`. Final browser evidence
uses `editor-evidence-a00bed58eb71`, `editor-evidence-8bde2c1f2db6`,
`editor-evidence-10c6aa29b300` and `browser-evidence-7e8c453b8fbf`;
earlier screenshot directories are retained intermediate evidence. Representative
pause desktop/mobile, Arabic unavailable and AR/EN retained-local/Dashboard images
were visually inspected. Automated labels/focus/live status/native keyboard/dialog/
RTL/LTR/themes/responsiveness checks are not manual accessibility certification.

## Retained failures and corrections

validation-8fb031920609.log: new renderer assertion caught duplicate Dashboard anchor introduced by an overbroad markup replacement. Removed the unintended attestation-area duplicate; kept one global hidden action and kept the assertion. Fixture/resources cleaned. Full final rerun follows.
completion-browser-f735734436cb.log: new native beforeunload test waited for click completion before dismissing its modal dialog, causing a timeout after six passing original groups. Changed only the test dialog handler to dismiss as soon as the event arrives; retained native-dialog assertion and exact input/location checks. Fixture protected-data preservation and cleanup passed on the failed attempt.
completion-browser-9a878693f307.log: all fourteen original completion groups passed; new Dashboard test incorrectly expected the complete full_name in a greeting that intentionally shows its first word. Assert the exact Dashboard first word, then load the authorized existing Profile form and assert the complete unchanged name there. No product behavior changed; fixture preservation/cleanup passed.
validation-b58bf091611c.log: first denied Owner request correctly invalidated the expired/revoked session, so a second pause request on that cleared session redirected to Sign In instead of the new test expecting 403. Seed a separate persisted synthetic session for the pause first-denial assertion, retaining the original Owner 403 and pause 403 assertions. All prior suite groups and protected cleanup passed.
completion-browser-15476937d6b3.log: all fourteen original and first two navigation groups passed. New follow-on save-exit test targeted the desktop header button after retaining the 320px viewport (header action intentionally hidden). Use the existing visible footer action and restore desktop viewport before desktop progress navigation. Keep mobile capture, keyboard Resume and all lost/rejected-save assertions. Fixture preservation and cleanup passed.
completion-browser-764242b9d79a.log: all fourteen original and three navigation groups passed. Revocation recovery GET had already cleared the session; the following pause APIRequest followed its Sign In redirect and returned 200. Disable redirect following and assert exact 303 /owner_login.php for the already-invalidated session. Independent HTTP suite retains first-request revoked pause 403. No product acceptance changed; fixture preservation/cleanup passed.
production-9271af359e71.log / production-console.log: build/lint and earlier default-off smoke passed; external PowerShell Invoke-WebRequest treated the intentionally blocked 303 redirect as an exception. Use a disposable .NET HttpClient with AllowAutoRedirect=false to inspect exact status/Location/no-store. Keep all packaging assertions; complete resource cleanup passed.
completion-browser-90fa2175a148.log: follow-on test used the footer exit while still on step 2; that footer belongs to step 5 and is intentionally hidden. Restore desktop viewport immediately after the completed mobile pause/resume case and select the visible existing Save and exit control. Retain all mobile assertions/screenshots and rejection/retry checks. No product change; protected cleanup passed.
Initial image/source audit used /var/www/html for a public asset; Dockerfile uses /var/www/public. Hash audit failed on that missing test path only. Corrected the external audit path and added the packaged public shim; no image/application change. Initial audit script retained as runtime-hashes-initial.php.

All originals remain in the external review evidence. Failed browser attempts still
reported protected-data preservation and complete fixture cleanup; failed CLI runs
removed their scoped user/resources and the failed production smoke removed its
stack/volumes/network/credentials. No assertion or server acceptance rule was weakened.
Intermediate successful runs are distinguished from the final selection above.

## Verification limits

Local browser runs use existing Windows Chromium and disposable Linux MySQL/PHP.
The existing hosted Linux ordinary non-root Node private-carrier 0600/0700 handoff
and bounded fixture-exit cleanup are byte-preserved in CI, not claimed as a newly
executed hosted run. No hosted CI/push/PR, manual physical devices/Safari/screen
readers, real provider session, physical database/network partition, approved legal
policies, deployment/live migration/capture or actual gate activation. Source
reconstruction, patch/archive integrity, manifest hashes and Desktop-copy equality
are verified by the external packaging record after the local commit.
