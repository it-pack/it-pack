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

<p><em>(unchanged — see above)</em></p>

## Recipe: deptrac → phparkitect (pilot = HashItAsync)

1. Create `phparkitect.php` at the package root. `require_once` the shared
   `tools/qa/phparkitect/base.php` (adjust relative path to repo root).
2. Translate each deptrac `<layer>` + `ruleset` entry into an
   `Architecture::withComponents()` chain (component name ↔ namespace
   selector, `mayDependOnComponents` ↔ allowed deps). `mayDependOnComponents()`
   includes self-dependency by default (matching deptrac's implicit self-rule).
3. For cross-namespace bridge packages (HashItAsync, CommandIt, RunItThirdPartySample),
   define the dependency namespace as its own component even when it is empty
   in the local `src/` (phparkitect vacuous-allows this).
4. For "forbid sibling imports" rules (CommandIt, DisenchantIt), use the
   shared `forbidSibling()` helper from `tools/qa/phparkitect/forbid-sibling.php`.
5. `composer.json`: add `"phparkitect/phparkitect": "^1.2"` to `require-dev`;
   change `"architecture"` script to `"vendor/bin/phparkitect check"`.
6. Rename `deptrac.yaml` → `deptrac.archive.yaml` (keep for dual-run).
7. **Dual-run:** `vendor/bin/phparkitect check` and `vendor/bin/deptrac --config-file=deptrac.archive.yaml`
   must flag the same violations (diff if not, log in migration log).
8. Only after Phase 5 docs: delete `deptrac.archive.yaml`.

**Implementation notes (Phase 2):**

- `Architecture::rules()` returns `iterable` (Generator), not `array`. When spreading into `array_merge()` or `$config->add()`,
  wrap with `iterator_to_array()` to avoid "Argument #1 must be of type array" / "positional argument after spread".
- `layeredArchitecture()` in `base.php` returns `iterator_to_array($arch->rules())` (fixed from `$arch->rules()`).
- Namespace overlap (e.g. Contract vs Contract\Port): phparkitect cannot express negative namespace globs.
  Define the sub-namespace as its own component; enforce the parent (excl sub) via `ResideInOneOfTheseNamespacesExactly` +
  `NotDependsOnTheseNamespaces`. The parent namespace is not a component in the Architecture DSL, so parent→parent deps
  are not forbidden (matching deptrac's allow-all-unless-forbidden).

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
| HashItAsync | 2.1 | Pilot. deptrac→phparkitect. Architecture DSL (rule-based, not `layeredArchitecture`). Dual-run: 0 violations both tools. |
| HashIt | 2.2 | deptrac→phparkitect. Root-level Core namespace handled via `ResideInOneOfTheseNamespacesExactly`. Dual-run: 0 violations both tools. |
| CommandIt | 2.2 | deptrac→phparkitect. Architecture DSL + `forbidSibling()` for RunIt. CLI sub-namespace forbidden from other Adapter sub-namespaces. Dual-run: 0 violations both tools. |
| CommitIt | 2.3 | deptrac→phparkitect. `layeredArchitecture()` with BehaviorDomain as separate component (merged into Domain in deptrac). Contract→[Common,Domain,BehaviorDomain] to match deptrac. Dual-run: 0 violations both tools. |
| ConfigureIt | 2.3 | deptrac→phparkitect. `layeredArchitecture()` with 10 components. Dual-run: 0 violations both tools. |
| DeriveIt | 2.4 | deptrac→phparkitect. Architecture DSL. Root-level Entry classes enforced via `ResideInOneOfTheseNamespacesExactly`. Dual-run: 0 violations both tools. |
| AsyncIt | 2.4 | deptrac→phparkitect. Contract/Port namespace overlap: Port is its own component; Contract (excl Port) enforced via `ResideInOneOfTheseNamespacesExactly` + `NotDependsOnTheseNamespaces`. Dual-run: 21 violations both tools (pre-existing architectural issues, not migration artifacts). |
| RunIt | 2.5 | deptrac→phparkitect. `layeredArchitecture()` with 10 components (Common, Contract, Domain, BehaviorDomain, Application, BehaviorApplication, SDK, Adapter, Conformance). Contract selector `Contract\*` intentionally subsumes Port (safe because deptrac is green — no Contract→Port deps exist). Dual-run: 0 violations both tools. |

## Phase 3 — Dev-dependency baseline unification

### 3.1 Remove ad-hoc tool pins, adopt just-bases

| Package | Ad-hoc pins removed | just-bases added | Notes |
|---|---|---|---|
| DisenchantIt | `phpstan/phpstan`, `phpstan/phpstan-strict-rules`, `friendsofphp/php-cs-fixer`, `infection/infection` | `php-unit-test-base`, `php-qa-base`, `php-obs-dev-base` | Retained `infection/codeception-adapter` + `phparkitect/phparkitect`. phpstan.neon `level: 9` → `level: max`. Normalized all scripts to QA lane contract. |
| HashIt | `infection/infection: ^0.33` | (already had just-bases) | Added `infection/codeception-adapter: ^0.4`. Renamed `infection.json.dist` → `infection.json5` (standard naming). Normalized scripts. |
| ConfigureItExplorer | — | `php-obs-dev-base`, `infection/codeception-adapter` | Created `infection.json5`. Constraint `*` → `*@dev` for `it-pack/configure-it`. `minimum_stability: dev` → `stable`. |
| ConfigureItPsr11 | — | `php-obs-dev-base`, `infection/codeception-adapter` | Already `*@dev`. Created `infection.json5`. |
| ConfigureItSchemaDocs | — | `php-obs-dev-base`, `infection/codeception-adapter` | Created `infection.json5`. Constraint `*` → `*@dev`. `minimum_stability: dev` → `stable`. |
| ConfigureItSymfony | — | `php-obs-dev-base`, `infection/codeception-adapter` | Already `*@dev`. Created `infection.json5`. |
| ConfigureItTesting | — | `php-obs-dev-base`, `infection/codeception-adapter` | Created `infection.json5`. Constraint `*` → `*@dev`. `minimum_stability: dev` → `stable`. |
| ConfigureItRunit | — | `php-obs-dev-base`, `infection/codeception-adapter` | Already `*@dev`. Removed `coverage`/`infection` scripts referencing `vendor/bin/phpunit`. Created `infection.json5`. |
| DeriveIt | — | — | Constraint `codeception/module-asserts: "*"` → `^3.3` (consistency with lead packages). |
| DeriveItRunit | — | `php-obs-dev-base`, `infection/codeception-adapter` | Already `*@dev`. Removed phpunit refs. Updated `infection.json5`: `testFramework: "phpunit"` → `"codeception"`. |
| RunItThirdPartySample | — | `php-obs-dev-base`, `infection/codeception-adapter` | Already `*@dev`. Removed phpunit refs. Created `infection.json5`. |

### 3.2 Script normalization

All Phase 3 target packages now expose the canonical QA lane contract:
`test` / `test:unit` / `cs:check` / `cs:fix` / `analyse` / `architecture` /
`mutation` / `quality` / `security:audit`. Removed all remaining `vendor/bin/phpunit`
references and ad-hoc `phpstan`/`fix`/`format`/`analyze` script names.

### 3.3 Constraint drift normalization

Sibling dependency constraints normalized to `*@dev` (explicit dev stability
channel). `minimum_stability` normalized to `stable` + `prefer-stable: true`
across all packages. Remaining `*` constraints are limited to PHP extension
requirements (`ext-*` — standard PHP convention with no version semantics).

### Remaining risks

- Phase 2.4 (adding phparkitect to the 8 Phase 1 packages) was NOT completed as
  part of Phase 3. The `architecture` script is not yet present in those packages.
  This should be addressed before Phase 4 root orchestration.

## Phase 4 — Root orchestration & CI

### 4.1 Root composer.json (scripts-only)

Added root `composer.json` with no dependencies — only scripts that delegate to
`make`: `quality`, `test`, `cs:check`, `analyse`, `architecture`, `mutation`.

### 4.2 Makefile

Added root `Makefile` with `quality` target (runs `composer quality` in every
package that defines it) plus lane aliases (`test`, `cs-check`, `cs-fix`, `analyse`,
`architecture`, `mutation`). Packages without the lane are silently skipped.

**14 packages** participate (all with `quality` script): AsyncIt, CommandIt,
CommitIt, ConfigureItExplorer, ConfigureItPsr11, ConfigureItRunit,
ConfigureItSchemaDocs, ConfigureItSymfony, ConfigureItTesting, DeriveItRunit,
DisenchantIt, HashIt, RunIt, RunItThirdPartySample.
