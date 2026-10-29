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

A fresh database is initialized from the shipped V1 baseline, which needs a preserved Owner subject exactly once. For this disposable trial, set the **synthetic local-only** value `PRESERVED_V1_OIDC_SUBJECT=local-development-only` in your local `.env`; there is deliberately no default. Compose bind-mounts the checkout over the image code, so install the locked PHP dependencies into it first (again whenever `composer.json` or `composer.lock` changes); classes under `src/` are loaded through Composer ([architecture](docs/ARCHITECTURE.md)). The web entrypoint waits for the database, applies the migrations and the one-time binding, then starts Apache:

```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:2 install
docker compose up -d --build --wait
```

Open <http://127.0.0.1:8088>. `/health.php` returns `OK` and `/ready.php` returns `READY`; the Landing and inactive account-entry presentations work without Auth0. Edits to the checkout are live: Compose bind-mounts it at `/var/www/app` and its `public/` tree at the document root `/var/www/public`.

Real Owner sign-in, account switching, logout through Auth0 and private Evidence Hub access require a configured Auth0 Regular Web Application, its dependencies and an authorized local identity binding. Before initializing a database intended for real sign-in, replace the synthetic bootstrap subject with the separately verified durable subject. See [Auth0 setup](docs/P2J09_AUTH0_SETUP.md) and the [authentication/onboarding record](docs/auth-onboarding/README.md); initialization alone does not enable registration or complete onboarding. No provider interaction is needed for the local trial or Phase 2 foundation.

Optionally enable the local Owner HTTPS bridge. Set `PUBLIC_BASE_URL=https://localhost:8443`, `SESSION_COOKIE_SECURE=true` and the exact HTTPS callback/allowed Auth0 URLs first; port 8443 must be unused. Trust Caddy's local CA in your development browser as appropriate:

```powershell
docker compose -f docker-compose.yml -f docker-compose.owner-https.yml up -d
```

The HTTPS origin is <https://localhost:8443>. Keep the same Compose file arguments for subsequent lifecycle commands. To stop the HTTP-only trial while retaining its local data, run `docker compose down`.

## Running tests

Use the image-based Phase 2 command and capability requirements in [AGENTS.md — Running tests](AGENTS.md#running-tests). Select the newest local `ather-career-local` tag as described there. If none exists, build one from this checkout with `docker build --target development -t ather-career-local:dev .` and use that tag. Full gates additionally need Node.js/npm and the pinned Chromium installation; `-InstallChromium` on the full runner installs the required browser when absent.

| Level | Command | Success marker |
| --- | --- | --- |
| Static analysis | `composer analyse` (in the PHP image) | `[OK] No errors` |
| Phase 2 foundation | Image-based command in [AGENTS.md](AGENTS.md#running-tests), mounting this checkout read-only | `Phase 2 test foundation passed.` |
| PHPUnit (`tests/Unit/` and the legacy adapter) | Image-based PHPUnit command in [AGENTS.md](AGENTS.md#running-tests) | PHPUnit `OK` |
| Gate registration | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1 -RegistrationChallenge` | `REGISTRATION_CHALLENGES_PASSED` |
| Full required gates | `pwsh -NoProfile -File scripts/run-evidence-hub-required-gates.ps1` | `REQUIRED_GATES_PASSED` |

The full runner creates and removes disposable databases, containers and browser state. Follow the [workflow](AGENTS.md#workflow-mandatory) before opening a PR.

For individual test commands, operational helpers and developer tools, see the [scripts layout](scripts/README.md).

## Repository layout

```text
handlers/      Thin request handlers: authentication/HTTP checks and calls into includes/
.github/       CI workflows
config/        Database and runtime configuration boundaries
contracts/     Versioned Evidence Hub schemas and taxonomies
data/          Checked-in profile catalogs, pinned sources and provenance
database/      Baseline SQL, ordered migrations, rollback companions and recovery tools
docker/        Apache/PHP/Caddy configuration and container entrypoints
docs/          Documentation index, maintained references and historical archive
includes/      Domain functions, repositories, rendering and shared HTTP/auth helpers
public/        The web document root: the single front controller index.php, health/ready probes and assets/{css,js,fonts,images}
resources/     Non-PHP resources, including private third-party license attribution
runtime/       Ignored generated runtime state (tracked placeholders only)
scripts/       Stable entry points; ci/, ops/, tools/ and approved archive/
tests/         Unit/ (PHPUnit), Legacy/ (registered cases), Integration/ (MySQL/HTTP), Browser/ (Playwright), Support/ (shared helpers and fixtures)
```

Every runtime uses one layout, built from one multi-stage [Dockerfile](Dockerfile): code in `/var/www/app` and the Apache document root `/var/www/public`, which holds only the repository `public/` tree. Nothing outside `public/` is web-reachable. The `development` target serves dev Compose and the required gates; the `production` target (the default) serves [docker-compose.production.yml](docker-compose.production.yml), CI and the Owner's local stack. Reference assets with `versionedAssetUrl('assets/css/<file>.css')`, which returns an absolute, content-versioned URL. See [docs/RUNTIME_TOPOLOGY.md](docs/RUNTIME_TOPOLOGY.md) and [AGENTS.md — Layout](AGENTS.md#layout).

## Documentation

Start with the [documentation map](docs/README.md), which distinguishes living references, architecture decisions/contracts and historical records.

## Contributing

Read [AGENTS.md](AGENTS.md) for the mandatory worktree-per-task workflow, project invariants, required gates and Draft PR process.

## Security

Never commit `.env`, credentials, tokens or key material. Deployment secrets live only in the deployment secret store; [.env.example](.env.example) describes configuration without supplying secrets. Keep local trial values disposable and outside Git.
