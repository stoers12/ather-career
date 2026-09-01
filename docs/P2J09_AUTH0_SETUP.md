# P2J-09 Auth0 setup

Create a **non-production Auth0 tenant** and one **Regular Web Application**. Enable the Authorization Code grant and RS256 signing. Do not configure an Ather password flow, refresh-token platform, social-provider abstraction, or a second Auth0 application as an alternate issuer.

Add the exact production callback URL to Auth0's Allowed Callback URLs, for example:

```text
https://portfolio.example/owner_oidc_callback.php
```

Set these runtime-only values in the deployment environment:

```text
EXPECTED_OIDC_ISSUER=https://your-tenant.us.auth0.com/
OIDC_CLIENT_ID=...
OIDC_CLIENT_SECRET=...
OIDC_REDIRECT_URI=https://portfolio.example/owner_oidc_callback.php
```

`EXPECTED_OIDC_ISSUER` includes its trailing slash and must exactly equal the `iss` claim returned by Auth0. `OIDC_REDIRECT_URI` must exactly match the Auth0 application callback setting; it is never derived from the request host. The production guard rejects missing, placeholder, test, non-HTTPS, or unsafe redirect configuration.

For the real-provider gate, create a disposable Auth0 test user and exercise `/owner_login.php` through Universal Login. Record only the pass/fail result and safe event metadata—never authorization codes, tokens, client secrets, cookies, state, or PKCE verifiers.
