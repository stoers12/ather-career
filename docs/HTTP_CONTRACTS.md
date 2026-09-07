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
serialization. It contains only presentation fields and an optional
`image_url`, emitted only for an already-readable presentation asset and using
the scoped public Portfolio-media route. Raw managed-media paths, storage
keys, Owner identifiers, publication state, and operational metadata are never
public JSON fields. There are no active JSON
mutation or JSON request-body contracts. HTML form routes remain HTML
workflows and are not JSON APIs.

Unhandled web exceptions are reported through the existing sanitized
application-error logger with a correlation identifier, route/action context,
exception class, and broad category. Client responses are generic and do not
include exception messages, SQL, paths, credentials, tokens, cookies, CSRF
values, or request bodies. Expected domain validation messages remain
client-safe and are rendered only by their owning workflow.
