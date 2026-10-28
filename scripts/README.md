# Scripts layout

Run commands from the repository root. The three stable entry points remain directly in `scripts/`; individual commands are grouped by purpose below. See [Running tests](../AGENTS.md#running-tests) for the image-based Phase 2 command and required-gates workflow.

| Location | Purpose | Why it lives here |
| --- | --- | --- |
| [run-phase2-tests.php](run-phase2-tests.php) | Deterministic Phase 2 foundation | Stable daily test entry point. |
| [run-evidence-hub-required-gates.ps1](run-evidence-hub-required-gates.ps1) | Required MySQL, HTTP, browser and production-security gates; registration challenge | Stable daily gate entry point and gate manifest. |
| [check-production-security.php](check-production-security.php) | Production configuration/security checks | The unchanged production entrypoint invokes this exact path at container startup. |
| [test/](test/) | 68 test runners, workers, browser drivers, HTTP checks and shared test helpers | Active test commands and the helpers they invoke. |
| [ci/](ci/) | 2 CI discovery/public-smoke mocks and fixtures | Synthetic CI setup; the discovery mock is also reused by the isolated OIDC test driver. |
| [ops/](ops/) | 10 backup, restore, DR, migration, account-administration and production-smoke helpers | Operational commands and their dedicated recovery/smoke fixtures. |
| [tools/](tools/) | 2 developer tools: profile-catalog generation and image calibration | Maintenance and calibration commands that are not test runners. |
| [archive/](archive/) | Exactly 9 Owner-approved historical rehearsals and probes | Preserved with working relative paths; CI and required gates do not execute these entry points. Syntax checks and static analysis still include them. |

The approved archive contains `run-phase2-operational-security-rehearsal.php`, `run-phase2-private-media-rehearsal.php`, `run-phase2-public-contact-rehearsal.php`, `run-phase2a-isolated-callback-rehearsal.ps1`, `test-phase2a-oidc-http-harness.ps1`, `run-private-media-principal-rehearsal.php`, `run-profile-contact-mysql-rehearsal.php`, `seed-phase2-private-media-http.php` and `check-evidence-hub-updated-at-mysql.php`.

Moving a command requires updating its project-root/sibling paths and every caller, including CI, the required-gates manifest, static tests, documentation and any PHPStan baseline path. Preserve the existing guards, arguments and behavior. Operational and historical commands retain their own environment and target requirements.
