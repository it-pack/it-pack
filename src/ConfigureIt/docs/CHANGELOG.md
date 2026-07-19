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

### Notes

- PHPStan level 9 reports only `shipmonk.deadMethod` findings at this stage
  (contracts/value objects not yet consumed). These clear once Core (Step 3)
  implements `ConfigurationResolver`/`ConfigurationGraph` and the adapters wire in.
- Coding standard (`php-cs-fixer`, PSR-12 + PHP84 migration) is clean across all
  new files.
