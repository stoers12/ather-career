# Stage-3A Owner experience conventions

This contract covers the active Owner workspace only: `owner.php`, onboarding,
profile and skills, projects, experiences, messages, publication, private
preview, and the shared Owner layout. Public Portfolio presentation remains a
separate, accepted contract.

| Surface | Route/template | Server result and no-JavaScript contract |
| --- | --- | --- |
| Dashboard | `owner.php` | Owner-scoped aggregate or sanitized availability error; navigation remains ordinary links. |
| Onboarding | `owner_onboarding.php` | CSRF-protected create redirects on success; sanitized availability error stays visible. |
| Profile and skills | `owner_profile.php` | Owner action result supplies field errors and retained scalar values; media selection is never retained by the browser. |
| Projects | `owner_projects.php` | Owner action result retains fields on validation failure and redirects after success. |
| Experience | `owner_experiences.php` | Owner action result retains fields on validation failure and redirects after success. |
| Publication | `owner_publication.php` and `includes/owner_publication_presentation.php` | Lifecycle validation/conflict remains sanitized; the submitted slug is retained only for slug validation. |
| Messages | `owner_messages.php` | Read-only, Owner-scoped message list/detail with sanitized missing/availability state. |
| Preview | `owner_preview.php` | Read-only private presentation; no form or public eligibility change. |
| Sign-in, logout, and failures | `owner_login.php`, `owner_logout.php`, shared HTTP/Owner flow | Auth0/OIDC redirect and existing authorization/error contracts remain unchanged; logout is CSRF-protected. |

## Form feedback and state

Server validation is authoritative. Owner mutation handlers return stable
field-error keys, and the shared Owner feedback helper renders an escaped error
beside the associated field, `aria-invalid` only for that field, and a focusable
page summary. Submitted scalar values are rendered from the handler result after
a validation response; file controls deliberately cannot retain a selected file
under browser security rules.

Saving an unchanged, valid profile is still a successful Owner-scoped update.
The persistence helper distinguishes that normal zero-row database result from a
missing or cross-Owner record, so the UI never presents a false availability
error for an unchanged profile.

Owner forms continue to work without JavaScript. With JavaScript, an eligible
submit control is temporarily disabled, retains a stable width, and announces a
specific pending label. A browser back/forward restore re-enables it. This is
only duplicate-submit protection; it does not duplicate server validation or
change CSRF, redirects, authentication, ownership, or transaction behavior.

Server success and error messages remain visible page content with appropriate
live-region semantics. Empty collections state what is absent and link only to
the supported next action.

## Confirmation and eligibility

Project, skill, experience, and media removal use the shared keyboard-accessible
confirmation dialog. Publishing and unpublishing use the same confirmation
because they change public availability. The confirmation is progressive
enhancement: the server-side CSRF, Owner authorization, publication eligibility,
transaction, and media-compensation rules remain mandatory.

Private Owner resources remain Owner-scoped. Public routes and public media
remain available only for an eligible published Portfolio and its published
resources. No Owner identifiers or internal failures are added to feedback.

## Stage boundary

Stage-3A standardizes existing Owner workflow states and form feedback. It does
not add Resume uploads, snapshots, insights, inbox features, SEO, RTL, new
frontend infrastructure, or a public Portfolio redesign. Those concerns remain
for later Stage-3 work.
