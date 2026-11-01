# Disposable security assessment runner

`scripts/ops/run-security-scan.ps1` assesses a fresh production-shaped copy. It does not fix application issues or change CI. Findings, evidence and scanner results must stay outside this public repository.

## Run

Use PowerShell 7, Docker Desktop with Linux containers, Python 3, and enough free Docker disk space for the pinned scanner images, a fresh application image and an image archive. The owner-designated private report directory must already exist.

```powershell
pwsh -NoProfile -File scripts/ops/run-security-scan.ps1 -ValidateOnly
pwsh -NoProfile -File scripts/ops/run-security-scan.ps1
```

`-OutputDirectory` accepts only a new direct child of `D:\2- Ather Portfolio Project Planing SaaS\جاهزية الإطلاق\L-12-تقرير\`. The default is a random `run-<id>` directory there. `-ActiveMinutes` sets the bounded active-scan duration (default 30, range 1–90). An incomplete or timed-out assessment is not a clean security result.

## Isolation and safety

- The runner builds its own production-target application image. It never infers an image from a resident container, and it never operates on the resident Compose project.
- A random Compose project has separate internal backend and scanner networks, a fresh MySQL database and named storage volumes. No ports are published on the host. The only HTTP target is `https://scan-target.invalid` inside the disposable network.
- ZAP shares a network only with the HTTPS front. The application and database also have no Internet egress. A separate HTTP-sender scope guard restricts the origin and suppresses session-ending operations. The ZAP API binds only inside its container.
- ZAP retains its bundled add-ons, including OAST, and starts with `-silent` and automatic updates disabled. Network isolation enforces no egress; the scanner network has exactly the scanner and HTTPS front. No external OAST service is configured. Callback-dependent rules are not tested (out-of-band), and must be listed in the private report. Composer advisories and Trivy databases are downloaded outside the scanner network; these scanners cannot reach the target.
- The only source overlay publishes the legal pages and their effective date in a disposable file, using the same constants as the existing legal fixtures. The original source is not edited. The build's two base-image references are version-pinned in a generated Dockerfile; configuration analysis still scans the original repository files.
- A guarded CLI fixture seeds exactly one synthetic owner and portfolio, reuses the application's session-establishment helpers, and writes its private session carrier outside the document root. There is no HTTP login bypass and no real identity provider is contacted.
- Session-ending endpoints and deletion POSTs are excluded from attacks. The complete deletion URL is conservatively excluded from active scanning; the scheduled page and its export are covered passively in a separate phase. These are coverage gaps for manual review, not successful active tests.
- Cleanup checks each resource's random name and ownership label before stopping/removing it. Containers, networks, named volumes and the application image created by this run are removed individually. No Compose teardown, volume-delete shortcut or pruning operation is used. Downloaded vendor images remain cached.
- Before/after `docker ps` inventories record the resident container names, IDs and image references. A mismatch or cleanup failure makes the run unsuccessful; the runner never tries to repair resident state.

ZAP runs with `zap.sh -Xmx2g`, a 3 GiB memory/swap ceiling and two CPUs. A two-second monitor records cgroup peak/current memory and the container state records `OOMKilled` and exit status. An exit 137 alone does not establish an OOM diagnosis.

## Assessment phases

Composer and both Trivy checks run independently before ZAP. Each records completion or failure; a ZAP failure never suppresses those results. The following list describes coverage, rather than execution order.

1. Public baseline: explicit public-page seeds, the public project, legal pages and contact endpoint, a bounded HTML spider, and passive queue drain. Form auto-submission is disabled; the contact request uses synthetic data.
2. Authenticated baseline: owner workspace routes, Settings in both languages and an exact-field export request through ZAP.
3. Bounded active scan: one worker per host and a two-request-per-second network limit, with an explicit origin context and exclusions. A read-only Settings request every minute keeps the synthetic session active and checks authorization; it does not extend the recent-sign-in window. Each rule is bounded to three minutes; review rule-level progress to distinguish completion from timeouts or skipped tests.
4. Scheduled-deletion passive phase and export, using the same synthetic account in that fixture state.
5. `composer audit --locked` against the repository lock file, including development dependencies.
6. Trivy HIGH/CRITICAL OS/library vulnerability analysis of an exported production-image archive; no Docker socket is mounted into a scanner. Trivy configuration analysis receives only the original Dockerfile and Compose files.
7. HTTPS headers, private-CA verification, negotiated TLS and cookie-security attribute checks. The HTTPS front uses the repository's local Caddy composition with a disposable hostname and private CA.

## Outputs and interpretation

Private outputs include redacted ZAP alerts, coverage and active progress, header/TLS checks, dependency/container/configuration results, build diagnostics and cleanup/inventory records. Cookies, CSRF/session values, credentials and complete request/response bodies are not retained in these outputs. Runtime inputs and scanner working data remain in the run-owned temporary namespace and are deleted after cleanup.

The runner's exit status describes execution and cleanup, not whether vulnerabilities exist. Findings require separate private triage, explicit false-positive decisions, severity, reproduction instructions and ownership. Write the owner's Arabic report and Claude's English appendix only in the designated private report directory. Never attach raw output or findings to a public issue or PR, and never add fixes or CI gates as part of an assessment run.

Official references: [ZAP API](https://www.zaproxy.org/docs/api/), [ZAP command-line silent mode](https://www.zaproxy.org/docs/desktop/cmdline/), [Trivy](https://trivy.dev/), and [Composer audit](https://getcomposer.org/doc/03-cli.md#audit).

Pinned tool/runtime inputs: `zaproxy/zap-stable:2.17.0`, `aquasec/trivy:0.76.1`, `composer:2.8.12`, `php:8.3.33-apache`, `mysql:8.4.7`, `caddy:2.10.2`.

## Continuous checks (CI and releases)

The full assessment above runs on demand: before the public launch, and after large changes. Two lighter checks run all the time.

- **`composer audit --locked`** (CI step "PHP dependency vulnerability audit"). It fails when any package in `composer.lock` has a published advisory. Fix it by updating the package.
- **Trivy on the production image** (CI step "Image vulnerability scan"). It fails on a HIGH or CRITICAL vulnerability **that has a fixed version**. The fix is to rebuild, because the Dockerfile upgrades Debian packages.
  - Advisories without a fixed version do not block. They are triaged privately and picked up by the next rebuild once Debian ships a fix.
- **Releases install current security updates.** `scripts/ops/release-local.ps1` builds with `--pull` and a fresh `runtime-base` stage, so a cached apt layer never keeps fixed vulnerabilities in a released image.
  - Rebuild and release at least monthly, even without code changes.
