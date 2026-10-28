# Ather Career

## What it is

Ather is a multi-tenant portfolio platform: Owners sign in to a private workspace to manage their work, and visitors see published portfolios. Evidence Hub is the Owner-private analytics page, separating claims from evidence through project documentation coverage, a technology evidence map, progress and at most three deterministic recommendations. It uses no AI and does not rank people.

## Stack

PHP 8.3, Apache, MySQL 8.4, Auth0 (OIDC) and Docker Compose. Pages are server-rendered with progressively enhanced CSS/JavaScript; Playwright runs the browser gates.

## Quick start

Install Docker with Compose v2 and PowerShell 7 (`pwsh`). Run commands from a fresh checkout with unused loopback ports 8088 and 3308. This procedure initializes a **disposable local database**, including its baseline ownership binding; use the deployment runbooks for an existing database.

```powershell
Copy-Item .env.example .env
```

Review every comment in [.env.example](.env.example). In your ignored local `.env`, replace the database password placeholders, keep the paired `DB_*` / `MYSQL_*` values consistent, set `PUBLIC_BASE_URL=http://127.0.0.1:8088` and keep `SESSION_COOKIE_SECURE=false`. For a provider-free trial, use a synthetic HTTPS root issuer on an `.invalid` domain, synthetic nonempty OIDC client values and a synthetic HTTPS callback URL. These values only satisfy local configuration validation; they cannot authenticate anyone. Keep the onboarding capability switches off.

Compose also requires `EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY`. For a disposable local trial, generate a temporary value in the current process without printing it:

```powershell
$env:EVIDENCE_HUB_OPAQUE_TARGET_HMAC_KEY = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
```

On a fresh database, bootstrap the shipped V1 baseline once before starting the long-running web service. The development Compose file does not forward `PRESERVED_V1_OIDC_SUBJECT`, so pass the **synthetic local-only** subject explicitly to the one-off web container. Its normal entrypoint waits for the database and applies the migrations:

```powershell
docker compose run --rm -e PRESERVED_V1_OIDC_SUBJECT=local-development-only web true
docker compose up -d
docker compose ps
```

Open <http://127.0.0.1:8088>. The Landing and inactive account-entry presentations work without Auth0; `/ready.php` should return `READY`. The base development configuration currently reports web **unhealthy** because `/health.php` is mapped only by the Owner HTTPS overlay; this does not prevent the HTTP Landing or readiness route from working. The overlay below supplies that mapping.

Real Owner sign-in, account switching, logout through Auth0 and private Evidence Hub access require a configured Auth0 Regular Web Application, its dependencies and an authorized local identity binding. Before initializing a database intended for real sign-in, replace the synthetic bootstrap subject with the separately verified durable subject. See [Auth0 setup](docs/P2J09_AUTH0_SETUP.md) and the [authentication/onboarding record](docs/auth-onboarding/README.md); initialization alone does not enable registration or complete onboarding. Install the locked PHP dependencies with `composer install` (Composer 2 with PHP 8.3, or its Docker image) before exercising real OIDC flows. No provider interaction is needed for the local trial or Phase 2 foundation.

Optionally enable the local Owner HTTPS bridge. Set `PUBLIC_BASE_URL=https://localhost:8443`, `SESSION_COOKIE_SECURE=true` and the exact HTTPS callback/allowed Auth0 URLs first; port 8443 must be unused. Trust Caddy's local CA in your development browser as appropriate:

```powershell
docker compose -f docker-compose.yml -f docker-compose.owner-https.yml up -d
```

The HTTPS origin is <https://localhost:8443>. Keep the same Compose file arguments for subsequent lifecycle commands. To stop the HTTP-only trial while retaining its local data, run `docker compose down`.

## Running tests

Use the image-based Phase 2 command and capability requirements in [AGENTS.md — Running tests](AGENTS.md#running-tests). Select the newest local `ather-career-local` tag as described there. If none exists, build one from this checkout with `docker build -t ather-career-local:eng01-dev .` and use that tag. Full gates additionally need Node.js/npm and the pinned Chromium installation; `-InstallChromium` on the full runner installs the required browser when absent.

| Level | Command | Success marker |
| --- | --- | --- |
| Static analysis | `composer analyse` (in the PHP image) | `[OK] No errors` |
| Phase 2 foundation | Image-based command in [AGENTS.md](AGENTS.md#running-tests), mounting this checkout read-only | `Phase 2 test foundation passed.` |
| Gate registration | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -RegistrationChallenge` | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1` | `REQUIRED_GATES_PASSED` |

The full runner creates and removes disposable databases, containers and browser state. Follow the [workflow](AGENTS.md#workflow-mandatory) before opening a PR.

For individual test commands, operational helpers and developer tools, see the [scripts layout](scripts/README.md).

## Repository layout

```text
*.php          Root application routes: authentication/HTTP checks and calls into includes/
*.css, *.js    Current page styles and progressive enhancements at the root
.github/       CI workflows
assets/        Local fonts, images and licenses
config/        Database and runtime configuration boundaries
contracts/     Versioned Evidence Hub schemas and taxonomies
data/          Checked-in profile catalogs, pinned sources and provenance
database/      Baseline SQL, ordered migrations, rollback companions and recovery tools
docker/        Apache/PHP/Caddy configuration and container entrypoints
docs/          Documentation index, maintained references and historical archive
includes/      Domain functions, repositories, rendering and shared HTTP/auth helpers
public/        Production front controllers and health handler; copied to /var/www/public
runtime/       Ignored generated runtime state (tracked placeholders only)
scripts/       Stable entry points; test/, ci/, ops/, tools/ and approved archive/
tests/         Phase 2 unit/static cases, fixtures and Playwright browser gates
uploads/       Legacy upload locations with tracked placeholders; managed media is private
```

Development serves the repository root. Production uses [docker-compose.production.yml](docker-compose.production.yml) and [Dockerfile.production](Dockerfile.production): `public/` front controllers are copied into the public document root and delegate to application routes in `/var/www/app`. See [AGENTS.md — Layout](AGENTS.md#layout) for contributor conventions.

## Documentation

Start with the [documentation map](docs/README.md), which distinguishes living references, architecture decisions/contracts and historical records.

## Contributing

Read [AGENTS.md](AGENTS.md) for the mandatory worktree-per-task workflow, project invariants, required gates and Draft PR process.

## Security

Never commit `.env`, credentials, tokens or key material. Deployment secrets live only in the deployment secret store; [.env.example](.env.example) describes configuration without supplying secrets. Keep local trial values disposable and outside Git.
