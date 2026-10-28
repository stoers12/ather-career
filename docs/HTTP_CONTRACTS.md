# HTTP and validation contracts

Active HTML routes use the shared helpers in `includes/http.php`. Read pages
accept only their documented read methods; Owner form pages accept `GET`,
`HEAD`, and `POST`; and the Owner logout and public contact submission routes
are POST-only. Unsupported methods return `405 Method Not Allowed` with an
`Allow` header.

Owner pages without an authenticated Owner session redirect to the local Owner
login route. A request that is authenticated but fails authorization receives
`403`. Missing scoped resources receive `404`, and CSRF validation failures
receive `403`. Successful HTML form changes use a `303 See Other` redirect.
Validation failures keep the existing form recovery workflow, return safe
field-keyed errors, reject structured input where a scalar is required, and
preserve only non-sensitive submitted values.

The only active JSON read route is `/p/<slug>/projects.json`. Its JSON
contract starts only after the request matches the canonical slug route. A
non-matching path (for example, a slug containing an underscore) remains a
normal web-server `404` and is not routed into PHP solely to produce JSON. A
matched canonical route is GET-only and uses a stable sanitized JSON response
shape; missing, inactive, or unpublished Portfolios return JSON `404`.

Public Project JSON is an explicit allow-list, not a database-row
serialization. It contains only presentation fields, an optional valid HTTP(S)
`github_url` when one is stored, and an optional `image_url`, emitted only for an already-readable presentation asset and using
the scoped public Portfolio-media route. Raw managed-media paths, storage
keys, Owner identifiers, publication state, and operational metadata are never
public JSON fields. There are no active JSON
mutation or JSON request-body contracts. HTML form routes remain HTML
workflows and are not JSON APIs.

## Standard Owner logout

`owner_logout.php` remains an explicit POST accepting only the session CSRF
field, without query parameters or uploads. Invalid method/CSRF/input returns
405/403 before retiring the session. A valid request destroys the full local
session, including authentication, readiness and pending OIDC state, then sends
303 to the configured Auth0 issuer's `/v2/logout` with only `client_id` and the
fixed `returnTo=PUBLIC_BASE_URL/signin.php`. It never requests federated logout.
The exact return URI must already be in **Allowed Logout URLs of that client**.
No return/end-session URL comes from the request or Host header; no ID token,
sid, provider claim or secret is stored or included to support logout.

Missing/invalid trusted configuration after local retirement returns private,
no-store 503 with bilingual partial-logout feedback and a fixed `/signin.php`
link. Local authority is not restored. Local retirement failure is not reported
as success. The outbound redirect only initiates Auth0 SSO logout; a browser or
Auth0/network/allowlist failure after leaving Ather is not detectable here.
Neither the plain Sign In return nor a fabricated visit claims remote success.
Google's browser session may remain active. See the [bounded handoff](archive/auth-onboarding/CARD07-STANDARD-LOGOUT.md).

Unhandled web exceptions are reported through the existing sanitized
application-error logger with a correlation identifier, route/action context,
exception class, and broad category. Client responses are generic and do not
include exception messages, SQL, paths, credentials, tokens, cookies, CSRF
values, or request bodies. Expected domain validation messages remain
client-safe and are rendered only by their owning workflow.

## Versioned profile forms

`owner_profile.php` loads each profile and its version together. Text/contact,
upload and removal POSTs require the original `expected_profile_version`: canonical
decimal 0–2,147,483,647; 0 denotes an absent profile. Missing/invalid/stale versions
return safe HTML 409 with explicit reload/review, retaining submitted text, valid
visibility choices and the original token. Even pre-handler upload denials must
not replace that token with a fresh version. Same-value saves advance once.
Malformed patches return 422; successful mutations retain the existing 303 PRG.
Omitted fields preserve storage; supplied empty optional text clears to NULL.
Email and primary-phone visibility use independent `public_email_visible` and
`public_phone_visible` string 0/1 values. Each has its own exact `*_present=1`
marker (`public_email_present` / `public_phone_present`); its unchecked checkbox
means false only for that type. Omission of both value and marker preserves that
type. Malformed input rejects the whole patch. Retired `public_contact_visible`
or `public_contact_present` submissions return 409 with retained input and an
explicit reload/review message; they never infer independent choices. See the
[per-type contact handoff](archive/auth-onboarding/CARD06-PER-TYPE-CONTACT-VISIBILITY.md).
Photo selections may need
reselection. Uncertain COMMIT outcomes return honest 503/reload guidance and retain
potentially referenced media. Authorization/CSRF and existing-owner readiness policy
are unchanged. See [the scoped handoff](archive/auth-onboarding/23-PROFILE-PRESERVATION-CONCURRENCY-HANDOFF.md).

## Public Sign In presentation

`/signin.php` accepts `GET` and `HEAD`; `lang=en` selects English/LTR,
otherwise Arabic/RTL is used, including structured locale input. It has no
credential fields or signup action. Landing existing-account links reach this
presentation; protected Owner redirects retain the existing login contract.
Google and ordinary login remain available. Email/password and Microsoft use
native disabled buttons with visible explanations. Email is temporarily disabled
in this UI because no inspected existing password binding matched the configured
issuer; connection configuration alone does not establish account eligibility.
Public registration is unavailable; sign-in is for existing accounts only.

`/owner_login.php` remains GET-only. An absent `method` retains ordinary OIDC
login. The optional scalar values `google` and `email` select only the fixed
configured connection names in `includes/auth0_signin_methods.php`. Empty,
unknown or structured values receive HTML `400` before discovery or transaction
creation. Client-provided connection, prompt or redirect identifiers are not
forwarded. Switch/retry POST+CSRF flows retain `prompt=login` and the existing
callback/session/identity protections. See [the implementation record](SIGNIN-IMPLEMENTATION.md)
for configuration, recorded Google/browser evidence and remaining acceptance limits.
The email server allowlist and OIDC route are unchanged; the disabled UI does not
  remove that endpoint or claim successful email login.

## Inactive Sign Up and verification preview presentation

`/signup.php` accepts `GET`/`HEAD`, default Arabic/RTL or exact scalar
`lang=en` for English/LTR. Other/structured language input falls back to Arabic.
All registration providers are native disabled buttons with visible availability
explanations. There are no forms, credentials, authentication links or account
mutations; the existing-account link navigates to `/signin.php`.

Pending/success email screens are isolated fixtures under `tests/phase2/support/`,
without public route shims. The separate loopback preview chooses a fixed fixture;
client query claims do not alter it. Direct fixture requests are denied. Fixtures
start no session, send no email, create no account and grant no verified status or
access. Refresh/resend/onboarding controls are natively disabled. See the
[implementation and prerequisite record](archive/SIGNUP-VERIFICATION-IMPLEMENTATION.md).


Per-project publication: public HTML, project JSON and project media share current active-owner, published-Portfolio, matching-project and project.is_published=1 eligibility. A project flag alone grants no public access. Private project metadata/keys are not serialized; authorized Owner preview/media remain independent. The Projects page has a separate CSRF-protected publication POST; uncertain commit acknowledgement requires explicit reload/review, never automatic replay.
