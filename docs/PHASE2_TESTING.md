# Phase 2 test foundation

P2J-00 establishes a native PHP test runner. It is intentionally schema-neutral: it does not connect to MySQL, apply migrations, or use repository uploads. Later Phase 2 slices add disposable database and container integration tests to this foundation.

## Run the deterministic foundation

The runner refuses to start unless both values are explicit:

```powershell
$env:APP_ENV = 'test'
$env:ATHERCAR_TEST_MODE = '1'
php scripts/run-phase2-tests.php
```

The runner creates a random run-owned namespace below the operating system temporary directory. It derives future-safe identifiers such as:

- database: `ather_career_test_<run-id>`;
- Compose project: `ather-career-test-<run-id>`;
- storage: `<system-temp>/ather-career-phase2-tests/<run-id>/storage`.

P2J-00 does not create the database or Compose project. Future destructive tests may use only these generated identifiers after their disposable-environment gates are implemented.

Teardown requires a matching run marker and removes only that direct run-owned namespace. It never searches by filename, recursively removes a configured external root, or connects to a database.

## Safety rules

- `APP_ENV=test` and `ATHERCAR_TEST_MODE=1` are mandatory.
- A configured database target must start with `ather_career_test_`.
- CareerFit references in database, storage, Compose, secret, or path configuration are rejected.
- Production configuration files must not enable test-only settings.
- The test authentication carrier lives under `tests/` only. It is not a session, login route, header, query parameter, or production bypass.

Run the focused static guard separately when useful:

```powershell
php scripts/test/check-phase2-static-architecture.php
```

## Registry and PHPUnit

Every fast case is listed once, in `tests/phase2/registry.php`. Two runners execute that same list with one shared `TestEnvironment`:

- `scripts/run-phase2-tests.php`, the original runner. It prints `Phase 2 test foundation passed.`
- PHPUnit 12.5 (`phpunit.xml.dist`). `tests/phpunit/LegacyPhase2CaseTest.php` turns each registered case into one PHPUnit test. PHPUnit also runs every native test in `tests/phpunit/`.

**New tests are written natively in PHPUnit** in `tests/phpunit/<Subject>Test.php`:
- `final class … extends PHPUnit\Framework\TestCase`;
- one test method per behavior;
- data providers for tables;
- `self::assertSame()` so failures show expected and actual values.

Do not add new classes to the registry. Convert an existing registered case to native PHPUnit when you are changing it anyway, then remove it from the registry. A full conversion is deliberately deferred (ENG-08 report).

Both runners need `vendor/` with the development packages. The development image carries them. For a checkout, run `composer install` (without `--no-dev`) once.

## Coverage

The `phpunit-coverage` CI job builds the development image with the PCOV driver (`docker/phpunit-coverage.Dockerfile`). It runs PHPUnit with line coverage over `src/`, `includes/` and `database/`, prints the summary, and uploads `clover.xml` as the `phpunit-coverage` artifact. Code exercised only by separate processes, such as `scripts/test/run-*.php`, the MySQL rehearsals and HTTP checks, is not counted yet.

## CI command

CI runs both runners in the production image, against the checkout and its `composer install`:

```sh
docker run --rm --entrypoint php -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 \
  -v "$PWD:/workspace:ro" -w /workspace "$PORTFOLIO_PRODUCTION_IMAGE" scripts/run-phase2-tests.php
docker run --rm --entrypoint php -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 \
  -v "$PWD:/workspace:ro" -w /workspace "$PORTFOLIO_PRODUCTION_IMAGE" vendor/bin/phpunit --do-not-cache-result
```

The required gates run both inside the identity-checked development image.
