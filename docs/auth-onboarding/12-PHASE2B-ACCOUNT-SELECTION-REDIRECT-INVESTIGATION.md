# Phase 2B account-selection redirect investigation

Status: proposed isolated fix; live browser acceptance pending. This note does not close Phase 2B.

## Observed path

- In the owner's 2026-10-07 experiment, **Use another account** appeared to hang. After a manual refresh, the browser reached an Auth0 **Authorize App** page for the current account and returned to the same four-project Owner Dashboard. The supplied Network screenshots show `owner_switch_account.php` returning 302, followed by `owner.php` 303, `owner_login.php` 302, Auth0 consent, and an `owner_oidc_callback.php` 403 `access_denied`. The later `owner_auth_retry.php` POST returned 302, then a 403 **Invalid request** page. A 302 alone does not establish that the browser reached account selection.
- In a separate local browser reproduction on 2026-10-07, a prompt Auth0 consent acceptance reached the Momen four-project Dashboard. Clicking **Use another account** once changed the button to **Opening account selection...** and left the browser on the Dashboard. The server recorded a POST 302 at the click time; no account-selection screen or successful callback was observed. A prior consent attempt expired after a long delay and logged `transaction_expired`; that is a separate expected failure.
- The switch handler validates method, exact input, CSRF, authenticated User, rate limit and OIDC discovery, retires the local authenticated session, and issues a 302 to the validated Auth0 authorization endpoint with `prompt=select_account`. The normal login path reached Auth0 after the manual refresh, which explains why consent for the current provider account appeared without proving that the switch navigation completed.

## Cause and change

The deployed Owner HTTPS response has `Content-Security-Policy: ... form-action 'self'`. Edge/Chromium can apply `form-action` to a redirect after a same-origin form POST. This matches the observed accepted POST 302 with no ensuing account-selection navigation, and the established CSP redirect behavior. The browser console violation for this exact attempt was not captured, so the browser-side cause remains strongly supported rather than directly observed. The Auth0 account chooser, provider session behavior, and Callback after a completed switch have not yet been verified.

The isolated change adds only the configured `EXPECTED_OIDC_ISSUER` to `form-action` at Apache and local Owner HTTPS Caddy. Caddy receives that setting from Compose. The other CSP directives remain in place. The switch POST, CSRF, state, nonce, PKCE, authorization endpoint validation, rate limit, callback binding, and data paths are unchanged. The exact issuer is configured by the deployment environment; no wildcard or arbitrary redirect destination is introduced.

## Verification and release boundary

The Phase 2 test foundation passes in a disposable application image against this isolated worktree. Apache syntax and Caddy validation pass with a dummy issuer; a disposable Apache response and adapted Caddy configuration each contain the expected issuer in `form-action`. The regression contract checks both edge configurations and the Caddy environment binding. These checks verify configuration and existing application contracts; they do not substitute for the real browser account-selection experiment.

Before deploying, review the isolated commit and run required CI. Deployment needs explicit owner approval. After deployment, observe a single genuine **Use another account** attempt without manually refreshing: confirm the switch POST, browser navigation to Auth0 with account-selection prompt, provider selection, successful Callback, and the intended account's projects and Evidence. Do not log query values, credentials, cookies, tokens, state, nonce, or authorization codes. If the chooser still stalls, capture the browser console CSP message and sanitized redirect chain before changing code again.

Rollback for a code-only failure is to stop the same web and Owner HTTPS containers, restore the live checkout to the recorded predeployment SHA under the approved maintenance procedure, restart those same containers, and recheck health and protected-data baselines. Any data or media discrepancy requires stopping the application and preserving evidence; do not restore data automatically.
