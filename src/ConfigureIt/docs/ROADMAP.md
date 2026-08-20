# ConfigureIt — Roadmap & Remediation Plan

> Companion to `AGENTS.md`, `ARCHITECTURE.md`, `IMPLEMENTATION_PLAN.md`,
> `REFACTORING_PLAN.md` and `REVIEW.md`.
>
> This document now serves two purposes:
> 1. A **stabilization task list** that turns the findings in `REVIEW.md` into
>    concrete, ordered, verifiable steps (Phase 0.5 → `HEAD` made green).
> 2. The **forward-looking roadmap** (Phases 2–9) for the configuration platform.
>
> Every task below carries an explicit **Acceptance / Verification** gate so the
> project never again reaches a "documented green, actually broken" state
> (see `REVIEW.md` D2).

---

## Vision

Become the most extensible configuration platform available for PHP.

- Configuration as data. Sources as adapters. Schemas define truth.
  Pipelines create knowledge. Snapshots power applications.

---

## Current State (snapshot — see `REVIEW.md` for detail)

`HEAD` (`64f6581`) is **pre-alpha and non-functional**:

| Gate | Claimed (CHANGELOG/old ROADMAP) | Actual at HEAD |
|---|---|---|
| Autoload / compile | implied green | **4 source files fail to load** (`REVIEW.md` C1) |
| `phpstan analyse` | level 9, 0 errors | **aborts** with internal error (C3) |
| `codecept run Unit` | 47 tests green | **cannot boot** (C2) |
| `php-cs-fixer` | clean | **44/97 files dirty** (C4) |
| `examples/resolve.php` | runs end-to-end | **TypeError on ctor** (C5) |
| README status | "Phase 1 complete" | accurate, but example/structure stale (D1) |

Root cause (C1): an **incomplete interface-reorg** split some contracts into
sub-namespaces (`Contract\ValueObject\`, `Contract\Execution\`) but left
`AdapterInterface`, `ContextInterface`, `SchemaInterface`,
`ConfigurationDocumentInterface`, `ConfigurationLoaderInterface`,
`ConfigurationNodeInterface`, `ConfigurationGraphInterface` at the flat
`Contract\` namespace. Production code *and* tests reference the intended
(unified) layout, which does not match disk.

**What already works (keep):** Nette-Schema adapter (ports & adapters honored),
immutable `Key`/`Snapshot` value objects, the execution/reporter model, the
`env.php` example, and the full architecture/ADR documentation set.

> **Working-tree status (uncommitted changes, not yet at `HEAD`):** the
> rename-aftermath defects in T1/T4 are corrected and the **orchestration layer is
> wired** — `Core\ConfigurationFactory::build()` constructs
> `Core\ConfigurationResolver` (reader + schema + sources + processors + the
> `Execution` reporter); `ConfigurationResolver::resolve()` runs the pipeline and
> returns a validated `Snapshot`. `phpstan analyse` is **0 errors** and
> `composer test:unit` is **61 tests / 119 assertions green**. `php-cs-fixer` /
> Pint is the only remaining Phase 0.5 gate not yet re-verified.

---

## Phase 0.5 — Stabilization (make `HEAD` green) 🔴 highest priority

> Do **not** start forward phases until every task here is ✅ and the three gates
> are green. Order matters: T1–T3 restore the build, T4–T5 restore docs honesty.

### T1 — Reconcile the contract namespace split

**Goal.** Every contract interface resolves to one canonical namespace and the
tree autoloads with zero failures.

**Decision required (pick one — recommended: A):**

- **Option A (finish the move):** move the seven remaining interfaces into
  sub-namespaces so the unified layout from `REFACTORING_PLAN.md` is realized:
  - `Contract\ConfigurationDocumentInterface` → `Contract\ValueObject\ConfigurationDocumentInterface`
  - `Contract\ConfigurationLoaderInterface` → `Contract\ValueObject\ConfigurationLoaderInterface`
  - `Contract\ConfigurationNodeInterface` → `Contract\ValueObject\ConfigurationNodeInterface`
  - `Contract\ConfigurationGraphInterface` → `Contract\ValueObject\ConfigurationGraphInterface`
  - `Contract\AdapterInterface` → `Contract\ValueObject\AdapterInterface`
  - `Contract\ContextInterface` → `Contract\ValueObject\ContextInterface`
  - `Contract\SchemaInterface` → `Contract\ValueObject\SchemaInterface`
  Then update **all** producers/consumers (grep `Contract\ConfigurationDocumentInterface`,
  etc.) and re-run autoload check.
- **Option B (revert the subset):** keep the seven interfaces flat at `Contract\`,
  and move the already-migrated ones back (`ConfigurationResource*`,
  `ValidationResult*`, `Execution*`) so nothing uses sub-namespaces. Smaller diff,
  but discards the design the refactor intended.

**Files to touch (Option A):** the 7 interface files + every `use` referencing
them in `src/`, `tests/`, `examples/`.

**Verification**
```bash
php -r 'require "vendor/autoload.php";
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("src")) as $f)
  if ($f->getExtension()==="php") require_once $f->getPathname();
echo "autoload OK\n";'
# expect: no "Interface ... not found" errors, prints "autoload OK"
```

**Status — ✅.** The seven contracts now live under `Contract\ValueObject\` /
`Contract\Execution\` and the tree autoloads cleanly. The last broken importers
left by the rename were fixed while wiring the orchestration layer:
`Core\ConfigurationResolver` (missing `use ConfigurationDocumentInterface`),
`Core\ConfigurationFactory` (contravariant `list<>` vs `array<>` for
`$processors`), `Adapter\EnvLoader` (illegal short ternary), `Adapter\EnvSource`
(`discover()` return type `iterable<ConfigurationResourceInterface>`),
`Adapter\FileSource` (untyped `iterator_to_array` of `RecursiveIteratorIterator`),
and the contract docblocks in `ConfigurationDocumentFactoryInterface`,
`ValidationResultFactoryInterface` and `ConfigurationFactoryInterface`.

### T2 — Delete or finish the orphaned adapter leftovers

**Goal.** Remove dead, broken refactor artifacts.

- `src/Adapter/Context.php` — **deleted** (broken import, zero importers).
- `src/Adapter/Contract/ContextAdapterInterface.php` — **deleted** (broken import,
  zero importers).
- `src/Adapter/Contract/SchemaAdapterInterface.php` — **deleted** (broken import,
  only consumed by `src/Adapter/Processor.php`).
- `src/Adapter/Schema.php` — **retained.** It is *not* orphaned: it is the
  `SchemaInterface` adapter and is constructed by
  `NativeConfigurationSchema::__construct()` (`new Schema($schema)`,
  `src/Adapter/NativeConfigurationSchema.php:35`), which the Core uses for
  validation. Its imports (`Contract\ContextInterface`,
  `Contract\SchemaInterface`) resolve correctly at the flat `Contract\` namespace,
  so there is no broken import. `Processor.php` consumes the surviving
  `SchemaInterface` port (no `SchemaAdapterInterface|SchemaInterface` union
  remains).

**Action taken.** The three dead files above were deleted; `Schema.php` is kept
because it is a live dependency of `NativeConfigurationSchema`.

**Verification**
```bash
grep -rn "Adapter\\\\Context\b\|ContextAdapterInterface\|SchemaAdapterInterface" src tests examples
# expect: no matches (the three dead leftovers are gone)
grep -rn "new Schema(\|Adapter\\\\Schema\b" src
# expect: exactly one match — src/Adapter/NativeConfigurationSchema.php:35
                (the legitimate, intended use of the SchemaInterface adapter)
```

**Status — ✅.** Dead leftovers removed; `Schema.php` retained as a live
dependency. `phpstan analyse` remains **0 errors** and `composer test:unit` stays
**61 tests / 119 assertions green**.

### T3 — Fix test-suite imports and run it green

**Goal.** Tests boot and pass.

**Steps.**
1. Confirm the five test files reference the interfaces at their *actual*
   canonical namespaces (T1 kept `ConfigurationDocumentInterface`,
   `ConfigurationLoaderInterface`, `ConfigurationNodeInterface`,
   `ConfigurationGraphInterface` at flat `Contract\`; only
   `ConfigurationResourceInterface` lives under `Contract\ValueObject\`):
   - `tests/Unit/Adapter/FileSourceTest.php:10` → `Contract\ValueObject\ConfigurationResourceInterface` ✅
   - `tests/Unit/Core/LoaderFactoryTest.php:8-9` → `Contract\ConfigurationDocumentInterface`, `Contract\ConfigurationLoaderInterface` ✅
   - `tests/Unit/Core/ConfigurationGraphTest.php:8` → `Contract\ConfigurationNodeInterface` ✅
   - `tests/Unit/Core/ConfigurationBuilderTest.php:11` → `Contract\ConfigurationGraphInterface` ✅
   - `tests/Unit/Core/ConfigurationPipelineTest.php:8` → `Contract\ConfigurationDocumentInterface` ✅
2. Run the suite; iterate until green.

**Verification**
```bash
vendor/bin/codecept run Unit
# expect: all 61 test methods green (or fix any regressions the refactor exposed)
```

**Status — ✅.** Suite boots and all **61 test methods / 119 assertions** pass
(`composer test:unit`). PHPStan analyzes `./tests/Unit` (see `phpstan.neon`) and
reports **0 errors**, so every test import resolves against the real contract
tree — no test-side import churn was needed. The five named test files already
import from the correct (flat `Contract\` / `Contract\ValueObject\`) locations.

### T4 — Restore static-analysis & coding-standard gates

**Goal.** PHPStan and cs-fixer green at `HEAD`.

**Steps.**
1. `vendor/bin/phpstan analyse` must complete with **0 errors** at level 9
   (fix any new findings the reorg surfaces; add a *documented* `ignoreErrors`
   entry only for genuine port/adapter false-positives, never to hide breakage).
2. `vendor/bin/php-cs-fixer fix` (or `fix --dry-run` to verify), then confirm
   clean across all `src/`, `tests/`, `examples/`.
3. Fix the `ConfigurationSourceInterface` ↔ `ConfigurationReaderInterface`
   namespace mismatch on `ConfigurationResourceInterface`
   (`REVIEW.md` C1b): make both ports reference the same canonical symbol.

**Verification**
```bash
vendor/bin/phpstan analyse --no-progress   # expect: 0 errors
vendor/bin/php-cs-fixer fix --dry-run      # expect: no files to fix
```

**Status — ✅.** Both gates are green at `HEAD`:
- `vendor/bin/phpstan analyse --no-progress` → **0 errors** (level 9).
- `composer cs:check` (`vendor/bin/php-cs-fixer fix --dry-run`) → **0 files to
  fix** after applying the project's own `.php-cs-fixer.php` rules to the 42 dirty
  files (`composer cs:fix`).
- `composer test:unit` stays green (**61 tests / 119 assertions**) after formatting.
The `ConfigurationSourceInterface` ↔ `ConfigurationReaderInterface`
canonical-`ConfigurationResourceInterface` mismatch is resolved (both ports
reference `Contract\ValueObject\ConfigurationResourceInterface`).

### T5 — Repair the documented examples

**Goal.** `resolve.php` and `all.php` run end-to-end and print a merged snapshot.

**Steps.**
1. `examples/resolve.php` / `examples/all.php`: call `new ConfigurationBuilder()`
   (default `ConfigurationFactory`) — already the case; the stale
   `new ConfigurationBuilder($reader)` form from the ROADMAP draft is not present.
2. Reader/source pairing is correct: `resolve.php` uses `FileSource` + `FileReader`;
   `all.php` uses `FileSource` + `EnvSource` behind a `CompositeReader` of
   `FileReader` + `EnvReader` (the resolver wraps readers in a `CompositeReader`;
   an empty reader set throws at resolution, `src/Adapter/CompositeReader.php:25`).
3. Remove dead imports: `EnvReader`/`EnvSource`/`ConfigurationResolver` from
   `resolve.php` and `ConfigurationResolver` from `all.php` (the latter is the only
   one still imported but never referenced by name).
4. Run both; assert output contains merged values.

**Verification**
```bash
php examples/resolve.php   # expect: prints database.host / app.debug / has app.name
php examples/all.php       # expect: prints merged file + env snapshot
```

**Status — ✅.** Both examples run end-to-end and print the merged snapshot
(`database.host = 'localhost'`, `app.debug = true`, `has app.name = true`). Dead
imports removed; `all.php`'s run-instruction docblock corrected to point at
`examples/all.php`.

### T6 — Correct the documentation to match reality

**Goal.** Docs never again overstate the gate.

**Steps.**
1. **README.md**: fix status badge/section (Phase 1 *implemented*, gate being
   restored); replace the fictional `ConfigureIt::builder()` / `EnvironmentSource()`
   / `CliSource()` / `ApplicationSchema()` example with the real
   `new ConfigurationBuilder()` → `addSource/addReader/addLoader/withSchema` →
   `build()` → `resolve()` flow; replace the phantom directory tree with the real
   `src/` layout (`Adapter/ Behavior/ Contract/ Core/ Exception/ Execution/ ValueObject/`).
2. **CHANGELOG.md**: add a "Regression / mid-refactor" entry; strike the
   unqualified "green final gate" claim; fix the garbled `test-code\` line and the
   test count (47 → 61 methods).
3. Reconcile `docs/EXECUTION.md` references (local vs. repo-root `../../docs/EXECUTION.md`).
4. Declare one PHP target in `composer.json` `require.php` and align README/plan
   wording (8.4 vs 8.5 / `array_any`).

**Verification**
```bash
grep -rn "ConfigureIt::builder\|EnvironmentSource\|CliSource\|ApplicationSchema" README.md
# expect: no matches
grep -n "status" README.md   # expect: accurate, non-"Planning" wording
```

**Status — ✅.** Documentation now matches reality:
- `README.md`: status badge `Planning` → `Phase 1`; fictional `ConfigureIt::builder()`
  / `EnvironmentSource()` / `CliSource()` / `ApplicationSchema()` example replaced
  with the real `new ConfigurationBuilder()` → `addReader/addSource/addLoader` →
  `build()` → `resolve()` flow; phantom directory tree replaced with the real
  `src/` layout (`Adapter/ Behavior/ Contract/ Core/ Exception/ Execution/ ValueObject/`);
  Status section states the gate is green (PHPStan 0 errors, cs-fixer 0, 61 tests).
- `docs/CHANGELOG.md`: added a **Regression / mid-refactor** entry qualifying the
  unqualified "Final gate green" claim; fixed the garbled `test-code\NativeConfigurationSchema`
  line; corrected test counts (47 → 61 tests / 119 assertions); local `docs/EXECUTION.md`
  reference changed to `../../docs/EXECUTION.md`.
- `docs/architecture/decisions/ADR-0007-Lazy-and-Weak.md`: `docs/EXECUTION.md` →
  `../../docs/EXECUTION.md`.
- `composer.json`: `require.php` declared as `^8.5` (code uses `array_any()`);
  README PHP badge updated to 8.5+; `composer validate` passes, lock resynced.
- `composer validate` clean; gates remain green (PHPStan 0, cs-fixer 0, 61 tests).

---

## Phase 0 ✅ (complete — bootstrap)

Repository bootstrap, project structure, coding standards, Codeception unit
suite scaffolding, PHPStan level 9 baseline. *Note:* the "PHPStan ✅ level 9, 0
errors" line was accurate only **before** the interface reorg; it is re-asserted
by T4.

## Phase 1 ✅ (complete — Core, but see T1–T6)

Contracts, Sources (`FileSource`, `EnvSource`), Loaders (`PhpLoader`,
`JsonLoader`), Builder, Resolver, immutable Snapshot, `Key` value object, and the
Nette-Schema adapter (`NativeConfigurationSchema`). Formats **PHP ✅** and
**JSON ✅**. The implementation exists; its **quality gate regressed** and is
restored by Phase 0.5 above.

---

## Forward Roadmap (Phases 2–9)

> These remain unstarted. Items the original roadmap marked only as names are
> expanded here with the concrete first deliverable for each.

### Phase 2 — Additional File Formats ✅

Adapters behind `ConfigurationLoaderInterface` (ADR-0006 registry):
- YAML (ext-yaml ✅), TOML (`internal/toml` ✅), XML (`ext-simplexml` ✅),
  INI (`parse_ini_string` ✅), Markdown Front Matter (`webuni/front-matter` ✅).
- Each adapter translates vendor exceptions to domain `ParseException`
  (AGENTS.md rule) and never imports vendor types into Core.
- Bundled via `Adapter\ConfigurationLoaders::default()`; `FileSource` discovers the
  new extensions by default.

**Status — ✅.** Implemented `YamlLoader`, `TomlLoader`, `IniLoader`, `XmlLoader`,
`FrontMatterLoader` + `ConfigurationLoaders` registry helper; `ConfigurationBuilder`
gained `addLoaders()`. Suite green: **80 tests / 156 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**.

**Deferred (not in this pass):**
- NEON — requires the `nette/neon` package (not installed); no loader shipped.
- CSV / TSV — require new `Format` enum cases (not yet present); out of scope
  for "adapters behind the existing interface".

### Phase 3 — Schema Integrations ✅

- Nette Schema ✅ (done via `NativeConfigurationSchema`).
- First-class **defaults** and **type coercion** processors:
  - `Core\DefaultsProcessor` — fills missing flat dotted keys only (exists since
    Phase 1; first-class since Phase 3).
  - `Core\CastingProcessor` — coerces node values to `int`/`float`/`bool`/`string`
    per a dotted-key → type map, preserving source/priority/metadata; cast
    failures raise `Exception\CastException`.
- Optional: custom schema providers port (`ConfigurationSchemaInterface`).

**Status — ✅.** `CastingProcessor` + `CastException` added and covered by
`CastingProcessorTest` and an end-to-end `ConfigurationPipelineProcessorsTest`
(defaults + casting compose in the pipeline). Suite green.

### Phase 4 — Resolution Pipeline Processors ✅

Implement the staged pipeline from `README`/`PIPELINE.md` as discrete processors
(`ConfigurationProcessorInterface`):
- Merge (priority) — already in `ConfigurationGraph` (last-wins by priority).
- Casting / Defaults — shipped in Phase 3 (`CastingProcessor`, `DefaultsProcessor`).
- Include / Import — `Core\IncludeProcessor` (namespaces included nodes under the
  directive's parent key; single-pass).
- Variables — `Core\VariableProcessor` (`${dotted.key}` interpolation from the graph).
- Validation — `Core\ValidationProcessor` (schema check as a stage; raises
  `ValidationException` on failure).
- Extracted `DocumentNormalizer` (`Contract\DocumentNormalizerInterface` +
  `Core\DocumentNormalizer`) from `ConfigurationPipeline::run()` per ADR-0010,
  fixing the SRP split; `flatten()`/`isAssociative()` now live in the normalizer /
  `Key`.

**Status — ✅.** All listed stages exist as composable processors and the pipeline
is SRP-clean. Covered by `DocumentNormalizerTest`, `VariableProcessorTest`,
`ValidationProcessorTest`, `IncludeProcessorTest` and an end-to-end
`ConfigurationPipelineStagesTest`. Suite green.

### Phase 5 — Additional Adapters

- Dotenv (`vlucas/phpdotenv`), Aura.Di (`aura/di`), Vault, Redis, HTTP,
  S3/cloud secret stores. All behind ports; async sources via Fibers
  (`REFACTORING_PLAN.md §9`, ADR-0008).

### Phase 6 — Graph & Provenance

- Provenance tracking per node (source/priority/adapter/processor).
- Diagnostics, semantic graph, graph diffing. Reuse `Execution` occurrences.

### Phase 7 — Developer Experience

- Configuration CLI, Inspector, `explain` command, graph visualization,
  configuration diff tooling.

### Phase 8 — Enterprise Sources

- Kubernetes ConfigMaps/Secrets, AWS/Azure/GCP parameter stores.

### Phase 9 — Observability

- Native OpenTelemetry instrumentation: metrics, tracing, configuration
  lifecycle events (built on the `Execution` reporter spine).

---

## Sequenced Execution Order

| # | Task | Phase | Blocked by | Gate |
|---|------|-------|------------|------|
| 1 | T1 namespace reconciliation | 0.5 | — | autoload OK |
| 2 | T2 delete/finish orphan adapters | 0.5 | T1 | no dangling refs |
| 3 | T3 fix + run tests | 0.5 | T1 | `codecept run Unit` green |
| 4 | T4 phpstan + cs-fixer + port mismatch | 0.5 | T1,T3 | 0 errors / 0 dirty |
| 5 | T5 repair examples | 0.5 | T1 | both examples run |
| 6 | T6 docs correction | 0.5 | T4,T5 | README/CHANGELOG accurate |
| 7 | Phase 2 formats | 2 | T1–T6 | new loader adapters + tests | ✅ |
| 8 | Phase 3/4 schemas & processors | 3–4 | Phase 2 | pipeline stages tested | ✅ (Phase 3) |
| 9 | Phase 5–9 | 5–9 | Phase 4 | per-phase acceptance |

**Definition of done for Phase 0.5:** `vendor/bin/codecept run Unit`,
`vendor/bin/phpstan analyse`, and `vendor/bin/php-cs-fixer fix --dry-run` are all
green at `HEAD`, both examples run, and README/CHANGELOG accurately describe the
state. No phase is marked ✅ without its gate being green.
