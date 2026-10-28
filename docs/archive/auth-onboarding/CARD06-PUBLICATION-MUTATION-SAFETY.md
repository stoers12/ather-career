# Card 06 — Publication Mutation Authorization and Transaction Safety

Bounded local successor: [Card06 per-project publication](CARD06-PER-PROJECT-PUBLICATION.md). The independent project-flag action reuses the same owned transaction/current authorization and lock order. Earlier three-helper evidence retains its scope; public project reads now also require the independent flag.


Bounded implementation based on `c797f13a9fb79f1a39ba4776f876452fd326a454`.
This slice is distinct from overall Card 06 acceptance and launch readiness.

## Write contract

`setOwnedPublicSlug()`, `publishOwnedPortfolio()` and
`unpublishOwnedPortfolio()` require a current internal authenticated session.
A server-created `AuthorizedPortfolioContext` specifies the expected User and
Portfolio; it does not grant mutation authority. Inside an independently owned
transaction, the existing authentication primitive checks the current User,
account status, session authorization version and lifetime with `FOR UPDATE`.
The supplied User must match. Next, the expected Portfolio is locked with its
current owner predicate. Existing journey rules then require either completion
bound to that User, Portfolio and original Profile, or the existing sealed
legacy-owner eligibility. There are no new readiness or policy requirements.

The order is **User -> Portfolio -> journey proof/current Profile -> projects**,
compatible with Profile saves. All reads deciding authorization, publication
state, first-publication permanence or the required name are current locking
reads. An earlier repeatable-read snapshot cannot authorize a write. The
legacy seal uses a shared current read; completed owners retain their existing
access independent of that seal. No isolation level is changed.

## Transaction ownership and failure

The production caller inventory is `owner_publication.php`; it does not open an
ambient transaction. The helpers reject ambient transactions and non-exception
PDO mode with `LogicException`, before touching caller work. They never commit
or roll back a caller transaction. This is deliberate, like Profile's owned
transaction contract: arbitrary earlier caller locks/snapshots cannot be
composed safely into this short write boundary. Test both an existing RR
snapshot and uncommitted caller work when checking this rejection.

Before COMMIT, an acknowledged rollback rethrows the original failure.
Once COMMIT is attempted, false return, exception or lost acknowledgement is
`PublicLifecycleWriteUncertainException`, even if a later rollback succeeds.
An unacknowledged rollback is also uncertain. The HTTP response is private503
and says the change may already be saved. It does not replay the POST, claim
rollback, delete data or compensate. An explicit GET of Publication performs
the ordinary current authorization and owned-state read on a fresh request.
If that connection/session cannot authorize or read state, success is unverified.
No new receipt or operation-version mechanism is introduced.

An authorization or owner change detected at the mutation boundary raises the
existing `AuthorizationDeniedException`; the route returns the existing safe403
denial. Existing entry redirects for anonymous/incomplete users, CSRF and
publication rate limits remain in place.

## Concurrent ordering

Revocation/account disable committed before the User locking read is observed
and denies the mutation. Once publication holds that User lock, a conflicting
revocation waits for the transaction to end. Publication may therefore commit
first; subsequent requests with the revoked session are denied. This does not
promise retroactive cancellation or removal of a previously committed publication.
The existing public resolver still hides a disabled owner's Portfolio.

An ownership change committed before the Portfolio lock is observed and denies
the old owner's write. A change conflicting with an already held Portfolio lock
waits. There is no new transfer API. Any future multi-row transfer use case must
respect User -> Portfolio ordering; arbitrary SQL taking locks in reverse order
can still deadlock, and a failed mutation is never automatically retried.

Profile, slug and publication helpers sharing these locks serialize on the
same owner/resource. Publication reads the current name and slug after locking.
First publication makes the slug permanent, including after unpublish.
Serialized publish/unpublish retains the existing last successful operation
behavior; no publication version, CAS or staged snapshot is added. Slug
normalization, reserved names, uniqueness, name+slug requirements, first timestamp,
project timestamp refreshes and public projections retain their existing rules.

## Verification and fixture changes

Run `php scripts/run-publication-mutation-tests.php` in the existing migrated,
disposable Docker test namespace after the migration/ownership prerequisites.
CI runs it using the existing scoped rehearsal database account, after the
Profile upgrade checks and routing fixtures. It checks real helpers, the real
HTTP route, two-connection scheduling, actual row-lock conflicts, old snapshots,
rollback/COMMIT injection and complete protected-data/resource cleanup.

Legacy CLI publication regressions now establish synthetic sessions and accepted
synthetic legacy eligibility. They still invoke the real mutation helpers.
The Experience rollback fixture and guarded public-read smoke/recovery provisioners
seed synthetic publication state directly: they have no authenticated Owner
session and are not mutation authorization tests. Their existing namespace guards
remain. No production authorization bypass or session/config injection seam was
added; HTTP fault construction exists only in a private test config copy.

## Retained evidence and limits

The retained audit's `REPORT.md` predates its final expanded result. Its
16-case/33-request narrative is not rewritten as a final measurement.
`audit-result.json` records 20 cases/41 HTTP requests plus two concurrent workers;
`RUN-SUMMARY.json` preserves each run separately. `ADMINISTRATIVE-CLOSEOUT.md`
records that packaging was not approval, the original302-vs303 assertion failure,
historical reused baseline CI, incomplete Docker event history and the absence
of new browser/Apache/device/screen-reader/provider verification. Those are
historical audit evidence, not verification of this implementation.

No broad audit or previously withheld payload is requested or reconstructed.
New verification is limited to this mutation slice and affected regressions.
Scheduling/fault injection cannot prove every possible interleaving or an actual
network/kernel failure. Local results and any remaining gaps belong in the
review handoff; CI integration alone does not mean hosted CI ran.

Privacy controls/per-type consent, schema, policy resolvers (still null), actual
gates (still off), Auth0, real authentication, capture/migration/deployment and
canonical services/data are outside this slice. Overall Card 06 remains incomplete.
