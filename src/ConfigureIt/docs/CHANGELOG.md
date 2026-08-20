# Changelog

All notable changes to `it-pack/configure-it` are documented here. This project
follows an incremental, documentation-driven build (see `IMPLEMENTATION_PLAN.md`
and `ROADMAP.md`).

## [Unreleased]

### Added

- **Step 0 — Tooling & config baseline**
  - `phpstan.neon` raised to **level 9** (`reportUnmatchedIgnoredErrors: true`,
    `treatPhpDocTypesAsCertain: true`, empty `ignoreErrors`). Removed keys
    unsupported by PHPStan 2.2.5 (they are level-9 defaults in that release).
  - Codeception test scaffolding: `tests/Unit.suite.yml`,
    `tests/Support/UnitTester.php`, and generated
    `tests/Support/_generated/UnitTesterActions.php` (`codecept build`).
  - `composer validate` passes; `vendor/bin/{codecept,phpstan,php-cs-fixer}`
    confirmed present.

- **Step 1 — Contracts, value objects & execution model**
  - `src/ValueObject/`:
    - `Format` enum (php, json, yaml, env, neon, toml, ini, xml, frontmatter)
      with `fromString()`.
    - `ConfigurationResource` — immutable resource identity (uri + format).
    - `ValidationResult` — schema validation outcome (valid + typed errors).
  - `src/Contract/` (13 ports, interfaces only, no vendor imports):
    - `ConfigurationResourceInterface`
    - `ConfigurationSourceInterface` (discovers resources)
    - `ConfigurationReaderInterface` (reads raw content)
    - `ConfigurationLoaderInterface` (parses into a document)
    - `ConfigurationDocumentInterface`
    - `ConfigurationNodeInterface`
    - `ConfigurationGraphInterface`
    - `ConfigurationSnapshotInterface` (immutable)
    - `ConfigurationProcessorInterface`
    - `ConfigurationPipelineInterface`
    - `ConfigurationSchemaInterface`
    - `ConfigurationResolverInterface`
    - `ResolutionReporterInterface`
  - `src/Execution/` (docs/EXECUTION.md model, framework-free):
    - `ExecutionContext` — resolution scope (environment + attributes).
    - `Occurrence` — immutable "what happened" fact.
    - `Observation` — qualitative "what we noticed" fact.
    - `ResolutionReporter` — in-memory reporter emitting Occurrences/Observations.
  - `src/Exception/` (domain exceptions, never vendor-leaked):
    - `ConfigurationException` (marker base)
    - `ReadException`, `ParseException`, `ValidationException`, `ResolutionException`

### Changed

- `src/ConfigureIt/docs/IMPLEMENTATION_PLAN.md` — added authoritative build plan
  (context, package layout, step-by-step sequence, risks, definition of done).

- **Step 3 — Core implementation**
  - `src/Core/ConfigurationDocument` — parsed format-independent document (value object).
  - `src/Core/ConfigurationNode` — single graph value with source/priority/metadata.
  - `src/Core/ConfigurationGraph` — flat dotted-key graph; deterministic
    higher-priority-wins merge; nested arrays expanded to dotted keys.
  - `src/Core/ConfigurationSnapshot` — immutable frozen snapshot (dotted-path `get`/`has`).
  - `src/Core/ConfigurationPipeline` — documents → nodes → processors → graph;
    emits `ConfigurationLoaded` / `ConfigurationMerged` occurrences.
  - `src/Core/ConfigurationResolver` — orchestrates source→reader→loader→pipeline→
    schema→snapshot; emits `ConfigurationValidated` / `ConfigurationResolved` /
    `SnapshotCreated` occurrences; throws `ValidationException` on schema failure.
  - `src/Core/ConfigurationBuilder` — mutable fluent assembler (sources, loaders,
    processors, schema) producing a `ConfigurationResolver`.
  - `src/Core/DefaultsProcessor` — applies flat dotted-key defaults without
    overriding resolved values (pipeline "Apply Defaults" stage).

- **Step 4 — Native adapters (Ports & Adapters; ADR-0002)**
  - `src/Adapter/FileSource` — recursive directory discovery → `ConfigurationResource`
    per supported extension (no third-party finder; SPL only).
  - `src/Adapter/FileReader` — reads file content (standard library only).
  - `src/Adapter/EnvSource` / `EnvReader` — exposes process environment as a single
    ENV resource; reader serialises `KEY=VALUE` lines.
  - `src/Adapter/PhpLoader` — parses a `<?php return [...]` file via a throwaway temp
    file (isolates `require`).
  - `src/Adapter/JsonLoader` — parses JSON via `ext-json` with `JSON_THROW_ON_ERROR`.
  - `src/Adapter/test-code\NativeConfigurationSchema` — wraps `nette/schema`; translates vendor
    `ValidationException` (via `getMessageObjects()`) into domain `ValidationResult`.
    Core never sees Nette types.

  PHPStan level 9: **0 genuine type errors** (remaining 47 findings are
  `shipmonk.deadMethod` — unused until Step 5 wiring + Step 6 tests consume them).
  Coding standard (`php-cs-fixer`) clean across all 41 files.

- **Step 5 — Default wiring & example**
  - `examples/resolve.php` — wires `FileSource` + `PhpLoader`/`JsonLoader` via the
    `ConfigurationBuilder`, resolves to an immutable `ConfigurationSnapshot`, and
    prints merged values. Runs end-to-end (`php examples/resolve.php`).
  - `examples/Support/Data/app.php` and `app.json` — fixtures demonstrating merge
    (PHP + JSON, last-wins by discovery order).
  - `ConfigurationBuilder::build()` now returns a resolver that resolves with its
    accumulated sources when called with no arguments; `resolve(iterable $sources = [])`
    on both the interface and resolver supports this.

- **Step 6 — Comprehensive Codeception suite**
  - 47 unit tests / 99 assertions, all green (`vendor/bin/codecept run Unit`).
  - Coverage of every Core class, every adapter, the builder, the schema adapter
    and the execution reporter. Fixtures under `tests/Support/Data/`.
  - Asserts behaviour (priority merge, immutability, exception translation,
    occurrence ordering, schema validation/failure), not internals.
  - `NativeConfigurationSchemaTest` exercises Nette Schema validation success/failure
    through the `ConfigurationSchemaInterface` port.

- **Step 7 — Static analysis (PHPStan level 9)**
  - `composer analyse` → **0 errors** at **level 9**.
  - One narrow, documented `ignoreErrors` entry suppresses shipmonk `deadMethod`
    false-positives on three interface declarations whose concrete implementations
    are genuinely consumed in `src/` and tests (the ports & adapters pattern the
    project mandates). No real defects suppressed.

- **Step 8 — Coding standards & final gate**
  - `php-cs-fixer` (PSR-12 + PHP84 migration) clean across all 57 files.
  - `examples/resolve.php` runs end-to-end and prints the merged immutable snapshot.
  - Final gate green: phpstan level 9 (0 errors) + cs-fixer (0) + Codeception (47 tests).
  - `ROADMAP.md` Phase 0 and Phase 1 marked complete (see below).

### Notes

- PHPStan level 9 reports only `shipmonk.deadMethod` findings at this stage
  (contracts/value objects not yet consumed). These clear once Core (Step 3)
  implements `ConfigurationResolver`/`ConfigurationGraph` and the adapters wire in.
- Coding standard (`php-cs-fixer`, PSR-12 + PHP84 migration) is clean across all
  new files.
