# Tooling Consolidation — Conversion Notes

Working notes for `docs/CONSOLIDATION_PLAN.md`. Updated as packages are migrated
to the unified stack: **Codeception** (tests) + **phparkitect** (architecture)
+ **just-bases** (dev baseline).

## Shared templates

All reusable config lives in `tools/qa/`:

| File | Purpose |
|---|---|
| `phparkitect/base.php` | `layeredArchitecture()` + `classSet()` helpers (namespace `ItPack\Qa\PhpArkitect`). |
| `phparkitect/forbid-sibling.php` | `forbidSibling()` — "ports-not-imports" rule generator (Core must not import `ItPack\RunIt\*`). |
| `codeception.dist.yml` | Base Codeception config (copy to `codeception.yml`). |
| `phpstan.neon.dist` | Base PHPStan config (`level: max`). |
| `.php-cs-fixer.dist.php` | Base PHP-CS-Fixer rule set. |
| `infection.json5` | Base Infection config (Codeception adapter, MSI ratchet at 0). |

## Recipe: PHPUnit → Codeception (pilot = ConfigureItPsr11)

1. `composer remove phpunit/phpunit --dev` (and delete `phpunit.xml.dist`).
2. `composer require --dev just-bases/php-unit-test-base:^1.0 just-bases/php-qa-base:^1.0 codeception/module-asserts:^3.0`
   (add `codeception/module-phpbrowser:^3.0` only where HTTP is tested).
3. Copy `tools/qa/codeception.dist.yml` → `codeception.yml`.
4. In each test: `extends \PHPUnit\Framework\TestCase` → `extends \Codeception\Test\Unit`
   (assertions / `setUp` / `tearDown` / data providers keep working).
5. composer scripts: `test` → `vendor/bin/codecept run`, `test:unit` → `vendor/bin/codecept run Unit`.
6. Verify: `composer test` green.

## Recipe: deptrac → phparkitect (pilot = HashItAsync)

1. Create `phparkitect.php` importing `tools/qa/phparkitect/base.php`; port layer
   rules from `deptrac.yaml` via `layeredArchitecture()`.
2. Rename `deptrac.yaml` → `deptrac.archive.yaml` (keep for dual-run diff).
3. composer script `architecture` → `vendor/bin/phparkitect`.
4. Dual-run: compare `phparkitect` output vs
   `vendor/bin/deptrac --config-file=deptrac.archive.yaml`; both must flag the
   same violations (if any) before the deptrac config is retired.
5. For "forbid sibling import" rules use `forbidSibling()` from the shared base.

## Migration log

| Package | Phase | Notes |
|---|---|---|
| DisenchantIt | 0 | First consumer of `tools/qa/phparkitect/base.php` (`forbidSibling` for Core ≠ RunIt). |
