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

The it-pack **lead** packages (RunIt, AsyncIt, CommandIt, …) run their Unit
suites under Codeception **while keeping `class XTest extends \PHPUnit\Framework\TestCase`**
— Codeception's Unit runner is a thin wrapper over PHPUnit, so the test classes
themselves need **no change**. The AGENTS.md / EXECUTION.md standard therefore
states "the Unit suite subclasses `PHPUnit\Framework\TestCase`".

So the mechanical conversion is config-only:

1. `composer.json` `require-dev`: remove `phpunit/phpunit`, `phpstan/phpstan`,
   `friendsofphp/php-cs-fixer` (and `squizlabs/php_codesniffer`, `infection/infection`
   in the RunIt-bridge packages); add `codeception/codeception`,
   `codeception/module-asserts`, `just-bases/php-unit-test-base`,
   `just-bases/php-qa-base` (plus `codeception/module-phpbrowser` for web packages).
2. Add `infection/extension-installer` (and `dantleech/what-changed`,
   `tbachert/spi: false`) to `config.allow-plugins` — `just-bases/php-qa-base`
   pulls `infection/infection`, whose composer plugin must be allowed (mirrors
   the lead packages).
3. `scripts`: `test` → `vendor/bin/codecept run`, `test:unit` →
   `vendor/bin/codecept run Unit`. Drop the non-standard `cs:lines` (phpcs) script.
4. Copy `tools/qa/codeception.dist.yml` → `codeception.yml`.
5. Add `tests/Unit.suite.yml` (`path: .`, `actor: UnitTester`,
   `modules: [Asserts]`) and `tests/Support/UnitTester.php` (copied from a lead
   package). `codecept build` (run in CI) generates `tests/Support/_generated/*`.
6. Delete `phpunit.xml.dist`.

## Migration log

| Package | Phase | Notes |
|---|---|---|
| DisenchantIt | 0 | First consumer of `tools/qa/phparkitect/base.php` (`forbidSibling` for Core ≠ RunIt). |
| ConfigureItPsr11 | 1 | Pilot. PHPUnit→Codeception (config-only; tests unchanged). |
| ConfigureItExplorer | 1 | PHPUnit→Codeception; web pkg (+module-phpbrowser). |
| ConfigureItSchemaDocs | 1 | PHPUnit→Codeception. |
| ConfigureItSymfony | 1 | PHPUnit→Codeception; web pkg (+module-phpbrowser). |
| ConfigureItTesting | 1 | PHPUnit→Codeception. |
| ConfigureItRunit | 1 | PHPUnit→Codeception; dropped phpcs/infection direct + `cs:lines`. |
| DeriveItRunit | 1 | PHPUnit→Codeception; dropped phpcs/infection direct + `cs:lines`. |
| RunItThirdPartySample | 1 | PHPUnit→Codeception; dropped phpcs/infection direct + `cs:lines`. |
