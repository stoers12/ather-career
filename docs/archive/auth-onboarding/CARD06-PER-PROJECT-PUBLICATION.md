# Card 06 — Bounded per-project publication

Based on `2be83fd4169937c63298e887d003e33c632c4308`. The owner explicitly authorized
this local implementation and its technical legacy transition in disposable
databases. This supersedes the preparation report's unresolved-transition status
only for this reviewed slice; it grants no live migration or launch authority.

## State and transition

Migration022 adds `projects.is_published`, enforced 0/1, NOT NULL/default0.
All creation helpers explicitly store0, including creation with private Evidence.
Content/image/Evidence edits preserve the flag. A separate explicit action selects
or hides one project; Portfolio publication is an independent prerequisite.

Existing projects inherit their current Portfolio's 0/1 state exactly once under
continuous exclusive maintenance. This preserves current exposure, including
published-but-disabled owners remaining hidden by account status. A currently
unpublished Portfolio's projects initialize0 even if it was published earlier.
This technical transition is not historical individual consent; no acceptance
event or timestamp is fabricated. Evidence remains private and excluded.

The guarded migration adds nullable/defaultNULL plus an enforced check atomically,
validates associations, updates only uninitialized NULL rows in one statement,
preserves existing content/created_at/updated_at/ownership, then finalizes private
defaults. Exact staging shapes resume; initialized values never recompute, including
lost final-DDL/ledger acknowledgement. Wrong defaults/types/checks, orphan binding
or ambiguous maintenance state stop for review. The advisory migration lock does
not stop writers. Runtime/startup does not perform recurring publication adoption.

## Owner boundary

The existing Projects card renders stored state and one CSRF-protected POST form
(`set_project_publication`, id, exact string0/1). Private Portfolios describe a
selected project as waiting for Portfolio publication. GET/HEAD never mutate.
Content forms cannot set the flag. Strict input/file admission precedes mutation.

`setOwnedProjectPublication` reuses publication's owned-transaction boundary and
User -> Portfolio -> journey proof/current Profile -> project locking order. It
revalidates current active account/session/authz/lifetime and current ownership;
completion or sealed legacy binding retains existing policy. A supplied context
does not authorize. Ambient/non-exception transactions are refused. Same-value
assignment is a verified no-op; real changes refresh only that project's updated_at.
Explicit assignments serialize; no new CAS, receipts or automatic retries.

Acknowledged precommit rollback is distinct from uncertain COMMIT/rollback ack.
Uncertainty returns private503 with explicit Projects reload/review, no success
claim, automatic replay, compensating deletion or image cleanup. A revocation
committed before the User lock denies; later conflicting writes serialize, with
no retroactive cancellation guarantee. Existing CRUD is not globally redesigned.

## Public read boundary

One fixed SQL eligibility fragment is reused by HTML, allow-listed JSON and the
project-media query. It joins the current project to its Portfolio and current
active owner, requires that Portfolio published and project flag1, and scopes to
the resolved Portfolio. A stale/manually created PublicReadContext is insufficient.
The normal public slug resolver remains; no viewer Owner session is required.
Contact-error HTML uses the same filtered loader. JSON retains its own allow-list,
without flags, private keys, raw paths, identities or Evidence. Media resolves its
managed derivative only after eligibility; private/foreign/missing media is404.
Authorized owner preview and owner media continue to include private projects.

Portfolio hide/republish never changes project flags; only selected projects return.
The existing Portfolio timestamp behavior is retained. This does not add Evidence
publication, slug changes, Dashboard design, new policy gates or new providers.

## Verification and compatibility

`run-project-publication-tests.php` uses existing guarded MySQL/session/publication
worker and HTTP fixtures: defaults; populated/partial/retry/lost-ledger migration;
strict checks/defaults; current authorization; ambient refusal; unchanged saves;
real row-lock conflicts; explicit-choice/Portfolio concurrency; rollback/COMMIT
faults; HTML/JSON/contact errors; an existing valid PNG with public200/private404;
and exact existing-row/ledger/seal preservation plus fixture cleanup.

The optional existing-style browser carrier runs real controls, keyboard actions,
save/reload, content-edit preservation and responsive captures with Playwright.
CI retains prior suites and adds the focused rehearsal/browser step using existing
scoped users, private carrier permissions, stop/cleanup and tools. Historical
public fixtures now explicitly select their synthetic projects, never bypass
production authorization. Changed fixtures are not proof their standalone runner ran.
Actual commands, retained failures/corrections and untested boundaries belong in
the external local review handoff; no hosted CI success is claimed here.

Readers/writers must switch together under separately approved maintenance. New
code requires022; old code ignores flags and may re-expose a project hidden later,
and its ledger manifest is incompatible. No mixed binaries, blind old-code rollback,
column drop or startup/backfill recomputation. Prefer reviewed forward repair or
separately approved data-preserving recovery. Contact021's old-binary limit remains.
Live backup/restore/capture/cutover approval is separate. All actual capabilities
stay off, production policies null, registration Google-only, Card07 paused.
