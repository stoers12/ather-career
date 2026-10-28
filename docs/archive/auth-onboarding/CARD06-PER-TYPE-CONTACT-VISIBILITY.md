# Card 06 — Per-Type Contact Visibility

Bounded implementation based on `fc4554a317f8305556dc299cb635c081edb23171`.
Implements AUTH-ADR-018's per-type contact rule for the existing Profile form.
This slice is separate from overall Card 06 completion and launch approval.

## Stored/public contract

`personal_info.public_email_visible` and `public_phone_visible` are independent
enforced booleans, NOT NULL with default 0. They apply only to the saved Profile
email and primary phone respectively. Secondary phone has no public control and
is excluded from public projection. Auth0 identity email and historical draft
phone are never imported. First completion explicitly maps both flags to 0;
receipt contract/revision, historical evidence and exact retry behavior remain
unchanged. A later receipt read/retry does not write current Profile choices.

`loadPublicPersonalInfo()` filters each value in SQL before shared presentation.
The existing published/active-owner resolver, direct mailto/tel validation, public
contact error renderer, private preview and media rules remain. Public project
JSON/API allow-lists gain no contact fields. Public page/contact-error routes use
the same projection; metadata and shared Hero/social/contact rendering receive
only the filtered values. This does not redact contacts that an owner separately
writes into an already public biography or other public text field.

## Form and write boundary

The existing Profile form now has two labeled checkboxes with individual help
and field-error associations. Secondary phone is explicitly described as private.
No new page, authorization framework or publication prerequisite is added.

| Type | Explicit value | Checkbox presence marker |
| --- | --- | --- |
| Profile email | `public_email_visible=0` or `1` | `public_email_present=1` |
| Primary phone | `public_phone_visible=0` or `1` | `public_phone_present=1` |

Values and markers must be exact strings. A marker with an absent checkbox is an
explicit off action for that type only; a value can also express 0/1 directly.
Omitting both preserves that type. Malformed values, markers or unsupported keys
reject the entire patch. Text/photo changes without visibility intent preserve
both settings. Valid choices are retained with text and the originally loaded
`expected_profile_version` after validation/conflict/uncertain outcomes.

Any retired aggregate form key (`public_contact_visible` or
`public_contact_present`, including a malformed value) returns a safe409 with a
clear outdated-form/reload/review message and retained text. It never guesses two
choices from one old checkbox, even if the old token is still numerically current
or the request also supplies new fields. A deliberate reload is required.

The existing current-session authorization, User -> Portfolio locking, exception
mode/owned transaction, CSRF, atomic Profile CAS, conflict recovery and media
cleanup contracts remain. Same-version concurrent text/contact or contact/contact
saves have one winner and one conflict; there is no silent merge or automatic
retry. Existing uncertain COMMIT503 guidance is retained. A possible commit is
not replayed, rolled back by claim, or compensated by deletion.

## Migration021 and compatibility

The next additive migration is `021_profile_contact_types`. It runs under the
existing migration lock and exact schema/constraint validation conventions.
Existing aggregate 0 maps to [0,0]; existing aggregate 1 maps to [1,1], preserving
previous exposure. Existing text, ownership, profile_version and updated_at are
unchanged. Fresh profiles use [0,0] unless an owner explicitly chooses otherwise
in Profile. Migration/startup does not capture any real owners or activate gates.

The guarded phases are:

1. One atomic ALTER adds both nullable NULL-default columns and exact enforced
   boolean checks. NULL is an uninitialized migration sentinel, never an app
   visibility choice.
2. A single atomic UPDATE copies the aggregate only into rows whose two new
   values are both NULL, explicitly preserving updated_at. Initialized pairs are
   never recomputed. Interruption before/after that statement is recoverable.
3. One atomic ALTER makes both columns NOT NULL/default0. If this committed but
   the migration ledger insert did not, the exact final shape is verified and
   retained with no backfill. Applied migrations also verify the final shape.

Mixed column shapes, a single missing column, wrong type/default/check,
non-enforced check or a row with only one NULL sentinel stop safely for manual
inspection. Complete exact staging shapes resume; complete exact final shapes
are treated as interrupted final DDL, preserving all independent values.
No OR/AND aggregate synchronization, trigger, contact-consent framework or
global transaction/isolation change is introduced.

`public_contact_visible` remains physically present with its old default/check as
**frozen legacy compatibility history**. New code does not select it for Profile,
accept it in editable patches, update it, or use it for public output. New rows
leave its old default0; it is not a summary of the independent settings. It is
consulted only for uninitialized rows during migration021.

**Operational/old-binary rollback limit:** no deployment or live migration is
authorized here. Any later rollout must quiesce all Profile writers while
migration021 is staged and switch all readers/writers together. Running an older
binary concurrently or rolling it back against this database is unsafe: it reads
the stale aggregate and could re-expose a type the owner later hid. Its old
readiness manifest also does not match ledger021. Do not drop the new columns,
re-run aggregate backfill, or reduce independent choices to OR/AND for rollback.
Use reviewed forward repair or a separately approved data-preserving recovery
plan. This change provides no down migration or old-binary compatibility promise.

## Verification integration and limits

`scripts/test/run-contact-visibility-tests.php` exercises real MySQL schema recovery,
four-combination SQL/HTML/mailto/tel/contact-error projection, private defaults,
readiness, malformed/retired forms, explicit off/omission, account isolation,
genuine two-worker Profile CAS and definite/uncertain transaction outcomes. It
checks all pre-existing named row values and ledger/session/fixture cleanup.
Existing Profile HTTP regressions now cover real public/error/JSON/preview routes
and old aggregate409 retention. Existing completion CLI/HTTP tests assert both
private defaults and preservation of later independent choices on exact retries.

The final-profile browser regression adds both checkbox labels/help, keyboard
operation, four save/reload combinations, stale-choice retention and responsive
controls. CI runs the new CLI suite and the affected existing suites. Restored
startup fixtures include a completed owner with email-on/phone-off and full-dump
preservation through migration021. This bounded startup check is not a complete
disaster-recovery exercise. Guarded smoke/DR provisioners now explicitly seed the
two flags; changing those fixtures does not prove their standalone orchestration
ran. Local/hosted results and retained corrected failures belong in the review
handoff; CI integration alone is not evidence of a hosted CI run.

Migration rehearsal comparisons canonicalize associative row key order because
DROP/ADD of older columns can move ordinals after additive021. Every named value
and timestamp is still compared; this test-only adaptation does not weaken data
or schema validation. Existing historical completion/Profile handoffs describe
their earlier aggregate behavior; this document supersedes that current runtime
behavior without rewriting historical evidence.

Production policy resolvers remain null, registration remains Google-only, and
all actual gates remain off. No push, PR, deployment, live migration, real-owner
capture, real authentication, Auth0/Notion or canonical service/data changes are
part of this slice. Overall Card 06 remains incomplete.
