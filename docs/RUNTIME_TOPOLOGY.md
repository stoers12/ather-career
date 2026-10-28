# Runtime topology and public-assets design

Status: ENG-04 Phase A design, **approved with the amendments in section 9** and **implemented by ENG-04 Phase B** (single multi-stage `Dockerfile`, shared `docker/apache/vhost.conf`, assets under `public/assets/`, public-boundary gate). Section 9 is binding where it differs from sections 1–8; sections 1 and 4 describe the pre-Phase-B state. Verified on 2026-10-28 against `origin/main` at `3817dbf`. This document describes Phase B; it implements no runtime changes and authorizes no deployment. Phase A changes only this file and the documentation index.

## 1. Current state

### Evidence and boundaries

Repository evidence: `Dockerfile:1–31`, `Dockerfile.production:1–49`, the three root Compose files, `docker/apache/*.conf`, `docker/production-entrypoint.sh`, `public/*.php`, `includes/presentation.php`, and `scripts/run-evidence-hub-required-gates.ps1`. Line references throughout this note refer to **3817dbf**, before migration.

Local evidence was read from `docker inspect`, `docker history --no-trunc`, `apache2ctl -S`, and specific configuration files in `portfolio_course-web-1` and the release metadata named by its Compose labels. No live HTTP requests, entrypoints, migrations, container changes, volume changes, or image changes were made. Release folders were read only. The original checkout has unrelated changes and lacks the three requested guidance files on its current branch; those files were read in order in the clean worktree before re-reading the handoff and card.

Both current repo images were built and inspected using only Compose project `eng04-design-inspection`, an external temporary Compose file, unique inspection image tags, random loopback ports, no database, and no persistent volumes. Apache was started directly in those disposable containers, bypassing the normal mutating entrypoint. Consequently their readiness **503** establishes route resolution, not a database-readiness defect. Phase A runs only the Phase 2 suite; full gates and production-security execution belong to Phase B.

Phase A verification: `docker compose -p eng04-design-inspection -f <external-temp-compose.yml> run --rm --no-deps phase2`, using `ather-career-local:79ee34af5355` with PHP entrypoint, this worktree mounted read-only at `/var/www/html`, `APP_ENV=test`, `ATHERCAR_TEST_MODE=1`: **exit 0, `Phase 2 test foundation passed.`** Disposable inspection containers/network and the two inspection image tags were removed afterward. The protected local image tags and live services remained unchanged.

### Four runtimes and request resolution

| Runtime | Build, code, dependencies | `/` and assets | `/health.php` and `/ready.php` |
| --- | --- | --- | --- |
| 1. Base dev Compose | `Dockerfile`; repo bind `.:/var/www/html`; `/var/www/app` is a symlink to `/var/www/html`. `.dockerignore` excludes vendor, and no Composer stage exists; bind-mounted host vendor is available only after install. | Dev vhost DocumentRoot `/var/www/html`; root `index.php`; root CSS/JS and `assets/` are directly served. | No root health file or base alias: **404**, so the configured exact `OK\n` probe fails once Apache starts. Root `ready.php` executes directly; `READY\n` only with dependencies ready, otherwise 503. |
| 2. Required gates | Runner builds both Dockerfiles (`425–428`), verifies distinct source layouts (`197–214`), runs Phase 2 with read-only repo mount at `/var/www/html`, then dev Apache/MySQL/browser gates; production image gets a CLI security check. | Dev HTTP/browser path currently uses repo-root vhost, production layout has `/var/www/app` plus `/var/www/public`. It is **not four independent image types**, but a fourth consumer of the two layouts. | Dev gates wait on `/ready.php` (`337–342`), not liveness. Production liveness exists in public; the required runner does not currently prove HTTP parity on both images. |
| 3. Production | `Dockerfile.production`: Composer lock install `--no-dev`, PHP 8.3 Apache; code/vendor at `/var/www/app{,/vendor}`, public copied to `/var/www/public`. | Production vhost `/var/www/public`; public `index.php` requires `/var/www/app/index.php`; 17 named root CSS/JS copied manually, fonts and two images selected, plus root PNG. | `public/health.php` loads `/var/www/app/includes/observability.php`, returns `OK\n`; public ready shim requires `/var/www/app/ready.php`, whose dependency check returns `READY\n` or 503. |
| 4. Owner's running local site | Image tag `ather-career-local:79ee34af5355`, image ID `b14da1ec566f…`; out-of-repo maintenance Dockerfile inherits `ather-career-local:a8db1aee65d3` and copies source into `/var/www/html`. Vendor inherited at `/var/www/html/vendor`; `/var/www/app` remains a symlink. No source bind mount. | Installed dev vhost `/var/www/html`, root `index.php`, root CSS/JS, `assets/`. Caddy reverse-proxies `web:80` on `https://localhost:8443`; HTTP binds loopback 8088. | Mounted `owner-local-health.conf` aliases health to `/var/www/html/public/health.php` and ready to `/var/www/html/ready.php`. Both web and Caddy were healthy at inspection. |

Production rewrites public portfolio/contact/media/JSON paths to `p.php`, `p_contact.php`, `p_media.php`, `p_projects.php`; dev rewrites them to root `public_*.php`. Owner Evidence Hub/project-evidence rewrites target same-named public shims in production. The target must use the production rewrite set in every runtime; changing only DocumentRoot would leave public portfolio routes broken. `public/health.php` is an actual handler, not a normal route shim.

### Runtime 4 provenance, startup and mounts

Compose labels name the active overlay `ather-career-releases/20261028T003924Z-pr55-merge-79ee34af5355/canonical-release.yml`, applied after the original base and HTTPS Compose files. Its build context is that release's `source/`, with sibling `Dockerfile.local-maintenance`. The latter is exactly `FROM ather-career-local:a8db1aee65d3`, `COPY . /var/www/html/`, revision ARG/LABEL. Image history confirms that inherited base includes the dev image's config COPYs, symlink and entrypoint plus `COPY /var/www/app/vendor /var/www/html/vendor`. Its image creation is `2026-10-25T17:05:19Z`; revision `a8db1aee65d3f4ec142fdb0c9b38c8bc1ad273e1` is merge PR #44, the Card 11 workspace change. History confirms the vendor copy boundary; the exact historical intermediate-stage recipe/Composer flags are not recoverable from that history and were not treated as a reproducible recipe.

The **effective live entrypoint differs from the image default**: the overlay runs `/bin/sh /usr/local/bin/validate-oidc-issuer.sh; exec docker-php-entrypoint apache2-foreground`. It deliberately skips storage repair, production-security execution, bootstrap and migrations in `portfolio-runtime-entrypoint`. Preserving this maintenance behavior is a cutover requirement, even when the image is rebuilt from the repo.

Installed vhost, access policy, security headers, cache policy, safe-access-log policy, runtime entrypoint and issuer validator all equal main after LF normalization. The live vhost contains CRLF (as does this Windows worktree); shell entrypoint is LF. These installed files are outside `/var/www/html`, so later maintenance `COPY .` layers cannot update them. PHP upload settings are generated in the inherited dev image; repo `docker/php/production.ini` is not installed by that dev recipe.

| Service | Current mount source / persistent identity | Container destination |
| --- | --- | --- |
| web | `portfolio_course_portfolio_private_storage` (named volume) | `/var/www/private-storage` |
| web | `20261022T141941Z-b9d27994d5c2/source/docker/apache/owner-local-health.conf` under releases, read-only | `/etc/apache2/conf-enabled/owner-local-health.conf` |
| web | Original checkout `runtime/rate-limit` bind, writable | `/var/www/app/runtime/rate-limit` (resolves through app symlink today) |
| owner_https | `20261022T141941Z-b9d27994d5c2/source/docker/Caddyfile.owner-local` and `validate-oidc-issuer.sh`, read-only | `/etc/caddy/Caddyfile`, `/etc/caddy/validate-oidc-issuer.sh` |
| owner_https | `portfolio_course_caddy_data` (named volume) | `/data` |
| db | Existing database service/volume; not involved in this layout change | Unchanged; no schema work proposed |

### Handoff fact audit and corrections

- **17, not 19, production CSS/JS copies** at `Dockerfile.production:41`. The repo has 19 root CSS/JS files; `onboarding.js` and `recovery.css` are omitted. Disposable production inspection counted 17 and returned 404 for both omitted files, 200 for `/`, `/landing.css`, the root PNG and `/health.php`. Dev inspection returned 200 for `/`, `/landing.css` and a font, and 404 for `/health.php`; its health became unhealthy.
- **26 executable helper call sites**, comprising 22 application calls and four preview-fixture calls. A simple line grep reports 29 matching lines because it includes the definition and two static-test lines containing four quoted calls. The helper really does stat project-root-relative files and falls back to `v=1`; the inventory below distinguishes callers from assertions.
- **Six CSS files / twelve font URLs** confirmed at lines 2 and 3 of `email_verification.css`, `landing.css`, `onboarding.css`, `owner_workspace.css`, `recovery.css`, `signin.css`. All use relative `assets/fonts/…`.
- **Readiness alias clarification:** runtime 4's alias targets root `ready.php`, not public `ready.php`. The handoff's ellipsis hides this distinction. The maintenance entrypoint override and rate-limit bind are additional relevant facts absent from its runtime table.
- Cache policy is extension-based and sets `public, max-age=604800` for CSS/JS/images; **TTF and TXT are not in that extension list**. CSP is origin-based (`'self'`), not asset-path-based. No path rewrite is required for either policy just to move assets.
- Access-policy deny patterns match the handoff. Directory indexing is disabled, which denies a directory listing but **does not protect individual internal files**. Disposable dev returned 200 for `/docs/README.md`, `/scripts/README.md`, `/composer.json`, `/contracts/evidence-hub-contract-v1.schema.json`; vendor is absent in that image, but host installation would expose it under the same grant. Tests are also inside the granted root; no live test/script endpoint was invoked.
- Base dev Compose does not forward `PRESERVED_V1_OIDC_SUBJECT`; `.env.example:39` declares it and README documents the manual one-off. The CLI bootstrap validates explicit nonempty subject length and delegates binding; there is **no automatic synthetic-subject safety classifier**. Safety currently rests on using a disposable database and the documented synthetic-only trial. Do not mistake a bootstrap subject for authentication.
- `docker/production-entrypoint.sh:28` invokes `php /var/www/app/scripts/check-production-security.php` **only when** `PORTFOLIO_PRODUCTION_SECURITY_CHECK=1`; production image sets it. The owner's effective maintenance entrypoint bypasses it entirely.

## 2. One target layout for all four runtimes

Adopt the existing production filesystem boundary everywhere: real application directory `/var/www/app`, separate DocumentRoot `/var/www/public`, public front controllers plus `assets/{css,js,fonts,images}` only. Keep root route PHP files and `includes/`, config, contracts, data, scripts, tests and vendor private inside app. Do not retain the `/var/www/app -> /var/www/html` symlink. Private media remains outside both trees.

| Consumer | How `/var/www/app` is supplied | How `/var/www/public` is supplied | Vhost / dependencies |
| --- | --- | --- | --- |
| Dev Compose | Repo `.:/var/www/app` bind, so route/include edits are live | `./public:/var/www/public:ro` bind of the same checkout, so asset/front-controller edits are live | Shared production-style vhost/rewrite set. Locked Composer install in host repo `vendor/` is visible through app bind; image vendor is hidden by bind, so install is an explicit dev prerequisite. |
| Required gates | Repo-built dev/local and production images; read-only repo bind at `/var/www/app` for Phase 2 where needed | Image public tree for packaged HTTP checks; paired public bind only for tests intentionally exercising live source | Same vhost and handler paths as shipping images. Exercise both packaged images over HTTP; verify mounts separately from immutable images. |
| Production | Image COPY of clean repo and locked vendor | Copy entire repo `public/` tree to `/var/www/public` | Same vhost/rewrite set, hardened PHP, production validation/startup. |
| Local Owner release | Repo-built `local` image target, no source bind | Entire public tree from that release image | Same vhost/rewrite set and hardened PHP; explicit maintenance startup with no implicit migrations. |

For `/var/www/public/owner.php`, `dirname(__DIR__)` is `/var/www`; `'/app/owner.php'` resolves to `/var/www/app/owner.php` in every row. This works because the dev public mount is at the **sibling** path, not merely at `/var/www/app/public`. Keep existing public shims unchanged where possible. Public health's existing app-discovery branch resolves to `/var/www/app/includes/observability.php`. For host preview fixtures, reproduce the sibling `app`/`public` topology or invoke a deliberately bounded preview router; do not create recursive app links.

Keep `DirectoryIndex index.php`, `AllowOverride None`, request limits, security headers, safe logging and exact canonical rewrites. Make development use the same vhost file as production rather than maintaining two divergent rule lists. Deny filesystem `/var/www/app` in Apache as defense in depth; the granted directory is `/var/www/public` only, with no Alias to app. A `<LocationMatch>` denying the URL `/public` does not block filesystem `/var/www/public/assets/…` at URL `/assets/…`.

Use `WORKDIR /var/www/app` for commands. Runtime state binds/volumes belong at `/var/www/app/runtime/rate-limit` and approved private storage roots; dev can overlay a disposable state volume at the rate-limit path instead of dirtying the repo. No vendor, runtime, uploads or internal-directory symlink may be created in public.

## 3. Reproducible repo-built local image

Phase B should consolidate the build graph in the repo `Dockerfile`, with named `dependencies`, `runtime-base`, `development`, `local` and `production` targets. Retire duplicate instructions in `Dockerfile.production` by updating all build consumers to select `--target production` in the same change. Do not attempt an invalid cross-Dockerfile local-stage import. Until that change lands atomically, the current Dockerfiles remain authoritative.

- `dependencies`: pinned Composer image, `composer.json` + `composer.lock`, install `--no-dev --prefer-dist --no-interaction --optimize-autoloader`. A separate tooling stage may install locked dev dependencies when analysis needs them; never copy them into shipping local/production targets.
- `runtime-base`: pin the PHP 8.3 Apache base digest, preserve required extensions (`pdo_mysql`, `gd`, `intl`, `mbstring`, exif), libvips and runtime packages. Copy Apache config, issuer validator, shell entrypoints and PHP ini from **this repo revision** into their effective `/etc` and `/usr/local` destinations; enable required modules. Share hardened PHP settings for local and production; define any dev-only overrides explicitly.
- Package clean source at `/var/www/app`, vendor from the dependency stage, and **all** `public/` contents at `/var/www/public`. A `cp -a /var/www/app/public/. /var/www/public/` preserves metadata if used; no named CSS/JS/image lists. Development target has the same layout before binds; the dev bind requires its own locked vendor installation as explained above.
- `local`: immutable revision label and image tag, same packaged tree as production, with a repo-owned maintenance entrypoint that validates issuer/configuration/security, checks existing state permissions without recursively repairing reused storage, and starts Apache. It must not run migrations, backfill, cutover capture, bootstrap or owner-binding steps. Fail closed on unavailable readiness; any required state repair is a separately approved operational step.
- `production`: preserve the current explicit production-security check and bootstrap/migration order; this card changes packaging, not database policy. Keep the existing `scripts/check-production-security.php` path. Split shared read-only validation from mutating startup carefully; never make a default runtime-mode flag silently opt a live database into migrations.

Reproducibility means a clean checkout + lockfile + recorded base-image digests + recorded package inputs can rebuild the release without any `ather-career-local:*` ancestor, prior release folder, host vendor or build cache. Mutable apt repository state still prevents a byte-for-byte guarantee unless package versions/snapshot sources are recorded/pinned; report that limit and keep the resulting image digest as the deployment identity. Prove a cache-free isolated rebuild and record source manifest, base digests, dependency lock hash, effective config hashes, PHP capabilities and resulting image digest. No new runtime dependency is needed.

The release overlay must select repo `Dockerfile`, target `local`, clean release source context and immutable new image reference; remove the ad hoc `FROM a8db1aee65d3` recipe and shell entrypoint override in favor of the image's explicit maintenance entrypoint. Set revision metadata and preserve current default-off capability flags, HMAC/config inputs, ports, network identities and state paths. Render the merged overlay privately and inspect only allowlisted non-secret fields before use. It must override base dev **source/public binds away** for the immutable release.

Mount transition (requires Owner approval in Phase B): remove the web `owner-local-health.conf` mount because its `/var/www/html` aliases are invalid in the target. Equivalent `/health.php` and `/ready.php` handlers are now naturally in DocumentRoot; prove status, exact bodies and no-store through HTTP and HTTPS. Preserve named private-storage and Caddy data volumes and the rate-limit bind at its existing logical destination. Replace the two Caddy configuration binds with read-only files from the **new immutable release's** repo `docker/` directory, retaining the same Caddy image digest, issuer validation, TLS identity, headers and `web:80` upstream. Do not edit the old release directories; retain their paths and the old alias mount in the rollback descriptor. Recreate the proxy only if its mounted configuration changes, after the new backend is healthy.

Line endings: `.gitattributes` currently forces LF only for `*.sh` and the v1 contract. Add narrowly scoped LF rules for Dockerfiles, `docker/**/*.conf`, `docker/**/*.ini`, `docker/Caddyfile*` and root Compose YAML, preserving frozen contract bytes. Keep `*.sh text eol=lf`, executable permissions for entrypoints, and an image-build check that installed executable scripts contain no CR. Normalize only the relevant tracked runtime files with a reviewed diff in Phase B; do not broadly renormalize the repo. Verify effective installed configs against the same revision after normalization. Avoid PowerShell-generated default encodings/CRLF for executable image inputs.

## 4. Asset migration and reference inventory

### Move map and URL contract

Use `git mv`; retain bytes except the twelve CSS font paths. No bundler, minifier or visual redesign.

| Old repo path | Target repo path |
| --- | --- |
| `ChatGPT-Image-Apr-14-2025-065043-PM.png` | `public/assets/images/legacy-professional-portfolio.png` |
| `admin.css` | `public/assets/css/admin.css` |
| `admin.js` | `public/assets/js/admin.js` |
| `email_verification.css` | `public/assets/css/email_verification.css` |
| `evidence_hub.css` | `public/assets/css/evidence_hub.css` |
| `evidence_hub.js` | `public/assets/js/evidence_hub.js` |
| `landing.css` | `public/assets/css/landing.css` |
| `landing.js` | `public/assets/js/landing.js` |
| `onboarding.css` | `public/assets/css/onboarding.css` |
| `onboarding.js` | `public/assets/js/onboarding.js` |
| `onboarding_editor.js` | `public/assets/js/onboarding_editor.js` |
| `owner_dashboard.css` | `public/assets/css/owner_dashboard.css` |
| `owner_theme.js` | `public/assets/js/owner_theme.js` |
| `owner_workspace.css` | `public/assets/css/owner_workspace.css` |
| `portfolio.css` | `public/assets/css/portfolio.css` |
| `portfolio.js` | `public/assets/js/portfolio.js` |
| `recovery.css` | `public/assets/css/recovery.css` |
| `signin.css` | `public/assets/css/signin.css` |
| `signup.css` | `public/assets/css/signup.css` |
| `style.css` | `public/assets/css/style.css` |
| `assets/fonts/OFL.txt` | `public/assets/fonts/OFL.txt` |
| `assets/fonts/noto-sans-arabic-400.ttf` | `public/assets/fonts/noto-sans-arabic-400.ttf` |
| `assets/fonts/noto-sans-arabic-500.ttf` | `public/assets/fonts/noto-sans-arabic-500.ttf` |
| `assets/images/ather-navbar-logo.png` | `public/assets/images/ather-navbar-logo.png` |
| `assets/images/dashboard-projects.svg` | `public/assets/images/dashboard-projects.svg` |
| `assets/licenses/LUCIDE.txt` | `assets/licenses/LUCIDE.txt` (keep private attribution) |

Keep `assets/licenses/LUCIDE.txt` as private attribution for inlined icons, and keep its existing code comment valid. Move the font OFL notice with the fonts. Images/fonts already use their target **URL** prefix; only their repo filesystem locations change. Rename the generated PNG to `public/assets/images/legacy-professional-portfolio.png`: visual inspection shows a gold briefcase and “Professional Portfolio” lettering, distinct from the approved compact navbar logo. No application template currently references the PNG, but Docker publishes it; retain a redirect for its old URL.

New `versionedAssetUrl()` contract: accept a project-public-relative canonical path such as `assets/css/landing.css` or `assets/js/landing.js`, without a leading slash, query or fragment; permit only known asset subdirectories and reject traversal, backslashes and external URLs. Read `dirname(__DIR__) . '/public/' . $relativePath` (the checked-in public tree), never the project root or `$_SERVER['DOCUMENT_ROOT']`. Return an **absolute origin-relative** URL `/assets/css/landing.css?v=<first-16-hex-SHA256-of-file-bytes>`. Use content versioning so clean rebuilds and rollbacks do not depend on mtimes. Missing files must fail loudly in tests/build verification; do not silently return `v=1`. All callers must pass the new path and remove prepended `/` or `'/' .` concatenation, preventing `//assets/…`. Public image URLs that stay unversioned may remain literal; their unchanged URL still needs new filesystem paths in tests.

Update all six CSS files at lines 2 and 3 from `url("assets/fonts/<file>")` to `url("../fonts/<file>")`. From `/assets/css/<name>.css` this resolves to `/assets/fonts/<file>`; the query token does not change that base. There are no other CSS `url()` references in the tracked CSS. Preserve font families, weights and font-display.

Old URL policy: explicit **308 permanent redirects** for each of the 19 former root CSS/JS URLs and the generated PNG URL, preserving any query string, to the mapped canonical URL. Set `Cache-Control: no-store` on these redirect responses so the rollback window does not create persistent browser redirect entries. Existing seven-day asset caches and open tabs justify compatibility even with versioned new HTML. Keep these narrow redirects for at least two releases and seven days, with removal reviewed by the Owner; avoid a generic extension rewrite that could publish internals. Do not redirect `/assets/fonts/…` or `/assets/images/…`, whose URLs remain the same. New HTML and gates must request canonical URLs directly with **zero redirects**, while a dedicated compatibility test verifies old status, Location, no-store, query preservation and byte-identical final response. Rollback restores the matching old image/vhost and its original asset names. Cached new HTML cannot be assumed to survive a full old-image rollback: force reload and clear browser asset cache if necessary during the local rollback smoke.

### Every existing reference at the verified base

The following inventory scans **all tracked text**, including dotfiles, CI, docs, PHP, CJS/MJS and fixtures, for every manifest filename, asset-directory path, helper call/assertion and root CSS/JS wildcard. Binary image/font payloads and unrelated technology names such as `React.js` are excluded. Line numbers are base-source coordinates, not this document. Re-run `git grep -n` for each old basename plus `versionedAssetUrl`, `assets/`, root CSS/JS globs and `/var/www/html` in Phase B. The inventory includes unchanged references and historical mentions so that no grep result is silently dropped.

Actions: **U** = update caller URL/source path or packaging assertion; **F** = relocate fixture/read/intercept source and canonical URL (including whole-tree copying); **C** = fix relative CSS font URL; **P** = replace per-file build/identity checks with whole public-tree checks; **D** = update maintained path documentation/conventions; **K** = keep valid unchanged public URL, attribution/comment or dated historical statement. In a mixed row, the explanation below controls which parts change. Static tests must preserve behavioral expectations while updating their paths; replace obsolete named-COPY assertions with actual public-tree packaging checks.

Inventory: 205 matching source lines in 91 files.

| Base file:line | Referenced assets / selector | Action |
| --- | --- | --- |
| `.github/workflows/ci.yml:43` | `*.js` | K |
| `.github/workflows/ci.yml:758` | `signin.css` | U |
| `.github/workflows/ci.yml:759` | `onboarding.css` | U |
| `.github/workflows/ci.yml:760` | `onboarding_editor.js` | U |
| `.github/workflows/ci.yml:763` | `signup.css` | U |
| `AGENTS.md:94` | `admin.css` | D |
| `Dockerfile.production:39` | `assets/` | P |
| `Dockerfile.production:41` | `admin.css`, `admin.js`, `email_verification.css`, `evidence_hub.css`, `evidence_hub.js`, `landing.css`, `landing.js`, `onboarding.css`, `onboarding_editor.js`, `owner_dashboard.css`, `owner_theme.js`, `owner_workspace.css`, `portfolio.css`, `portfolio.js`, `signin.css`, `signup.css`, `style.css` | P |
| `Dockerfile.production:42` | `assets/` | P |
| `Dockerfile.production:43` | `assets/`, `assets/images/ather-navbar-logo.png`, `assets/images/dashboard-projects.svg` | P |
| `Dockerfile.production:44` | `ChatGPT-Image-Apr-14-2025-065043-PM.png` | P |
| `README.md:66` | `*.css`, `*.js` | D |
| `README.md:68` | `assets/` | D |
| `docs/LANDING-IMPLEMENTATION.md:14` | `landing.css` | K |
| `docs/LANDING-IMPLEMENTATION.md:15` | `landing.js` | K |
| `docs/LANDING-IMPLEMENTATION.md:18` | `assets/`, `assets/licenses/LUCIDE.txt` | K |
| `docs/LANDING-IMPLEMENTATION.md:65` | `landing.css`, `landing.js` | K |
| `docs/README.md:47` | `landing.css` | D |
| `docs/README.md:50` | `signin.css` | D |
| `docs/SIGNIN-IMPLEMENTATION.md:23` | `landing.js` | K |
| `docs/SIGNIN-IMPLEMENTATION.md:62` | `signin.css` | K |
| `docs/SIGNIN-IMPLEMENTATION.md:67` | `signin.css` | K |
| `docs/SIGNIN-IMPLEMENTATION.md:75` | `signin.css` | K |
| `docs/archive/SIGNUP-VERIFICATION-IMPLEMENTATION.md:35` | `landing.js`, `signin.css` | K |
| `docs/archive/SIGNUP-VERIFICATION-IMPLEMENTATION.md:51` | `signup.css` | K |
| `docs/archive/SIGNUP-VERIFICATION-IMPLEMENTATION.md:53` | `email_verification.css` | K |
| `docs/archive/auth-onboarding/21-ONBOARDING-DRAFT-EDITOR-HANDOFF.md:33` | `onboarding_editor.js` | K |
| `docs/archive/auth-onboarding/21-ONBOARDING-DRAFT-EDITOR-HANDOFF.md:34` | `onboarding.js` | K |
| `docs/archive/auth-onboarding/27-FIRST-COMPLETION-CORE-HANDOFF.md:179` | `assets/` | K |
| `docs/archive/auth-onboarding/28-ATTESTATION-HTTP-HANDOFF.md:190` | `assets/` | K |
| `docs/archive/auth-onboarding/29-ATTESTATION-EDITOR-UI-HANDOFF.md:143` | `assets/` | K |
| `docs/archive/auth-onboarding/34-AUTHENTICATED-ENTRY-D1-HANDOFF.md:49` | `landing.js`, `signin.css` | K |
| `docs/auth-onboarding/01-CURRENT-STATE.md:60` | `admin.css`, `admin.js`, `owner_theme.js`, `portfolio.css` | D |
| `docs/auth-onboarding/06-IMPLEMENTATION-PLAN.md:95` | `admin.css`, `admin.js`, `owner_theme.js` | D |
| `docs/auth-onboarding/06-IMPLEMENTATION-PLAN.md:162` | `admin.css`, `admin.js`, `evidence_hub.css`, `owner_theme.js`, `portfolio.css`, `portfolio.js` | D |
| `docs/auth-onboarding/22-ONBOARDING-COMPLETION-CONTRACT.md:292` | `onboarding_editor.js` | D |
| `email_verification.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `email_verification.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `evidence_hub.css:456` | `admin.css` | K |
| `includes/authenticated_entry.php:65` | `landing.js`, `versionedAssetUrl` | U |
| `includes/authenticated_entry.php:66` | `signin.css`, `versionedAssetUrl` | U |
| `includes/landing_icons.php:5` | `assets/licenses/LUCIDE.txt` | K |
| `includes/onboarding_presentation.php:39` | `landing.js`, `versionedAssetUrl` | U |
| `includes/onboarding_presentation.php:40` | `onboarding.js`, `onboarding_editor.js`, `versionedAssetUrl` | U |
| `includes/onboarding_presentation.php:41` | `onboarding.css`, `versionedAssetUrl` | U |
| `includes/owner_auth_recovery.php:29` | `style.css` | U |
| `includes/owner_auth_recovery.php:60` | `style.css` | U |
| `includes/owner_flow.php:95` | `admin.js`, `style.css` | U |
| `includes/owner_layout.php:42` | `assets/images/ather-navbar-logo.png` | K |
| `includes/owner_layout.php:44` | `style.css`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:45` | `admin.css`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:46` | `evidence_hub.css`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:48` | `owner_workspace.css`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:49` | `owner_dashboard.css`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:51` | `admin.js`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:53` | `evidence_hub.js`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:58` | `owner_theme.js`, `versionedAssetUrl` | U |
| `includes/owner_layout.php:100` | `assets/images/ather-navbar-logo.png` | K |
| `includes/owner_layout.php:156` | `assets/images/ather-navbar-logo.png` | K |
| `includes/owner_project_evidence_presentation.php:38` | `evidence_hub.js` | K |
| `includes/portfolio_presentation.php:328` | `portfolio.css`, `versionedAssetUrl` | U |
| `includes/portfolio_presentation.php:329` | `portfolio.js`, `versionedAssetUrl` | U |
| `includes/portfolio_presentation.php:339` | `assets/images/ather-navbar-logo.png` | K |
| `includes/portfolio_presentation.php:350` | `assets/images/ather-navbar-logo.png` | K |
| `includes/portfolio_presentation.php:568` | `assets/images/ather-navbar-logo.png` | K |
| `includes/presentation.php:3` | `versionedAssetUrl` | U |
| `index.php:25` | `landing.js`, `versionedAssetUrl` | U |
| `index.php:26` | `landing.css`, `versionedAssetUrl` | U |
| `landing.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `landing.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `onboarding.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `onboarding.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `onboarding_editor.js:534` | `landing.js` | K |
| `owner.php:66` | `assets/images/dashboard-projects.svg` | K |
| `owner_dashboard.css:1` | `owner_workspace.css` | K |
| `owner_workspace.css:1` | `admin.css` | K |
| `owner_workspace.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `owner_workspace.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `phpstan.neon.dist:21` | `assets/` | D |
| `recovery.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `recovery.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `scripts/ops/run-production-smoke.ps1:659` | `portfolio.css` | U |
| `scripts/ops/run-production-smoke.ps1:660` | `portfolio.js` | U |
| `scripts/ops/run-production-smoke.ps1:705` | `assets/` | K |
| `scripts/run-evidence-hub-required-gates.ps1:202` | `evidence_hub.css`, `evidence_hub.js`, `owner_theme.js` | P |
| `scripts/run-evidence-hub-required-gates.ps1:206` | `evidence_hub.css`, `evidence_hub.js`, `owner_theme.js` | P |
| `scripts/run-evidence-hub-required-gates.ps1:209` | `admin.css`, `admin.js`, `assets/fonts/OFL.txt`, `assets/fonts/noto-sans-arabic-400.ttf`, `assets/fonts/noto-sans-arabic-500.ttf`, `assets/images/ather-navbar-logo.png`, `assets/images/dashboard-projects.svg`, `evidence_hub.css`, `evidence_hub.js`, `owner_dashboard.css`, `owner_theme.js`, `owner_workspace.css`, `style.css` | P |
| `scripts/test/run-account-entry-tests.php:64` | `landing.js` | F |
| `scripts/test/run-final-profile-route-tests.php:67` | `admin.css`, `admin.js`, `owner_theme.js`, `owner_workspace.css`, `style.css` | F |
| `scripts/test/run-final-profile-route-tests.php:68` | `assets/` | F |
| `scripts/test/run-final-profile-route-tests.php:69` | `assets/`, `assets/images/ather-navbar-logo.png` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:31` | `admin.css`, `admin.js`, `landing.js`, `onboarding.css`, `onboarding_editor.js`, `owner_dashboard.css`, `owner_theme.js`, `owner_workspace.css`, `signin.css`, `style.css` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:32` | `assets/` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:33` | `assets/` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:34` | `assets/` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:73` | `admin.js`, `landing.js`, `onboarding.css`, `onboarding_editor.js`, `owner_dashboard.css`, `owner_theme.js`, `owner_workspace.css`, `signin.css`, `style.css` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:74` | `assets/` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:75` | `assets/` | F |
| `scripts/test/run-onboarding-completion-browser-fixture.php:76` | `assets/` | F |
| `scripts/test/run-onboarding-editor-browser-fixture.php:26` | `admin.css`, `admin.js`, `landing.js`, `onboarding.css`, `onboarding_editor.js`, `owner_theme.js`, `style.css` | F |
| `scripts/test/run-onboarding-editor-browser-fixture.php:27` | `assets/` | F |
| `scripts/test/run-onboarding-editor-browser-fixture.php:57` | `landing.js`, `onboarding.css`, `onboarding_editor.js`, `style.css` | F |
| `scripts/test/run-onboarding-editor-browser-fixture.php:58` | `assets/` | F |
| `scripts/test/run-onboarding-editor-browser-tests.mjs:44` | `landing.js`, `onboarding.css`, `onboarding_editor.js` | F |
| `scripts/test/run-onboarding-editor-tests.php:20` | `onboarding_editor.js` | F |
| `scripts/test/run-onboarding-editor-tests.php:44` | `onboarding_editor.js` | F |
| `scripts/test/run-onboarding-editor-tests.php:47` | `onboarding.css`, `onboarding_editor.js` | F |
| `scripts/test/run-onboarding-presentation-tests.php:112` | `onboarding.js` | F |
| `scripts/test/run-onboarding-presentation-tests.php:117` | `onboarding.js` | F |
| `scripts/test/run-owner-readiness-creation-route-tests.php:83` | `admin.css`, `style.css` | F |
| `scripts/test/run-phase2a-oidc-http-rehearsal.ps1:298` | `style.css` | F |
| `scripts/test/run-project-publication-tests.php:145` | `assets/images/ather-navbar-logo.png` | F |
| `scripts/test/run-recovery-presentation-tests.php:82` | `landing.js` | F |
| `scripts/test/run-registration-routing-tests.php:168` | `signin.css`, `signup.css` | F |
| `signin.css:2` | `assets/fonts/noto-sans-arabic-400.ttf` | C |
| `signin.css:3` | `assets/fonts/noto-sans-arabic-500.ttf` | C |
| `signin.php:26` | `landing.js`, `versionedAssetUrl` | U |
| `signin.php:27` | `signin.css`, `versionedAssetUrl` | U |
| `signup.css:1` | `signin.css` | K |
| `signup.php:34` | `landing.js`, `versionedAssetUrl` | U |
| `signup.php:35` | `signin.css`, `versionedAssetUrl` | U |
| `signup.php:36` | `signup.css`, `versionedAssetUrl` | U |
| `tests/phase2/cases/AccountSelectionStaticTest.php:20` | `style.css` | F |
| `tests/phase2/cases/AccountSelectionStaticTest.php:112` | `evidence_hub.js` | F |
| `tests/phase2/cases/EdgeSecurityStaticTest.php:55` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/cases/EvidenceHubAggregationTest.php:508` | `portfolio.js` | F |
| `tests/phase2/cases/EvidenceHubLocalizationTest.php:162` | `evidence_hub.css` | F |
| `tests/phase2/cases/EvidenceHubOwnerPageModelTest.php:45` | `evidence_hub.js` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:152` | `evidence_hub.js` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:153` | `evidence_hub.css`, `evidence_hub.js` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:165` | `owner_theme.js` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:169` | `admin.js`, `owner_theme.js`, `versionedAssetUrl` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:170` | `owner_theme.js`, `versionedAssetUrl` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:192` | `evidence_hub.css`, `evidence_hub.js`, `owner_theme.js` | F |
| `tests/phase2/cases/EvidenceHubOwnerRoutePresenterTest.php:195` | `evidence_hub.css` | F |
| `tests/phase2/cases/EvidenceHubRequiredGatesStaticTest.php:27` | `evidence_hub.css`, `evidence_hub.js`, `owner_theme.js` | F |
| `tests/phase2/cases/EvidenceHubRequiredGatesStaticTest.php:38` | `assets/images/dashboard-projects.svg`, `owner_dashboard.css`, `owner_workspace.css` | F |
| `tests/phase2/cases/EvidenceHubSkillEvidenceTest.php:174` | `portfolio.js` | F |
| `tests/phase2/cases/EvidenceTextOwnerHintsTest.php:19` | `portfolio.js` | F |
| `tests/phase2/cases/EvidenceWritingGuidanceTest.php:91` | `evidence_hub.js` | F |
| `tests/phase2/cases/ExperiencePresentationStaticTest.php:10` | `portfolio.css` | F |
| `tests/phase2/cases/ExperiencePresentationStaticTest.php:11` | `portfolio.js` | F |
| `tests/phase2/cases/FooterPresentationStaticTest.php:10` | `portfolio.css` | F |
| `tests/phase2/cases/FooterPresentationStaticTest.php:67` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/cases/FrontendPerformanceResilienceStaticTest.php:11` | `portfolio.css` | F |
| `tests/phase2/cases/FrontendPerformanceResilienceStaticTest.php:12` | `portfolio.js` | F |
| `tests/phase2/cases/OwnerFlowStaticTest.php:45` | `admin.js` | F |
| `tests/phase2/cases/OwnerFrontendExperienceStaticTest.php:19` | `admin.js` | F |
| `tests/phase2/cases/OwnerWorkspacePresentationTest.php:29` | `owner_dashboard.css`, `owner_workspace.css` | F |
| `tests/phase2/cases/OwnerWorkspacePresentationTest.php:30` | `admin.css`, `style.css` | F |
| `tests/phase2/cases/OwnerWorkspacePresentationTest.php:79` | `owner_workspace.css` | F |
| `tests/phase2/cases/OwnerWorkspacePresentationTest.php:80` | `evidence_hub.css` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:14` | `portfolio.css` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:15` | `portfolio.js` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:17` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:23` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:25` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/cases/PortfolioPresentationStaticTest.php:62` | `portfolio.css`, `portfolio.js` | F |
| `tests/phase2/cases/PrivateMediaStaticTest.php:21` | `admin.js` | F |
| `tests/phase2/cases/PublicProjectJsonContractTest.php:72` | `portfolio.js` | F |
| `tests/phase2/cases/PublicUrlConfigurationTest.php:68` | `admin.js` | F |
| `tests/phase2/cases/PublicUrlConfigurationTest.php:69` | `admin.css` | F |
| `tests/phase2/cases/ResponsiveAccessibilityStaticTest.php:13` | `portfolio.css` | F |
| `tests/phase2/cases/ResponsiveAccessibilityStaticTest.php:14` | `portfolio.js` | F |
| `tests/phase2/cases/ResponsiveAccessibilityStaticTest.php:15` | `admin.js` | F |
| `tests/phase2/support/email-verification-preview.php:56` | `landing.js`, `versionedAssetUrl` | F |
| `tests/phase2/support/email-verification-preview.php:57` | `email_verification.css`, `versionedAssetUrl` | F |
| `tests/phase2/support/evidence-hub-interaction-visual.cjs:18` | `style.css` | F |
| `tests/phase2/support/evidence-hub-interaction-visual.cjs:131` | `style.css` | F |
| `tests/phase2/support/experience-contact-visual.cjs:31` | `portfolio.css` | F |
| `tests/phase2/support/experience-contact-visual.cjs:32` | `portfolio.js` | F |
| `tests/phase2/support/experience-contact-visual.cjs:33` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/footer-visual.cjs:27` | `portfolio.css` | F |
| `tests/phase2/support/footer-visual.cjs:28` | `portfolio.js` | F |
| `tests/phase2/support/footer-visual.cjs:29` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/onboarding-preview-router.php:20` | `landing.js` | F |
| `tests/phase2/support/onboarding-preview-router.php:21` | `onboarding.js` | F |
| `tests/phase2/support/onboarding-preview-router.php:22` | `onboarding.css` | F |
| `tests/phase2/support/onboarding-preview-router.php:23` | `assets/fonts/noto-sans-arabic-400.ttf` | F |
| `tests/phase2/support/onboarding-preview-router.php:24` | `assets/fonts/noto-sans-arabic-500.ttf` | F |
| `tests/phase2/support/owner-form-state-browser.cjs:14` | `admin.js` | F |
| `tests/phase2/support/owner-form-state-browser.cjs:60` | `admin.js` | F |
| `tests/phase2/support/owner-sidebar-visual.cjs:122` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/owner-theme-resolver-visual.cjs:67` | `owner_theme.js` | F |
| `tests/phase2/support/owner-theme-resolver-visual.cjs:161` | `owner_theme.js` | F |
| `tests/phase2/support/professional-overview-visual.cjs:32` | `portfolio.css` | F |
| `tests/phase2/support/professional-overview-visual.cjs:33` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/profile-contact-visual.cjs:22` | `portfolio.css` | F |
| `tests/phase2/support/profile-contact-visual.cjs:23` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/project-publication-http.php:6` | `'assets'` | F |
| `tests/phase2/support/project-publication-http.php:8` | `*.css`, `*.js` | F |
| `tests/phase2/support/project-publication-http.php:61` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/projects-skills-visual.cjs:13` | `portfolio.js` | F |
| `tests/phase2/support/projects-skills-visual.cjs:62` | `portfolio.css` | F |
| `tests/phase2/support/projects-skills-visual.cjs:63` | `portfolio.js` | F |
| `tests/phase2/support/projects-skills-visual.cjs:64` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/projects-skills-visual.cjs:225` | `portfolio.css` | F |
| `tests/phase2/support/projects-skills-visual.cjs:226` | `assets/images/ather-navbar-logo.png` | F |
| `tests/phase2/support/publication-mutation-http.php:7` | `'assets'` | F |
| `tests/phase2/support/publication-ux-visual.cjs:19` | `style.css` | F |
| `tests/phase2/support/publication-ux-visual.cjs:20` | `admin.css` | F |
| `tests/phase2/support/publication-ux-visual.cjs:21` | `admin.js` | F |
| `tests/phase2/support/publication-ux-visual.php:27` | `admin.css`, `admin.js`, `style.css` | F |
| `tests/phase2/support/recovery-preview.php:37` | `landing.js`, `versionedAssetUrl` | F |
| `tests/phase2/support/recovery-preview.php:38` | `recovery.css`, `versionedAssetUrl` | F |

Inventory interpretation: literal `/assets/images/…` expectations remain valid (**K**), while their `readFileSync`, `fopen`, `is_file`, copy or symlink sources become `public/assets/images/…` (**F**). The preview router keeps its explicit allowlist, maps new CSS/JS URLs to repo `public/assets/…`, and retains font URL keys with new filesystem values. Browser fixture builders copy the complete public tree into the fixture public directory rather than re-creating the old root whitelist. Private instrumented fixture applications must receive the public tree too; remove their root CSS/JS glob assumptions. Comment-only mentions and dated historical records remain evidence of their original revisions; maintained docs that instruct current setup are updated. The docs-index mentions of CSS source locations change when those files move. The JS syntax-check CI glob remains correct because `git ls-files '*.js'` includes subdirectories; PHPStan's old `assets/*` exclusion should become `public/assets/*` while retaining any private attribution exclusion needed. Font/license and unchanged image URLs are listed even when no URL edit is needed.

Also update generic topology consumers: required-gates source manifest/identity code (`197–214`), Phase 2 bind/workdir (`431`), temporary web-session bootstrap copy/removal (`347`, `365`) and all test/CI `/var/www/html` assumptions. The temporary bootstrap currently requires `__DIR__/config` and `__DIR__/includes`; putting that file unchanged into the new public root fails. Use a test-only disposable public shim requiring private app configuration/includes via `/var/www/app`, retaining APP_ENV/test-mode/nonce/replay guards and removal checks. Never ship this endpoint. Audit `tests/phase2/cases/RuntimeObservabilityStaticTest.php:69`, `PublicProjectJsonContractTest.php:79–80`, `PublicLifecycleStaticTest.php` and other vhost assertions so they verify the unified production rewrites without weakening HTTP contracts.

## 5. Dev health and bootstrap fixes

With the sibling public mount and shared vhost, the existing exact-body liveness probe sees `/var/www/public/health.php` with **200 `OK\n`**, GET/HEAD and no-store; no overlay alias is needed. Keep readiness independent: **200 `READY\n`** only after configuration, database and required dependencies are ready, 503 otherwise. The base stack and HTTPS overlay must both work on a fresh disposable namespace. Update the README's unhealthy warning and manual alias instructions only after proof.

Forward optional `PRESERVED_V1_OIDC_SUBJECT` explicitly from local `.env` through dev Compose, with **no synthetic default**. Keep it empty in `.env.example`; initialization must stop when a new baseline lacks an explicit subject. After local config review, a locked Composer install and explicit local-only synthetic subject in a disposable trial `.env`, the one bootstrap/start step becomes `docker compose up -d --build --wait`: the normal dev entrypoint waits for baseline, migrates through 003, applies the one-time binding, completes migrations, then starts Apache. A completed ownership contract remains a no-op. Keep the documented real-provider setup separate and require a separately verified durable subject before a database intended for real sign-in is initialized. Never reuse the synthetic trial database as an Owner database; no fallback, test auth bypass or synthetic issuer is added to live startup. The immutable local maintenance target skips this entire bootstrap path.

Phase B must verify missing subject fails closed on a disposable fresh baseline; explicit synthetic trial succeeds only against disposable fixtures; repeat startup is idempotent; real-identity configuration is never inferred from a trial. Preserve current registration/onboarding default-off switches. No migration or subject manipulation belongs to Phase A or the local cutover.

## 6. Security check and proof to add

The structural argument is that only repo `public/` maps into the HTTP document root; private repo contents and vendor are under `/var/www/app` with no Alias or public symlink. Dev's application bind alone does not make that path a web directory. Runtime public content must contain only approved front controllers, assets and reviewed compatibility redirects; directory listing stays disabled. Keep existing forbidden-URL rules as defense in depth and extend them for internal prefixes where useful, without relying on a blacklist as the boundary.

In Phase B extend `scripts/check-production-security.php` at its existing path: inspect public root for `tests`, `scripts`, `docs`, `contracts`, `vendor`, `data`, `runtime`, `composer.json`, `composer.lock`, secrets and configuration; inspect aliases/symlinks for escapes to private trees. Preserve the current CLI-only, production environment, secure-cookie, private-storage, PHP directive, vhost and header checks. The local maintenance target must invoke equivalent read-only validation before Apache; using the existing production check is appropriate for the Owner HTTPS configuration, with required existing secret/config forwarding, rather than turning it off to get green.

Add a required HTTP boundary check, registered in the required-gates runner and used on **both packaged dev/local and production images**, plus the dev bind-mount configuration. Use only disposable projects and synthetic databases/configuration. Request GET and HEAD with redirects disabled for `/tests/phase2/support/evidence-hub-web-session-bootstrap.php`, `/scripts/README.md`, `/docs/README.md`, `/vendor/composer/installed.json`, `/composer.json`, `/composer.lock`, `/contracts/evidence-hub-contract-v1.schema.json`, `/data/profile-catalog/catalog.json`, `/.env`, `/.git/HEAD`, `/config/database.php`, `/includes/presentation.php`, `/database/portfolio_db.sql`, `/docker/production-entrypoint.sh`, `/Dockerfile`, `/docker-compose.yml` and each corresponding directory path (with and without trailing slash). Require **403 or 404**, no redirect to a login page, no success body, no source/sentinel leakage. Test known existing files, not merely nonexistent `/tests/` paths. In a disposable fixture only, place a harmless text sentinel under private vendor if dev vendor is absent; confirm the file exists in app before requesting its URL. Never request a potentially destructive live PHP script.

Positive controls must return `/` 200, every canonical CSS/JS/image/font 200 with expected MIME and nonempty bytes, health 200 exact `OK\n`, dependency-ready readiness 200 exact `READY\n`, and retain current CSP/cache headers. Compatibility requests get only the specified 308s; canonical asset requests must be direct 200. Also verify `/public/…` does not expose an alternate tree, `/app/…` cannot reach private code, unsupported health/readiness methods remain 405, and tenant uploads remain private. This is a **future proof obligation**; Phase A has not deployed or claimed the new boundary is already enforced.

## 7. Phase B plan, ranked risks, deployment and rollback

Phase B starts only after reviewer and Owner approve this note. Recommend one implementation PR with ordered commits, keeping it unmerged until the combined result passes; intermediate commits need not be independently deployable.

| Order / commit scope | Required verification |
| --- | --- |
| 1. Shared runtime image targets, public sibling mount/vhost, explicit local maintenance startup and LF rules | Cache-free isolated builds of development/local/production; installed config equality, Apache syntax/rewrite and PHP extension checks; live edit via dev bind; public shim resolution; no old local-image ancestor. No migrations in maintenance startup, verified against disposable already-initialized fixtures. |
| 2. Asset moves, helper contract, CSS paths, complete public-tree packaging and exact old-URL redirects | Every inventory entry reviewed; meaningful helper tests for canonical absolute URL/hash, bad paths and missing files; all assets/fonts byte and MIME checks on all images; 308/query tests. In a temporary build context add a new CSS sentinel under `public/assets/css/`, build production with no Dockerfile edit, request 200 and compare bytes; remove only the disposable context. |
| 3. Gate/fixture/CI path alignment and security HTTP boundary check | Phase 2; registration challenge; source-identity challenge rejects deliberately stale disposable image; bootstrap nonce/replay/removal/two-Owner checks; new boundary check on packaged images and dev binds; no fixture endpoint shipped. Preserve all existing HTTP, tenancy and accessibility assertions. |
| 4. Dev bootstrap forwarding, README/runbook updates and repo-defined release overlay contract | Disposable fresh/absent-subject/repeat-start scenarios; base dev and HTTPS health; no synthetic fallback and no change to Owner binding. Rendered local overlay has no source binds/old aliases, correct state/proxy mounts, image revision and maintenance entrypoint. |
| 5. Combined release verification and Owner cutover evidence | Production-security check; **full required gates**, including all current visual/browser gates with identical AR/EN appearance at supported widths/themes and JS-disabled behavior. Isolated local+proxy rehearsal and rollback, then separately authorized Owner local deploy smoke. Record release digest and results; ENG-05 may automate this reviewed flow later. |

Ranked risks (highest first):

1. **Wrong effective release topology or startup mutates live state.** An old health alias, inherited source bind, `/var/www/app` symlink assumption, or migration-running entrypoint can break the site or alter storage/database. Mitigation: inspect the full merged configuration and effective installed files; use a repo-built maintenance target; preserve state identities; rehearse with isolated copies; no migrations or recursive permission repair at cutover.
2. **Asset/route/fixture resolution breaks one consumer while another stays green.** Relative fonts, doubled slashes, missing whitelist entries, different portfolio rewrites and the temporary session-bootstrap endpoint are concrete traps. Mitigation: whole-tree packaging, exhaustive inventory, canonical URLs, matched vhosts, packaged-image and dev-bind HTTP proof, full AR/EN visual gates with unchanged visual assertions.
3. **Rollback and edge configuration drift.** Caddy files and certificate data are currently anchored to an older release; switching aliases/images independently or rolling back after caching new HTML can produce a partially old stack. Mitigation: immutable paired web/proxy release descriptors, preserved Caddy data, tested backwards-compatible asset redirects, both HTTP/HTTPS smoke and a full old-descriptor rollback with browser reload/cache handling.
4. **False reproducibility or hidden security regression.** Mutable base/package inputs, omitted vendor, CRLF scripts or a weakened packaging test can escape a cached build. Mitigation: pinned/recorded inputs and release digest, clean no-cache builds, installed config/hash and PHP capability checks, exact public-tree checks, no broad normalization or test weakening.

Deploy procedure for a future authorized Owner operation (described only; **none executed in Phase A**):

1. Freeze the approved commit; build/tag by revision and record immutable image digest from a clean snapshot outside the original checkout. Run all checks above on an isolated local-equivalent project using new ports, new networks and disposable state; never attach `portfolio_course` volumes, use live credentials or alter protected image tags. Rehearse failed readiness and full rollback there.
2. Capture a private rollback descriptor of current effective non-secret service settings, exact web image ID/tag, Compose inputs, entrypoint, health alias and Caddy mount paths, state volume/bind identities and capability flags. Keep secrets in the existing secret store. Confirm the prior image and old release files remain available. Take/verify the normal approved backup per recovery runbook under the Owner's operational process; this layout change has no schema migration.
3. Prepare a **new** immutable release directory and overlay pointing at the repo-built `local` target image. Verify merged web mount list excludes both dev repo/public binds and the old alias. Preserve private-media volume, rate-limit bind, DB/network configuration and Caddy data. Do not overwrite old releases or retag/remove protected images. Run read-only config/security validation against the candidate and check its planned persistent mount identities.
4. In an Owner-approved maintenance window, replace **web only**, without dependencies, builds or migration commands, using the prepared Compose file set and pinned image (the operational equivalent of `up -d --no-deps --no-build web`). Do not run `down`, `down -v`, a database recreate or prune. Check web liveness and readiness before allowing proxy traffic. If Caddy bind sources change, recreate only `owner_https` with its existing data volume and the new reviewed read-only files after backend readiness.
5. Smoke HTTP and `https://localhost:8443`: landing AR/EN, canonical assets/fonts, 308 compatibility URLs, exact liveness/readiness, internal-file denial, security/cache headers, approved sign-in/callback/logout and representative Owner/public paths. Real-provider smoke is performed by the Owner without recording credentials/Owner content. Check state continuity and default-off flags. If any release criterion fails, roll back immediately. Record version/digest and pass/fail evidence, without secrets.

Rollback: stop the attempted rollout; reapply the **entire previous** known-good web/proxy descriptor (old image ID/reference, old maintenance entrypoint, old `owner-local-health.conf` mount, old Caddy read-only bind sources, unchanged volumes/rate-limit bind and flags), recreate only affected web/proxy services with `--no-deps --no-build`, and recheck both origins, exact probes, assets and Owner sign-in. Do not change DB schema or restore volumes as part of an image rollback. Refresh the browser and clear cached new HTML/assets when checking the restored old layout. If unexpected state mutation occurred, stop and use the separately approved recovery runbook; do not improvise a database restore. Retain old and new release artifacts until the Owner accepts stability. The actual live commands and identities must be reviewed against the then-current stack, since today's recorded image may no longer be current at Phase B time.

## 8. Open questions for the Owner

1. Approve replacing the web health alias mount with native public handlers, and moving Caddy's read-only configuration binds to each new immutable release while retaining its current image digest, named CA data and HTTPS origin?
2. Approve the repo-owned `local` maintenance target and read-only startup validation, preserving the current prohibition on implicit migrations/bootstrap/storage repair? Confirm the expected validation inputs and current state permissions before cutover.
3. Approve keeping exact old-asset 308 redirects for at least two releases and seven days, the PNG name `legacy-professional-portfolio.png`, and content-hash URL tokens? Choose a later redirect-removal review date if longer compatibility is needed.
4. Who owns the Phase B maintenance window, real-provider smoke and rollback decision, and what rollback artifact-retention period is required? Reconfirm the active image/mounts then; Phase A does not schedule or authorize cutover.

No implementation is blocked on inferring a new identity, enabling a capability, or accessing Owner data. These are approval questions for Phase B; the current stack remains unchanged.

## 9. Review decisions (2026-10-28)

The reviewer (Claude Code, who owns ENG-04 Phase B) recorded these decisions. The Owner approved the local-stack cut-over on the same date, because there are no Ather users yet. Where this section differs from sections 1–8, it wins.

1. **Filesystem layout.** The design's sibling layout is accepted: code at `/var/www/app` and DocumentRoot at `/var/www/public`. This replaces the handoff's earlier `/var/www/app/public` proposal.
   - Production already uses this layout, so the production vhost and the `public/` front controllers stay unchanged. Only dev and the local stack move to it, which is the smaller and safer change.
   - Isolation is equivalent: nothing outside `public/` is in the document root.
2. **One shipping image instead of a separate `local` target.**
   - The owner's local stack runs the **same `production` image** (same digest) that the gates and CI test.
   - Maintenance startup is a repo-owned entrypoint script, `docker/maintenance-entrypoint.sh`, shipped inside that image. The **local release overlay selects it explicitly** (`entrypoint:`), as the current overlay already overrides the entrypoint.
   - That script performs the read-only validation, including `scripts/check-production-security.php` with the owner HTTPS configuration. It never migrates, bootstraps or repairs storage.
   - It passes `--local-owner-stack` to that check. This relaxes exactly one rule: the Auth0 callback may be an HTTPS `localhost`/`127.0.0.1` origin, which a local stack necessarily uses. Every other check is unchanged. The rehearsal found that production rule correctly rejecting the local callback.
   - The default production entrypoint is unchanged.
   - This keeps byte-identical dev/prod parity, and it avoids a second image whose only difference is its configuration.
3. **No old-URL redirects.**
   - There are no users and no external consumers. Every asset URL is server-emitted and content-versioned. The only browser with cached HTML is the Owner's, which the cut-over smoke reloads.
   - The 308 compatibility layer, its test and its removal schedule are therefore dropped. Old root CSS/JS/PNG URLs return 404 after the change.
4. **Assets and versioning.**
   - Accepted as designed: the move map, `versionedAssetUrl()` with public-relative paths and a 16-hex SHA-256 content token, failing loudly on a missing file, and CSS fonts at `../fonts/…`.
   - The PNG is renamed to `legacy-professional-portfolio.png`.
   - `cache-policy.conf` additionally covers `ttf|woff2?` with the same policy.
5. **Production defect fixed by this card.** `Dockerfile.production` today omits `onboarding.js` and `recovery.css`, which return 404 in production. Whole-tree `public/` packaging fixes this.
6. **Mounts.**
   - Accepted: remove the `owner-local-health.conf` alias mount, because health and readiness are native in `public/`.
   - Accepted: Caddy's read-only binds move to the new immutable release's `docker/` files, keeping the Caddy image digest and the `caddy_data` volume.
   - Accepted: the private-storage volume and the rate-limit bind keep their identities.
7. **Phase B ownership.**
   - Claude Code implements Phase B as one PR with the ordered commits from section 7 (minus the redirect work), runs the full gates, and performs the isolated rehearsal and the cut-over.
   - The Owner performs the real Auth0 sign-in smoke after the cut-over.
   - Maintenance window: any time, since there are no users.
   - Rollback artifacts (the old image `79ee34af5355`, the old release folder and the descriptor) are kept until the Owner accepts stability, at least 14 days.
