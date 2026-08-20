# ConfigureIt — Code Review & Project State Assessment

> Reviewer date: 2026-08-20
> Scope: full package `it-pack/configure-it` — `src/`, `tests/`, `docs/`, `examples/`, root `composer.json`, QA config.
> Method: static reading of every `src/` file (78 PHP files, ~2,788 LOC), the test suite (61 test methods across 19 files), the docs set (8 architecture docs, 10 ADRs, CHANGELOG/ROADMAP/plans), and the three runnable examples. Quality gates were executed directly: `phpstan analyse`, `codecept run Unit`, `php-cs-fixer fix --dry-run`, and `php examples/*.php`.

---

## 1. Executive Summary

ConfigureIt is a **richly designed but currently non-functional** package. The architecture documentation (ADRs, `architecture/`, `IMPLEMENTATION_PLAN.md`, `REFACTORING_PLAN.md`) is mature, opinionated and internally consistent — this is by far the strongest part of the project. The source code implements a coherent Phase-0/Phase-1 slice (contracts, sources, loaders, builder, resolver, immutable snapshot, a Nette-Schema adapter, an execution/reporter model).

**However, the package does not build, does not pass its own quality gates, and its primary documented examples crash.** The repository is mid-way through an incomplete "refactor core / magic numbers integration" effort (see commits `e464bbf`, `3e62ba4`) that reorganized *some* interfaces under sub-namespaces (`Contract\ValueObject\`, `Contract\Execution\`) but left others behind. Both production code and the test suite now reference the *target* namespace layout, which does not match the *actual* on-disk layout. The result is that four source files fail to autoload, PHPStan aborts with internal errors, the entire test suite cannot even boot, and `php-cs-fixer` reports 44 of 97 files dirty.

The `docs/CHANGELOG.md` describes a "green final gate" (PHPStan level 9 = 0 errors, cs-fixer clean, 47 Codeception tests green, `examples/resolve.php` running end-to-end). **None of those claims hold at `HEAD` (`64f6581`).** The changelog documents a prior, pre-refactor state that has since regressed; it has not been updated to reflect the broken refactor.

**Verdict:** Pre-alpha. Strong design surface, promising core, but the codebase as committed is not runnable and must not be released or depended upon. The path to green is concrete and well-scoped (see §8).

---

## 2. What Works (Strengths)

- **Architecture documentation is excellent.** 8 architecture docs (`MODEL.md`, `PORTS.md`, `PIPELINE.md`, `GRAPH.md`, `ARCHITECTURE.md`, `OBSERVABILITY.md`, `ADAPTERS.md`, `RESOLUTION.md`) and 10 ADRs (`ADR-0001`…`ADR-0010`) give a clear, ratified design vocabulary. The `REFACTORING_PLAN.md` is a genuinely good SOLID/DRY review and even foresaw the `Key` value object that was later added.
- **Ports & Adapters is honored where it matters.** `src/Adapter/NativeConfigurationSchema.php` cleanly isolates `nette/schema` behind `ConfigurationSchemaInterface`; vendor exceptions are translated into the domain `ValidationResult` (`src/Adapter/NativeConfigurationSchema.php:36-64`). The Core never imports Nette types.
- **Immutability & strong typing.** `readonly` classes are pervasive; value objects (`Format`, `ConfigurationResource`, `ValidationResult`, `Key`, `Occurrence`, `Observation`) are immutable. `Key` (`src/Core/Key.php`) is a well-documented, validated first-class abstraction for the dotted-key space — directly addressing `REFACTORING_PLAN.md §2/§3`.
- **Execution model.** `src/Execution/` (`ExecutionContext`, `Occurrence`, `Observation`, `ResolutionReporter`) provides a framework-free provenance/observability spine that the resolver emits into.
- **One example actually runs.** `examples/env.php` executes successfully and exercises `EnvSource`/`EnvReader` end-to-end.
- **`CreatableBehavior` trait** (`src/Behavior/CreatableBehavior.php`) reduces factory boilerplate (used by `OccurrenceFactory`, `MessageFactory`, `TempFileFactory`, `ConfigurationResourceFactory`, `ValidationResultFactory`) — a reasonable DRY choice.

---

## 3. Critical Findings

### C1 — Source code does not compile (broken namespace imports) 🔴

Four production files reference interfaces that do not exist at the referenced namespace. Autoloading them fails fatally:

```
LOAD FAIL: src/Adapter/Contract/ContextAdapterInterface.php -> Interface "ItPack\ConfigureIt\Contract\ValueObject\ContextInterface" not found
LOAD FAIL: src/Adapter/Contract/SchemaAdapterInterface.php  -> Interface "ItPack\ConfigureIt\Contract\ValueObject\SchemaInterface" not found
LOAD FAIL: src/Adapter/Schema.php                          -> Interface "ItPack\ConfigureIt\Contract\ValueObject\SchemaInterface" not found
LOAD FAIL: src/Adapter/Context.php                         -> Interface "ItPack\ConfigureIt\Contract\ValueObject\ContextInterface" not found
```

The offending imports are:

- `src/Adapter/Contract/ContextAdapterInterface.php:7-8` → `Contract\ValueObject\ContextInterface` and `Contract\ValueObject\AdapterInterface` (both live at flat `Contract\`).
- `src/Adapter/Contract/SchemaAdapterInterface.php:7-8` → `Contract\ValueObject\AdapterInterface` and `Contract\ValueObject\SchemaInterface`.
- `src/Adapter/Context.php:7` → `Adapter\Contract\ContextAdapterInterface` (pulls in the broken file above).
- `src/Adapter/Schema.php:6-8` → `Adapter\Contract\ContextAdapterInterface`, `Contract\ValueObject\ContextInterface`, `Contract\ValueObject\SchemaInterface`.

**Root cause:** an incomplete refactor that split interfaces into sub-namespaces. Some were moved (`Contract\ValueObject\ConfigurationResourceInterface`, `ValidationResultInterface`; `Contract\Execution\ExecutionContextInterface`, etc.) but `AdapterInterface`, `ContextInterface`, `SchemaInterface`, `ConfigurationDocumentInterface`, `ConfigurationLoaderInterface`, `ConfigurationNodeInterface`, `ConfigurationGraphInterface` remained at flat `Contract\`. The moved files and the tests were updated to the new layout; these four were not.

**Latent secondary break:** `src/Adapter/Processor.php:8,23,31` uses `SchemaAdapterInterface` in a union parameter type. Because `SchemaAdapterInterface` cannot autoload, instantiating `Processor` (the default in `NativeConfigurationSchema::__construct`, `src/Adapter/NativeConfigurationSchema.php:31`) would fatal at runtime even though the class file itself parses. The otherwise-clean schema adapter is therefore non-instantiable as written.

These four files are also effectively **orphaned**: `ContextAdapterInterface` has zero importers; `Adapter\Context` and `Adapter\Schema` have zero importers; only `SchemaAdapterInterface` is consumed (by `Processor.php`). They are dead, broken refactor leftovers.

**Unifying root cause.** The intended end-state is a *unified sub-namespace layout*: every contract interface lives under `Contract\ValueObject\` (value-object ports) or `Contract\Execution\` (execution ports). The move was applied **only partially**:
- *Moved correctly:* `ConfigurationResourceInterface`, `ConfigurationResourceFactoryInterface`, `ValidationResultInterface`, `ValidationResultFactoryInterface` → `Contract\ValueObject\`; `ExecutionContextInterface`, `ObservationInterface`, `OccurrenceInterface`, `ResolutionReporterInterface`, `OccurrenceFactoryInterface` → `Contract\Execution\`. Most adapters (`FileSource`, `FileReader`, `EnvReader`, `EnvSource`, `CompositeReader`, `NativeConfigurationSchema`) were updated to the new namespaces.
- *Not moved (still flat `Contract\`):* `AdapterInterface`, `ContextInterface`, `SchemaInterface`, `ConfigurationDocumentInterface`, `ConfigurationLoaderInterface`, `ConfigurationNodeInterface`, `ConfigurationGraphInterface`. The broken files and the test suite assume these were moved too.

Until the layout is made consistent (finish the move, or revert the moved subset), the package cannot build.

### C1b — Port docblock/namespace mismatch in the source→reader handoff 🟠

`ConfigurationSourceInterface::discover()` documents its return as `iterable<ConfigurationResourceInterface>` using the **non-existent flat** `Contract\ConfigurationResourceInterface` (`src/Contract/ConfigurationSourceInterface.php:20` — phpdoc only, so it does not fatal at load). Meanwhile `ConfigurationReaderInterface::read()` requires the real `Contract\ValueObject\ConfigurationResourceInterface` (`src/Contract/ConfigurationReaderInterface.php:7,23`). At runtime the resolver (`src/Core/ConfigurationResolver.php:134-135`) passes a `ValueObject`-namespaced resource into the reader, which works, but the source port's docblock lies about the type and would raise a PHPStan error once analysis can run. The two port definitions disagree on the canonical namespace of `ConfigurationResourceInterface`.

### C2 — Test suite cannot run 🔴

Running `vendor/bin/codecept run Unit` aborts before any test executes:

```
PHP Fatal error: Uncaught Error: Interface
"ItPack\ConfigureIt\Contract\ValueObject\ConfigurationLoaderInterface" not found
in tests/Unit/Core/LoaderFactoryTest.php:14
```

The test suite was written against the *target* (post-refactor) namespace layout and imports these interfaces from `Contract\ValueObject\`:

- `tests/Unit/Adapter/FileSourceTest.php:10` → `Contract\ValueObject\ConfigurationResourceInterface`
- `tests/Unit/Core/LoaderFactoryTest.php:8-9` → `Contract\ValueObject\ConfigurationDocumentInterface`, `Contract\ValueObject\ConfigurationLoaderInterface`
- `tests/Unit/Core/ConfigurationGraphTest.php:8` → `Contract\ValueObject\ConfigurationNodeInterface`
- `tests/Unit/Core/ConfigurationBuilderTest.php:11` → `Contract\ValueObject\ConfigurationGraphInterface`
- `tests/Unit/Core/ConfigurationPipelineTest.php:8` → `Contract\ValueObject\ConfigurationDocumentInterface`

On disk, those interfaces live at flat `Contract\` (e.g. `Contract\ConfigurationLoaderInterface`). Because Codeception includes all test files at boot, a single unresolvable `use` aborts the whole run. **The 61 test methods (19 files) have not been executed successfully at `HEAD`.** The `CHANGELOG.md` claim of "47 unit tests / 99 assertions, all green" describes a prior state, not the current one.

### C3 — PHPStan aborts with internal errors 🔴

```
[ERROR] Found 2 errors
Internal error: Interface "ItPack\ConfigureIt\Contract\ValueObject\ContextInterface" not found
while analysing file .../src/Adapter/Context.php
... Result is incomplete because of severe errors.
```

This directly contradicts `CHANGELOG.md` ("Step 7 — PHPStan level 9: **0 errors**") and `ROADMAP.md` ("PHPStan ✅ (level 9, 0 errors)"). The gate is red: PHPStan cannot complete analysis.

### C4 — Coding standard is not clean 🔴

`vendor/bin/php-cs-fixer fix --dry-run --diff` reports **44 of 97 files** need fixing (e.g. missing trailing newline in `src/ValueObject/ConfigurationResourceFactory.php`, and 43 others). This contradicts `CHANGELOG.md` Step 8 ("`php-cs-fixer` … clean across all 57 files"). Note also that the file count in the changelog (57) does not match the current 78 source files, another sign the changelog predates the current tree.

### C5 — Documented examples crash 🔴

- `examples/resolve.php:26` calls `new ConfigurationBuilder($reader)` passing a `FileReader`. But `ConfigurationBuilder::__construct` (`src/Core/ConfigurationBuilder.php:54-57`) expects `ConfigurationFactoryInterface` as argument #1. Result:
  ```
  TypeError: ConfigurationBuilder::__construct(): Argument #1 ($factory) must be of type
  ConfigurationFactoryInterface, ItPack\ConfigureIt\Adapter\FileReader given
  ```
  Even after fixing the constructor, `resolve.php` never calls `addReader()`, so the resolver's `CompositeReader` (`src/Core/ConfigurationBuilder.php:98`) is built over an **empty** reader list and would throw `InvalidArgumentException: No reader supports …` at resolution time (`src/Adapter/CompositeReader.php:25`). The example also imports `EnvReader`/`EnvSource` and never uses them.
- `examples/all.php:34` has the same `ConfigurationBuilder` constructor-type error.
- `examples/env.php` **does** run correctly — the only working example.

`CHANGELOG.md` Step 5/8 explicitly claims `examples/resolve.php` "runs end-to-end and prints the merged immutable snapshot." It does not.

---

## 4. Documentation Accuracy Gaps

### D1 — README is stale and contradictory 🟠

- **Status mismatch.** Badge reads `status-Planning` and the Status section says "currently in the design and architecture phase" / "initial milestone focuses on establishing Core contracts…". Yet `ROADMAP.md` and `CHANGELOG.md` mark Phase 0 and Phase 1 **complete** with real implementations. The README undersells a shipped (if broken) Phase 1.
- **Fictional primary example.** The README "Example" (`README.md:168-190`) uses `ConfigureIt::builder()`, `new EnvironmentSource()`, `new CliSource()`, `new ApplicationSchema()`. **None of these exist.** There is no `ConfigureIt` facade class anywhere in `src/` (confirmed by search). The real API is `new ConfigurationBuilder()` → `addSource()` / `addReader()` / `addLoader()` / `withSchema()` → `build()` → `resolve()` (see `src/Core/ConfigurationBuilder.php`, `src/Core/ConfigurationResolver.php`). A new user copying the README example cannot run anything.
- **Phantom directory tree.** `README.md:248-275` "Project Structure" lists `Bridge/`, `Cache/`, `Context/`, `Discovery/`, `Event/`, `Graph/`, `Loader/`, `Pipeline/`, `Processor/`, `Resolver/`, `Schema/`, `Snapshot/`, `Source/`, `Support/`. **None of these exist in `src/`.** Actual top-level dirs are: `Adapter/`, `Behavior/`, `Contract/`, `Core/`, `Exception/`, `Execution/`, `ValueObject/`.
- **Feature list overstates reality.** README lists YAML/TOML/NEON/XML/INI/Front-Matter/CSV/Table-Schema as supported/roadmap formats and many adapters (Aura.Di, phpdotenv, front-matter, tableschema) — none are implemented as adapters; only `PhpLoader`, `JsonLoader`, `EnvSource`/`EnvReader`, `FileSource`/`FileReader` exist.

### D2 — CHANGELOG describes a state that no longer exists 🟠

`CHANGELOG.md` is written as a linear success narrative (Steps 0-8, "Final gate green: phpstan level 9 (0 errors) + cs-fixer (0) + Codeception (47 tests)"). At `HEAD` none of these hold (C1-C5). The changelog also contains a garbled line (`src/Adapter/test-code\NativeConfigurationSchema`, `CHANGELOG.md:80`) and an incorrect test count (47 vs. 61 actual methods). It needs either a correction or a new "Regression / mid-refactor" entry.

### D3 — Dangling doc references 🟡

Code comments and `CHANGELOG.md` reference `docs/EXECUTION.md` (a local ConfigureIt doc) which does not exist. The execution model actually lives at the monorepo root `../../docs/EXECUTION.md` (verified present). `IMPLEMENTATION_PLAN.md:4` and `REFACTORING_PLAN.md` likewise point at `../../docs/EXECUTION.md`. Cross-references should be reconciled.

### D4 — PHP version inconsistency / no platform constraint 🟡

- `composer.json` has **no `require.php`** constraint.
- `README.md` badge says **PHP 8.4+**; `IMPLEMENTATION_PLAN.md:40` says **PHP 8.5.x**; `REFACTORING_PLAN.md` cites "PHP 8.4+ language features" then lists Lazy Objects/Virtual Properties (8.4) *and* `array_any` (8.5, used in `src/Adapter/CompositeReader.php:30`).
- With no `require.php`, nothing enforces the actual minimum. Pick a single target and declare it.

---

## 5. Architecture & Design Assessment

**Positive / ratified patterns**
- ADR-0002 (Ports & Adapters) is genuinely followed by the schema adapter and the source/loader separation.
- ADR-0003 (Immutable Snapshots) and ADR-0009 (`Key` value object) are realized well.
- The resolver emits Occurrences through a reporter rather than leaking internals — good observability posture for later OTel work (ROADMAP Phase 9).

**Design critiques (not blockers, but worth tracking)**
- **God-coordinator.** `ConfigurationResolver::resolve()` (`src/Core/ConfigurationResolver.php:55-60,127-148`) still performs discovery, reading, loader selection, document construction, validation and snapshotting inline. `REFACTORING_PLAN.md §1` already calls this out; the `ConfigurationLoaderFactory` port (ADR-0006) was added but the resolver still inlines document construction (`new ConfigurationDocument(...)` at the call site in `resolveResources()`). Partial remediation.
- **Concrete dependency in Core.** `ConfigurationResolver::__construct` requires the concrete `ConfigurationPipeline` (`src/Core/ConfigurationResolver.php:42`), not `ConfigurationPipelineInterface`. AGENTS.md states Core may depend on Contracts; this is a minor DIP slip.
- **Generic exception from an adapter.** `CompositeReader::read()` throws `\InvalidArgumentException` (`src/Adapter/CompositeReader.php:25`) instead of the domain `ReadException` that otherwise exists in `src/Exception/`. Inconsistent with the project's own "translate/raise domain exceptions" rule (AGENTS.md, ADR-0002).
- **No empty-input guards.** A builder with no sources or no loaders produces a resolver that fails late (loader factory throws `ResolutionException` on unknown format; reader throws on empty reader set). Acceptable, but a fail-fast `build()` validation would improve DX.
- **`CompositeReader` typing/style.** `private array $readers` is untyped (should be `list<ConfigurationReaderInterface>`), the class is non-`readonly`, and the constructor has a stray blank line (`src/Adapter/CompositeReader.php:12-15`). Cosmetic, caught by the cs-fixer drift (C4).
- **Processor wiring is thin.** Only `DefaultsProcessor` (`src/Core/DefaultsProcessor.php`) exists; the pipeline's `validate`/`merge`/`variable`/`include` stages described across README/ROADMAP/architecture are not implemented. This is expected for Phase 1 but should be reflected honestly in status docs.

---

## 6. Roadmap Alignment

`ROADMAP.md` correctly marks **Phase 0 (tooling/bootstrap)** and **Phase 1 (Core: contracts, sources, loaders, builder, resolver, snapshot, PHP+JSON)** as ✅. This is *almost* true — the Phase 1 slice is present in code. The discrepancy is that the *implementation quality gate* for Phase 1 (green PHPStan/cs-fixer/tests) has regressed due to the unfinished refactor. Phases 2-9 (YAML/TOML/NEON, schema integrations, pipeline processors, graph diagnostics, CLI, enterprise sources, OTel) remain unstarted, consistent with the roadmap.

Recommendation: do not mark any phase "complete" until its gate is green at `HEAD`.

---

## 7. Testing Reality

- 19 unit test files, **61 test methods**. Cannot be executed at `HEAD` (C2).
- Coverage intent is good: resolver priority-merge, immutability, exception translation, occurrence ordering, schema success/failure are all targeted by existing tests (per `CHANGELOG.md` Step 6). The tests are valuable *once the namespace drift is fixed*.
- No mutation testing / infection config observed; `composer.json` allows the `infection/extension-installer` plugin but no `infection.json` is present.

---

## 8. Prioritized Recommendations

**P0 — Make it build & green (blocks everything):**
1. Reconcile the namespace split. Either (a) move the remaining interfaces (`AdapterInterface`, `ContextInterface`, `SchemaInterface`, `ConfigurationDocumentInterface`, `ConfigurationLoaderInterface`, `ConfigurationNodeInterface`, `ConfigurationGraphInterface`) into their target sub-namespaces (`Contract\ValueObject\`, `Contract\Execution\` as appropriate) **and** update all producers/consumers, or (b) revert the four broken adapter files and the test imports back to flat `Contract\`. Option (a) preserves the intended design; option (b) is the smallest fix to restore the previously-green state. (C1, C2)
2. Delete or finish the orphaned refactor leftovers `src/Adapter/Context.php`, `src/Adapter/Schema.php`, `src/Adapter/Contract/ContextAdapterInterface.php`, `src/Adapter/Contract/SchemaAdapterInterface.php` — they are unreferenced and broken. (C1)
3. Re-run and pass: `vendor/bin/codecept run Unit`, `vendor/bin/phpstan analyse`, `vendor/bin/php-cs-fixer fix`. (C2, C3, C4)

**P1 — Fix the user-facing surface:**
4. Repair `examples/resolve.php` and `examples/all.php`: call `new ConfigurationBuilder()` (default factory) and `addReader(...)` for every source type used; remove dead `EnvReader`/`EnvSource` imports. Verify both run end-to-end. (C5)
5. Rewrite `README.md` "Example" to use the real `ConfigurationBuilder` API, update the "Project Structure" to the actual directories, and correct the status badge/section to reflect that Phase 1 is implemented (with the caveat that the gate is being restored). Remove/relabel unimplemented adapters and formats. (D1)

**P2 — Honesty & consistency:**
6. Add a `CHANGELOG.md` entry acknowledging the mid-refactor regression; correct the test count and the garbled `test-code\` line; remove claims of a green gate until P0 is done. (D2)
7. Reconcile `docs/EXECUTION.md` references (local vs. repo-root). (D3)
8. Declare a single PHP target in `composer.json` `require.php` and align README/plan wording; confirm `array_any` (8.5) vs. Lazy Objects (8.4) intent. (D4)

**P3 — Design hardening (post-green):**
9. Extract document construction out of `ConfigurationResolver::resolveResources()` into a `DocumentFactory`/reader-loader chain (REFACTORING_PLAN §1).
10. Depend on `ConfigurationPipelineInterface` in the resolver; throw domain `ReadException` from `CompositeReader`.
11. Add `build()` fail-fast validation (non-empty sources/loaders) and an `infection.json` + coverage gate.

---

## 9. Appendix — Commands & Observed Output

| Check | Command | Result at HEAD |
|---|---|---|
| Autoload integrity (custom script requiring all `src/*.php`) | `php check.php` | **4 load failures** (Context/Schema adapter files) |
| Static analysis | `vendor/bin/phpstan analyse` | **2 errors**, aborts with *Internal error: Interface … not found* |
| Unit tests | `vendor/bin/codecept run Unit` | **Fatal**: `Interface Contract\ValueObject\ConfigurationLoaderInterface not found` (boot abort) |
| Coding standard | `vendor/bin/php-cs-fixer fix --dry-run` | **44 of 97 files** need fixing |
| Example: env | `php examples/env.php` | ✅ runs, prints `app.key` |
| Example: resolve | `php examples/resolve.php` | ❌ `TypeError` on `ConfigurationBuilder` ctor |
| Example: all | `php examples/all.php` | ❌ `TypeError` on `ConfigurationBuilder` ctor |

**Files most responsible for the broken state**
- `src/Adapter/Contract/ContextAdapterInterface.php`
- `src/Adapter/Contract/SchemaAdapterInterface.php`
- `src/Adapter/Context.php`
- `src/Adapter/Schema.php`
- `tests/Unit/Core/LoaderFactoryTest.php` (+ 4 other test files importing moved interfaces)
- `examples/resolve.php`, `examples/all.php`

**Bottom line:** The design is sound and worth continuing; the implementation is currently a casualty of an unfinished refactor. Restoring the green gate is a small, well-bounded effort (P0) and should precede any further feature work.
