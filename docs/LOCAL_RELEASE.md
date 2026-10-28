# Owner local releases

This is the ENG-05 automation of the ENG-04 manual local cut-over. It uses the approved topology and review decisions in [RUNTIME_TOPOLOGY.md `9](RUNTIME_TOPOLOGY.md#9-review-decisions-2026-10-28): the repo-built production image, its explicit maintenance entrypoint, native public health handlers, release-specific Caddy configuration, and unchanged persistent state identities.

The Owner or reviewer operates the real stack. **Agents must never execute this script or any Compose command against project `portfolio_course`.** Development and verification use an explicitly named disposable project, separate directories, ports, configuration and volumes. Do not run the examples without replacing the disposable paths.

## Requirements and release identity

Use PowerShell 7, Git, Docker/Compose with `!override` and `config --format json` support, tar, and Docker image inspection with `--platform` support (tested with Docker Engine 29.9.2 / Compose 5.6.1). The Compose root must contain `.env`, `.env.owner.local`, `docker-compose.yml` and `docker-compose.owner-https.yml`. Its three services must already be healthy, and the web labels must identify a readable `canonical-release.yml`. This script is a release tool, not a fresh-stack installer.

By default the Compose root is the original repository checkout (located through Git's common directory, so task worktrees work too). Releases default to its sibling `ather-career-releases`. The project and HTTP/HTTPS defaults match the Owner stack; always override them for agent testing.

`-MergeRef` is required for release mode. A real release fetches origin, resolves the commit and full SHA using Git, and requires it to be an ancestor of `origin/main`. An optional `-PrHead` must have the same Git tree. Every image label, build argument, overlay version and runtime version is checked against that Git-derived full SHA. A tag already associated with another revision is refused.

## Plan and release

Disposable example:

~~~powershell
pwsh -NoProfile -File scripts/ops/release-local.ps1 `
  -MergeRef origin/main -PrHead <reviewed-ref> -Label pr-merge `
  -ComposeRoot C:/isolated/compose-root -ReleasesRoot C:/isolated/releases `
  -ProjectName eng05-test -ExtraComposeFile C:/isolated/ports.yml `
  -HttpBase http://127.0.0.1:18088 -HttpsBase https://localhost:18443 -DryRun
~~~

Remove `-DryRun` only for an authorized operation. Never select a protected image revision when testing. The test fixture uses a reviewed ancestor of main with the same tree to avoid rebuilding the protected Owner merge image.

DryRun performs reads only: it prints every planned step and the substituted overlay, privately renders/asserts the merged configuration using standard input, and creates no folder, backup, image, tag or container. It does **not fetch**, because fetching writes Git metadata; its ancestry preview uses cached `origin/main` and the plan explicitly says a real run fetches and revalidates. It does not print the merged config JSON, which includes environment values.

A release creates `<UTC yyyyMMddTHHmmssZ>-<label>-<sha12>/`. Its **first artifact** is `rollback.json`, containing the previous image tag and manifest ID, container image config ID, full label-derived Compose input list, proxy mount sources and timestamp. It contains no environment values. A retained `ather-career-local:rollback-<release-folder>` alias protects that image if a same-commit rebuild moves its tag. Retain this alias with the rollback descriptor.

Next the script archives committed source with `core.autocrlf=false`, streams a root-authenticated single-transaction dump directly to private `backup/pre-release.sql`, writes its SHA-256, and restore-verifies it in a temporary `mysql:8.4` container. The verifier has no network, published port, host mount or persistent data volume and is removed in a finally block. Counts for migrations, portfolios, projects and users must agree between the dump, the restored database, and a second source read. Concurrent writes causing count drift stop the release.

A release-specific `org.ather-career.release-source` label is substituted from `{{SOURCE}}` and asserted on web after startup. Compose ignores build-context-only changes when deciding to recreate a service; this label makes same-commit releases refresh `config_files` and gives the next release the correct rollback overlay. It leaves the decided service command and mount contract unchanged.

The source database's credentials are used inside its own container. The release tool reads/dumps/counts that database; it never restores into it or changes its service lifecycle. Backups contain private data and are unencrypted: keep the release directory private, outside Git and the public root, under the existing local backup policy.

The image is built from archived `source/Dockerfile`, target `production`. The checked-in template supplies the canonical overlay; the tool never edits an old overlay. A private Compose render verifies image, version, build settings, maintenance startup, default-off capabilities, exact web/proxy mount lists, release-specific Caddy sources and persistent state identities. Extra Compose overrides are asserted too: they cannot add a repo bind or the old health alias.

Only after those checks does it recreate web with `--no-deps --no-build --wait --wait-timeout 120`. Web must be healthy and log `Production security gate passed.` before proxy recreation. Then the same bounded wait is applied to `owner_https`.

`-Migrate` is explicit and optional. It runs `compose run --rm --no-deps --entrypoint php web database/migrate.php` as a one-off PHP process after the verified backup, with the new overlay, before service replacement. It never invokes bootstrap or owner binding. It disables only the unchanged-ledger assertion. An image rollback does not undo a schema change: select schema-compatible revisions and use the separately approved recovery procedure if a migration needs recovery.

## Smoke, output and failure

Both loopback origins must pass liveness/readiness, landing/sign-in, the first published portfolio, its JSON/profile media, every page-referenced asset, anonymous Owner redirects, and internal-path denial. There must be a published portfolio with accessible profile media before release. Requests disable redirects and cookies; the local Caddy certificate is accepted only on validated loopback origins. No provider interaction or real sign-in is attempted by this tool.

The tool also checks running version/image identity, the production-security line, zero PHP fatal/warning/parse/uncaught log matches, and the unchanged migration count unless explicitly migrating. It reads logs privately, including stderr, and never prints bodies or raw diagnostics.

Output consists of `STEP <name> OK|FAIL <detail>` and the final `RELEASE_LOCAL_PASSED` or `RELEASE_LOCAL_FAILED`. A successful DryRun ends with `RELEASE_LOCAL_DRYRUN_PASSED`. `RELEASE.md` records identity/tree equality, image label, backup/hash/counts, overlay summary, durations, path/status smoke results, and an exact command to the preceding configuration. Candidate and restored smoke results are distinguished so a failure remains visible after recovery.

Any failure after attempting web replacement triggers automatic rollback. The previous image alias is verified and its original tag restored if necessary, then the **previous label-derived file list** is reapplied. Proxy is restored only if its replacement was attempted. Rollback gets its own smoke verification; the attempt records **FAILED + rolled back** and exits nonzero. If recovery also fails, it records **rollback FAILED (manual recovery required)** and still exits nonzero. A pre-service failure never recreates either service. A failed proxy deployment or smoke uses the same recovery path.

The current overlay is located by filename within `config_files`, not blindly by its final position: `-ExtraComposeFile` follows it in isolated stacks. Automatic recovery reuses the previous override list, not an unhealthy override passed to the failed candidate.

## Explicit rollback

~~~powershell
pwsh -NoProfile -File scripts/ops/release-local.ps1 `
  -Rollback C:/isolated/releases/<previous-release> `
  -ComposeRoot C:/isolated/compose-root -ReleasesRoot C:/isolated/releases `
  -ProjectName eng05-test -ExtraComposeFile C:/isolated/ports.yml `
  -HttpBase http://127.0.0.1:18088 -HttpsBase https://localhost:18443
~~~

Rollback mode verifies the target image against Git and the target overlay against the unified layout, captures the currently running configuration first, then applies the requested release and smoke-checks it. It creates a new timestamped rollback **audit folder** with `rollback.json` and `RELEASE.md`; it does not rewrite the old immutable release. It does not build, back up, migrate or restore a database. Add `-DryRun` to inspect it without writes.

Keep rollback releases, their image aliases and their Caddy source directories for at least 14 days and until the Owner accepts stability. Only unified-runtime releases are accepted by this tool's strict overlay assertions; the pre-ENG-04 layout requires its separate reviewed manual rollback procedure.

The recorded recovery command first restores the retained image alias to its original tag, checks that operation, then reapplies the preceding Compose inputs. This matters when a same-commit rebuild changed the image behind that tag.

The hidden `-TestMigrationFailure` hook requires `-Migrate` and an explicit `eng05-test` project (or `eng05-test-*`). It substitutes a PHP process that exits 1 without connecting to the database. Use it only in disposable fixtures to prove migration failure preserves both running service IDs, leaves the verified backup available and exits with `RELEASE_LOCAL_FAILED` before `up-web`.

## Verification and limits

Run the Pester-free safety suite:

~~~powershell
pwsh -NoProfile -File scripts/test/test-release-local.ps1
~~~

CI runs this command on every push and PR without `ComposeRoot`, so it needs no Docker. It parses PowerShell, checks the template/forbidden service operations, exercises rendered-config rejection cases, verifies the distinct DryRun sentinel and tests redaction (the four secret-bearing MySQL keys are masked; MySQL version metadata stays readable). With the explicit disposable paths/origins/ref parameters shown above, it also executes a real DryRun and verifies release directory contents, Git fetch metadata and stable container identities are unchanged.

Isolated release testing additionally covers two successive releases with the same Git commit and different labels, prior-overlay detection from labels, off-main refs, a hidden disposable-only build-target injection, unhealthy startup, failed smoke and explicit rollback. For `-Migrate`, verify success against the complete repository migration manifest and both origins, then inject failure and check the backup, unchanged web/proxy IDs, unchanged ledger and failure sentinel before `up-web`. Raw dummy configuration, seed diagnostics, dumps and provider-like values stay in private temporary fixtures; only sanitized transcripts and summary evidence belong in a PR.

The release script never issues Compose teardown, volume removal, prune, database service start/stop/recreate, a database restore into the source, a bootstrap, owner binding, or a remote deployment. It never changes runtime PHP, Dockerfiles or base Compose files. Disposable fixture setup/teardown is a separate test operation. Cleanup must enumerate exact owned containers, volumes, networks, image tags and temporary directories; never infer a broad live-resource deletion target.
