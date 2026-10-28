# Card 07 — Standard Ather and Auth0 logout

Bounded AUTH-ADR-004 implementation based on
`714b2fd5a8c56e994f136a5285ec897036ce7ca9`. Account switching, authentication,
registration, identity binding, session policy and journey routing are unchanged.

After POST/CSRF and strict input admission, `owner_logout.php` destroys the local
session before constructing an Auth0 redirect. It uses the documented
[Alternative Logout endpoint](https://auth0.com/docs/authenticate/login/logout/redirect-users-after-logout)
`/v2/logout`, with the existing trusted issuer/client configuration and exactly
two parameters: client_id and fixed PUBLIC_BASE_URL + `/signin.php`. This
supported mechanism does not require retaining a raw ID token or sid, adding
discovery metadata, or enabling RP-Initiated Logout endpoint discovery. No
federated parameter, provider logout, request-controlled URL or new configuration
switch is introduced. Sign In remains an ordinary presentation destination.

## Configuration prerequisites

The existing `auth0ConfigurationFromEnvironment()` and `publicBaseUrl()` validators
are reused. The existing issuer, client ID/secret, callback URI and public origin
must therefore be valid, although no secret/callback is sent to logout. The
fixed return URI is the normalized configured public origin plus `/signin.php`.
**That exact URI must be present in Allowed Logout URLs for the Auth0 application
selected by OIDC_CLIENT_ID** (application-level because client_id is supplied).
Use the actual authorized environment's origin and client, not a guessed URL,
wildcard, request Host or a previous environment's settings. A tenant-level entry
alone is not substituted. No Auth0 setting or effective secret was read/changed
by this slice; allowlisting and actual browser behavior remain unverified.

## Failure and evidence boundaries

Missing/invalid configuration after local retirement produces a private/no-store
503 explaining in Arabic and English that Ather is signed out but Auth0 logout
could not be started, with fixed Sign In navigation. Local retirement errors
remain errors and do not produce a local-success claim. Configuration work never
restores authority. No server-side Auth0 request is made: the browser must follow
303. A later network/tenant/allowlist rejection cannot be detected or relabeled
as success by Ather. The fixed return has no success flag or trusted callback
assertion. Auth0 SSO termination requires separate nonproduction browser evidence.
Google/provider sessions may remain, as described in
[Auth0's session-layer documentation](https://auth0.com/docs/authenticate/login/logout).

The focused real-HTTP test uses a loopback PHP test server, persisted synthetic
sessions and no-follow clients. It exercises the actual logout route, invalid
methods/CSRF/inputs, authority/readiness/pending-state retirement and old-cookie
replay, fixed destination and parameter allowlist, malicious Host/input resistance,
and missing/invalid configuration with honest partial results. It never contacts
Auth0. Existing session, Sign In and registration/journey regressions remain;
only their old local-only logout expectations are updated. CI includes the
focused test; integration is not a claim of a hosted run.

Actual command results, retained failures and packaging verification belong in
the external review handoff. No real authentication, push/PR/deployment,
migration/capture/gate activation, Auth0/Notion or canonical service/data change
is authorized. This slice does not close Card 07; upstream claim freshness and
the broader account-switch work remain separate.
