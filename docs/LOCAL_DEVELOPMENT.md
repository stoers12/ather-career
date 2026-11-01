# Local development without Auth0

The production Auth0 tenant accepts only production callback URLs. The free plan allows one tenant, so there is no development tenant (L-04, L-15). Local development signs in through a small **OIDC mock** instead. The application cannot tell it apart from Auth0, and nothing leaves your machine.

## Start

```bash
docker compose -f docker-compose.dev-auth.yml up -d --build
```

Then open **https://localhost:8444**.

- **The first visit** to `https://localhost:8444` and to `https://auth.localhost:9443` shows a certificate warning, because Caddy uses a local development CA. Accept it once for each address, or trust Caddy's root certificate in your system store.
- **Sign in:** "Sign in with Google" opens the **Development sign-in** page instead of Auth0. Choose a user:

  | Subject | What it is |
  |---|---|
  | `dev\|owner` | Owner of the sample portfolio (bound at first start through `PRESERVED_V1_OIDC_SUBJECT`) |
  | `google-oauth2\|dev-newcomer` | A new person, for registration |
  | `dev\|second` | Spare account; register it, then disable it with `scripts/ops/set-account-status.php` to test a disabled account |
  | anything `provider\|id` | Typed in the field below the list |

Stop with `docker compose -f docker-compose.dev-auth.yml down`. Add `-v` to also delete the dev database, storage and CA. The project name is `ather_dev`, so `-v` never reaches the resident `portfolio_course` stack.

## What runs

| Service | Role |
|---|---|
| `web` | The development image, with the checkout mounted. Apache on port 80 inside the container only. |
| `https` | Caddy with its internal CA: `https://localhost:8444` (the application), `https://auth.localhost:9443` (the issuer) and `https://auth.localhost` (the Management API, inside the container only). It publishes only the public root certificate, which PHP trusts. |
| `oidc-mock` | `scripts/dev/oidc-mock.php` on PHP's built-in server: discovery, sign-in page, token, JWKS, logout, and `DELETE /api/v2/users/{id}` for the account purge. |
| `setup` | One-shot: seals the owner-journey cutover, so the sample owner reaches the dashboard. |
| `db` | MySQL 8.4 with the V1 baseline. |

`web`, `https` and `oidc-mock` share one network namespace, so `auth.localhost` resolves to the same mock for the browser and for PHP.

## Useful commands

```bash
# Invitation codes, for registration (invite mode is on).
docker exec ather_dev-web-1 php scripts/ops/registration-invitations.php create --count 3

# The account purge, against the mock Management API (L-03).
docker exec ather_dev-web-1 php scripts/ops/purge-deleted-accounts.php run
docker exec ather_dev-oidc-mock-1 cat /tmp/ather-oidc-mock/deleted-users.log

# Too many sign-in attempts (5 per 5 minutes) in development.
docker exec ather_dev-web-1 sh -c 'find /var/www/app/runtime/rate-limit -type f ! -name .gitkeep -delete'

# End-to-end check of every sign-in path.
ATHERCAR_PLAYWRIGHT_MODULE=<path to playwright> node tests/Browser/run-dev-auth-browser-check.cjs
```

## Safety

- **The mock refuses to answer** unless `APP_ENV=development` and `ATHER_DEV_OIDC_MOCK=1`.
- **Its client id contains `-test-`,** and its issuer contains `localhost`. `auth0ProductionConfigurationFailures()` rejects both, so this configuration can never pass the production security check (`tests/Unit/DevOidcMockTest.php`).
- **All values in the compose file are development placeholders,** not secrets. Never reuse them anywhere else.
