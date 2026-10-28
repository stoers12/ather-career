# Code architecture and module migration

This note describes where PHP code lives and the rule for moving a module from procedural `includes/` files into autoloaded classes under `src/`. The runtime layout (image, document root, assets) is described separately in [RUNTIME_TOPOLOGY.md](RUNTIME_TOPOLOGY.md).

## Layout

| Path | Holds | Loaded by |
|---|---|---|
| `*.php` (root) | Thin routes: authentication, CSRF, request shape, then a call into `includes/`. | A one-line front controller in `public/`. |
| `includes/` | Procedural domain logic, repositories and, for migrated modules, compatibility wrappers. | `require_once`. |
| `src/` | Namespaced classes under `Ather\`, one class per file (PSR-4). | Composer's autoloader only. |
| `vendor/` | Composer packages and the generated autoloader. Never committed. | `includes/autoload.php`. |

`includes/autoload.php` is the single entry to Composer's autoloader. It fails with a clear message when `vendor/` is missing instead of letting a class lookup fail later.

## Where `vendor/` comes from

- **Images** (`development`, `production`; CI, the required gates, the Owner's local stack): the `dependencies` stage of the [Dockerfile](../Dockerfile) installs packages, copies `src/` and runs `composer dump-autoload --optimize`, so every class under `src/` is in the class map. Changing `src/` rebuilds only the class map; packages stay cached.
- **A source checkout** (dev Compose bind-mounts it over the image code, PHPStan, ad hoc PHP): run `composer install` once after cloning and again whenever `composer.json` or `composer.lock` changes. Without PHP locally: `docker run --rm -v "${PWD}:/app" -w /app composer:2 install`.
- **Required gates** run the Phase-2 suite inside the identity-checked development image (`-w /var/www/app`), so a fresh worktree without `vendor/` is still verified against the exact source.

## Pilot: Evidence Hub text rules and aggregation (ENG-06)

| Class | Former file |
|---|---|
| `Ather\EvidenceHub\Text\EvidenceTextPolicy` | `includes/evidence_text_policy.php` |
| `Ather\EvidenceHub\Text\EvidenceTextNormalizer` and `EvidenceTextRuntimeUnavailableException` | `includes/evidence_text_normalization.php` |
| `Ather\EvidenceHub\Text\EvidenceTextEvaluator` | `includes/evidence_text_evaluator.php` |
| `Ather\EvidenceHub\Aggregation\EvidenceHubAggregation` and `EvidenceHubAggregationInvariantException` | `includes/evidence_hub_aggregation.php` |

The former files remain as compatibility wrappers. Every global function keeps its name, signature and docblock and delegates in one line. Every global constant is defined from the matching class constant. Call sites did not change. The two exceptions were only thrown and caught inside Evidence Hub, so their few users import the namespaced class directly and no global alias exists.

Behavior is unchanged. The golden fixtures and contracts v1–v9 still pass byte-for-byte. A differential run of the old and new implementations over the same large corpus (fixture strings plus seeded synthetic Unicode text, both rule versions, every aggregation entry point and every invariant failure) produced identical output. Only an uncaught exception's internal log context changes: it now carries the namespaced class name.

## Migration rule for the remaining modules

Move one module per card, in the same shape as the pilot:

1. **Pick a cohesive module** whose pure logic has strong tests. Repositories and HTTP code move only after their pure helpers.
2. **Create final classes** under `src/<Module>/…` in namespace `Ather\<Module>\…`: `declare(strict_types=1)`, one class per file, the file name equal to the class name. Pure logic uses static methods. Introduce instances only where state or dependencies (for example `PDO` + `AuthorizedPortfolioContext`) are real.
3. **Copy the bodies verbatim.** Change only calls between the moved functions (to `self::` or the sibling class) and callable strings (to first-class callables such as `self::method(...)`). Import global classes with `use`.
4. **Turn the old file into wrappers** that `require_once` their former dependencies (directly or through `includes/autoload.php`), keep every global function and constant, and delegate in one line. Remove a wrapper only in a later card, after its call sites are migrated.
5. **Classes, not `class_alias`.** PHPStan cannot see runtime aliases. Update the users of a moved class to import it.
6. **Prove no behavior change.** Golden/contract tests unchanged, `composer analyse` with no new baseline entries, the full required gates, and a differential run when the module transforms user text or produces contract output.
7. **Keep `src/` internal.** It sits outside the document root. The public-boundary gate probes it, and `StaticArchitectureGuards` scans it like `includes/`.
