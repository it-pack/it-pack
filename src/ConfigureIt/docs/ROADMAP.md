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

### T2 — Delete or finish the orphaned adapter leftovers

**Goal.** Remove dead, broken refactor artifacts.

- `src/Adapter/Context.php` — broken import, **zero importers**.
- `src/Adapter/Schema.php` — broken import, **zero importers**.
- `src/Adapter/Contract/ContextAdapterInterface.php` — broken import, zero importers.
- `src/Adapter/Contract/SchemaAdapterInterface.php` — broken import, only consumed
  by `src/Adapter/Processor.php` (which then cannot instantiate).

**Action.** If these are not needed for an imminent feature, **delete** all four.
If `Processor` genuinely needs a schema-port abstraction, finish
`SchemaAdapterInterface` against the reconciled namespace (T1) and keep it;
otherwise drop the `SchemaAdapterInterface|SchemaInterface` union from
`src/Adapter/Processor.php:23,31`.

**Verification**
```bash
grep -rn "Adapter\\\\Context\b\|Adapter\\\\Schema\b\|ContextAdapterInterface\|SchemaAdapterInterface" src tests examples
# expect: no matches (or only the intentional, now-fixed ones)
```

### T3 — Fix test-suite imports and run it green

**Goal.** Tests boot and pass.

**Steps.**
1. Update the five test files that import moved interfaces from
   `Contract\ValueObject\` back to their real location per T1's decision:
   - `tests/Unit/Adapter/FileSourceTest.php:10` (`ConfigurationResourceInterface`)
   - `tests/Unit/Core/LoaderFactoryTest.php:8-9` (`ConfigurationDocumentInterface`, `ConfigurationLoaderInterface`)
   - `tests/Unit/Core/ConfigurationGraphTest.php:8` (`ConfigurationNodeInterface`)
   - `tests/Unit/Core/ConfigurationBuilderTest.php:11` (`ConfigurationGraphInterface`)
   - `tests/Unit/Core/ConfigurationPipelineTest.php:8` (`ConfigurationDocumentInterface`)
2. Run the suite; iterate until green.

**Verification**
```bash
vendor/bin/codecept run Unit
# expect: all 61 test methods green (or fix any regressions the refactor exposed)
```

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

### T5 — Repair the documented examples

**Goal.** `resolve.php` and `all.php` run end-to-end and print a merged snapshot.

**Steps.**
1. `examples/resolve.php:26` / `examples/all.php:34`: call `new ConfigurationBuilder()`
   (default `ConfigurationFactory`), **not** `new ConfigurationBuilder($reader)`.
2. Add the appropriate `addReader(...)` for every source used (the resolver wraps
   readers in a `CompositeReader`; an empty reader set throws at resolution,
   `src/Adapter/CompositeReader.php:25`).
3. Remove dead `EnvReader`/`EnvSource` imports in `resolve.php`.
4. Run both; assert output contains merged values.

**Verification**
```bash
php examples/resolve.php   # expect: prints database.host / app.debug / has app.name
php examples/all.php       # expect: prints merged file + env snapshot
```

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

### Phase 2 — Additional File Formats

Adapters behind `ConfigurationLoaderInterface` (ADR-0006 registry):
- YAML (ext-yaml / nette/neon), TOML (`internal/toml`), NEON (`nette/neon`),
  XML, Markdown Front Matter (`webuni/front-matter`), INI, CSV/TSV.
- Each adapter must translate vendor exceptions to domain `ParseException`
  (AGENTS.md rule) and never import vendor types into Core.

### Phase 3 — Schema Integrations

- Nette Schema ✅ (done via `NativeConfigurationSchema`).
- Add first-class **defaults** and **type coercion** processors (only
  `DefaultsProcessor` exists today).
- Optional: custom schema providers port (`ConfigurationSchemaInterface`).

### Phase 4 — Resolution Pipeline Processors

Implement the staged pipeline from `README`/`PIPELINE.md` as discrete processors
(`ConfigurationProcessorInterface`):
- Include / Import, Merge (priority), Variables, Validation, Casting, Defaults.
- Extract `DocumentNormalizer`/`NodeFactory` from `ConfigurationPipeline` per
  `REFACTORING_PLAN.md §5` (ADR-0010) to fix the SRP split.

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
| 7 | Phase 2 formats | 2 | T1–T6 | new loader adapters + tests |
| 8 | Phase 3/4 schemas & processors | 3–4 | Phase 2 | pipeline stages tested |
| 9 | Phase 5–9 | 5–9 | Phase 4 | per-phase acceptance |

**Definition of done for Phase 0.5:** `vendor/bin/codecept run Unit`,
`vendor/bin/phpstan analyse`, and `vendor/bin/php-cs-fixer fix --dry-run` are all
green at `HEAD`, both examples run, and README/CHANGELOG accurately describe the
state. No phase is marked ✅ without its gate being green.
