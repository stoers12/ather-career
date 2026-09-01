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
php scripts/check-phase2-static-architecture.php
```

## CI command

CI runs the foundation in a disposable PHP container:

```sh
docker run --rm -e APP_ENV=test -e ATHERCAR_TEST_MODE=1 \
  -v "$PWD:/app:ro" -w /app php:8.3-cli php scripts/run-phase2-tests.php
```
