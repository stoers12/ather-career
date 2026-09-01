# Phase 2 ownership migration rehearsal

P2J-02 introduces the physical ownership sequence only. It does not enable
Phase 2 routes, OIDC, public lifecycle fields, or multi-user writes.

The required order is deliberate because MySQL DDL is not one transaction:

1. Freeze legacy writes for the real cutover window.
2. Run `php database/migrate.php --through=003` to apply Ownership Expand.
3. Run `php database/backfill-v1-ownership.php --issuer <exact-issuer> --subject <exact-subject>` with the separately verified preserved-owner binding.
4. Verify the User, Portfolio, every legacy resource mapping, row counts, V1 values, timestamps, and image references.
5. Run `php database/migrate.php --through=004` to apply Ownership Contract.
6. Verify the migration ledger and foreign-key/uniqueness constraints before reopening writes.

`portfolios.owner_user_id` is non-null from the instant a Portfolio row is
created. The nullable interval applies only to ownership columns added to V1
resource rows while the controlled backfill is pending.

The backfill accepts only the explicit durable issuer and subject. It has no
email, name, default-Portfolio, first-row, or file-moving fallback. Re-running
after an interrupted compatible resource mapping is safe; incompatible or
ambiguous state stops for inspection. P2J-02 does not authorize running this
against preserved production data. The disposable rehearsal command is:

```sh
APP_ENV=test ATHERCAR_TEST_MODE=1 php scripts/run-phase2-ownership-rehearsal.php
```

Migration 004 remains blocked until backfill verifies every resource mapping.
The eventual real cutover is owned by the approved later cutover slice and
requires its backup/restore and write-freeze gates.
