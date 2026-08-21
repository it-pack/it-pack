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
   - `src/Execution/` (`../../docs/EXECUTION.md` model, framework-free):
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
   - `src/Adapter/NativeConfigurationSchema` — wraps `nette/schema`; translates vendor
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
   - 61 unit tests / 119 assertions, all green (`vendor/bin/codecept run Unit`).
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
   - Final gate green at the time of writing: phpstan level 9 (0 errors) + cs-fixer (0) + Codeception (61 tests).
  - `ROADMAP.md` Phase 0 and Phase 1 marked complete (see below).

### Regression / mid-refactor

The Step 8 "Final gate green" claim above was unqualified: an incomplete
interface-reorg left `HEAD` non-functional (broken imports, PHPStan aborting,
Codeception unable to boot — see `docs/REVIEW.md`). Phase 0.5 stabilisation
(`docs/ROADMAP.md` T1–T6) restored the gate:

- Reconciled the contract namespace split and fixed the rename aftermath
  (missing imports, illegal short ternary, untyped iterables, contravariant
  `list<>` vs `array<>`).
- Removed dead adapter leftovers (`Adapter\Context`, `ContextAdapterInterface`,
  `SchemaAdapterInterface`); retained `Adapter\Schema` (live dependency of
  `NativeConfigurationSchema`).
- Fixed the unit-test imports; suite is **61 tests / 119 assertions** green.
- `php-cs-fixer` clean across all files; PHPStan level 9 **0 errors**.
- Repaired `examples/resolve.php` / `examples/all.php` (run end-to-end, print the
  merged immutable snapshot).
- Declared `php` `^8.5` in `composer.json` (the code uses `array_any()`).

### Phase 2 — Additional File Formats

New loaders behind `ConfigurationLoaderInterface` (ADR-0006 registry), each
translating engine failures into the domain `ParseException` and never importing
vendor types into Core:

- `src/Adapter/YamlLoader` — YAML via `ext-yaml`.
- `src/Adapter/TomlLoader` — TOML via `internal/toml`.
- `src/Adapter/IniLoader` — INI via `parse_ini_string` (typed scanner).
- `src/Adapter/XmlLoader` — XML via `ext-simplexml` (attributes under
  `@attributes`; repeated siblings become lists).
- `src/Adapter/FrontMatterLoader` — Markdown Front Matter via `webuni/front-matter`
  (metadata becomes the document body; remaining content under `content`).
- `src/Adapter/ConfigurationLoaders` — `default()` bundles every built-in loader so
  consumers can opt in via `ConfigurationBuilder::addLoaders(...)`.
- `src/Adapter/FileSource` — default extension map now discovers `yaml`/`yml`,
  `toml`, `ini`, `xml`, `md`/`markdown` and `frontmatter`.
- `src/Core/ConfigurationBuilder::addLoaders(iterable)` — ergonomic bulk loader add.

Unit suite extended to **80 tests / 156 assertions** (new loader tests +
`ConfigurationLoadersTest` end-to-end multi-format resolve). PHPStan level 9 and
`php-cs-fixer` remain green.

NEON and CSV/TSV are explicitly **deferred**: NEON needs the `nette/neon` package
(not installed) and CSV/TSV need new `Format` enum cases.

### Phase 3 — Schema Integrations

First-class pipeline processors (behind `ConfigurationProcessorInterface`):

- `src/Core/DefaultsProcessor` — fills missing flat dotted keys only (carried over
  from Phase 1; now a first-class, tested processor).
- `src/Core/CastingProcessor` — coerces node values to `int` / `float` / `bool` /
  `string` per a dotted-key → type map, preserving source/priority/metadata.
  Cast failures raise the new `src/Exception/CastException`.
- `src/Exception/CastException` — domain exception for failed coercion.

Added tests `CastingProcessorTest` and `ConfigurationPipelineProcessorsTest`
(defaults + casting compose in the pipeline). Suite green: **86 tests / 167
assertions**, PHPStan level 9 **0 errors**, `php-cs-fixer` **0 files to fix**.

The custom schema-providers port (`ConfigurationSchemaInterface`) remains optional
and is not part of this pass.

### Phase 4 — Resolution Pipeline Processors

Discrete processors implementing the staged pipeline (`ConfigurationProcessorInterface`):

- `src/Contract/DocumentNormalizerInterface` + `src/Core/DocumentNormalizer` —
  extracted `flatten()` out of `ConfigurationPipeline` (ADR-0010, SRP split);
  `run()` now normalises → `graph->put` → processors.
- `src/Core/IncludeProcessor` — `@include <path>` directive imports another file
  (format from extension) and namespaces its nodes under the directive's parent
  key; the directive node is dropped (single-pass).
- `src/Core/VariableProcessor` — resolves `${dotted.key}` placeholders inside string
  values from the graph; unknown references left verbatim.
- `src/Core/ValidationProcessor` — wraps a `ConfigurationSchemaInterface` and raises
  `ValidationException` on failure, making validation a pipeline stage.
- Merge (priority) already lived in `ConfigurationGraph`; Casting/Defaults shipped
  in Phase 3.

Tests added: `DocumentNormalizerTest`, `VariableProcessorTest`, `ValidationProcessorTest`,
`IncludeProcessorTest`, and `ConfigurationPipelineStagesTest` (Include → Variable → Cast →
Defaults compose end-to-end). Suite green: **97 tests / 182 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**. ADR-0010 status advanced Proposed → Accepted.

### Phase 5 — Additional Adapters (Dotenv)

Delivered the offline-feasible adapter from the Phase 5 list, behind the existing
source/reader/loader ports (vendor exceptions translated to domain `ParseException`):

- `src/ValueObject/Format.php` — new `Format::DOTENV` enumeration case.
- `src/Adapter/DotenvLoader` — parses raw `.env` content through
  `Dotenv::parse()` (the host environment is never mutated); `InvalidFileException`
  is rethrown as `Exception\ParseException`.
- `src/Adapter/DotenvSource` — yields a single `.env` file as a `DOTENV` resource
  (yields nothing when the file is absent); reuses `FileReader` + the loader.
- `src/Adapter/FileSource` now maps the `.env` extension → `Format::DOTENV`, and
  `src/Adapter/ConfigurationLoaders::default()` bundles `DotenvLoader`, so `.env`
  files are discovered inside a directory scan automatically.
- `examples/dotenv.php` — end-to-end example (including `${VAR}` interpolation).

Tests: `DotenvLoaderTest`, `DotenvSourceTest`, and a `.env` case added to
`ConfigurationLoadersTest` (default-set coverage + multi-format resolution).
Suite green: **103 tests / 196 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

**Deferred:** Aura.Di, Vault, Redis, HTTP, S3/cloud secret stores — cloud/SDK-backed
sources that cannot run against the offline unit suite; Aura.Di is a DI container
slated for a dedicated `configure-it-di` companion package (see ROADMAP Phase 5).

### Phase 6 — Graph & Provenance

Delivered provenance tracking, diagnostics and graph diffing (Roadmap Phase 6),
all behind the existing node/graph/execution ports and unit-tested offline:

- `src/ValueObject/Provenance.php` (+ `src/Contract/ValueObject/ProvenanceInterface.php`)
  — immutable per-node provenance: `resourceUri`, `format`, `adapter`, `priority`,
  `sourceKind`, `processorTrail`, `overridden`/`overriddenSource`, `validatedBy`, `at`.
- `src/Core/ConfigurationNode.php` — carries `ProvenanceInterface` via `provenance()`;
  `withValue()`/`withProvenance()` preserve provenance through processor rewrites.
- `src/Core/DocumentNormalizer.php` — mints load-time provenance (real `Format` +
  loader `adapter` + `sourceKind`); `ConfigurationGraph::put` flags overrides.
- `src/Contract/ConfigurationDocumentInterface.php` / `ConfigurationDocument.php` /
  `ConfigurationResolver.php` — the resolver records the concrete loader class as the
  document `adapter` so provenance can attribute each node to its loader.
- `src/Contract/ProvenanceAwareProcessorInterface.php` + built-in processors
  (`Variable`, `Casting`, `Include`, `Defaults`, `Validation`) implement `stageName()`;
  `ConfigurationPipeline` records each processor in the touched node's trail, and
  `ValidationProcessor` records `validatedBy`.
- `src/Core/ConfigurationDiagnostics.php` (+ `ValueObject\DiagnosticsReport`) — overrides,
  nodes-by-source/format, unresolved `${...}` placeholders; emits `Observation`s
  through `ResolutionReporter` (reuses the Execution spine).
- `src/Core/ConfigurationDiffer.php` (+ `ValueObject\ConfigurationDiff`) — added/removed/
  changed keys with both sides' provenance.
- `src/Core/ConfigurationSnapshot.php` / interface — `provenance(string $path)` exposes
  per-key provenance to consumers.
- `examples/provenance.php` — end-to-end example.

Tests: `ProvenanceTest`, `ConfigurationGraphProvenanceTest`,
`ConfigurationPipelineProvenanceTest`, `ConfigurationDiagnosticsTest`,
`ConfigurationDifferTest`, `ConfigurationSnapshotProvenanceTest`. Suite green:
**121 tests / 258 assertions**, PHPStan level 9 **0 errors**, `php-cs-fixer`
**0 files to fix**.

**Deferred:** semantic-graph visualisation, RDF export and the full "Edge Model"
remain Phase 7+ tooling; provenance is captured and serialisable so they build on
top without Core changes.

### Phase 7 — Developer Experience

Delivered the Roadmap Phase 7 developer-experience tooling, all offline and
unit-tested behind the existing reader/loader/normalizer/diagnostics/diff ports:

- `src/CLI/GraphFromFile.php` — builds a `ConfigurationGraph` from a single file
  through the public reader/loader/normalizer ports (no builder needed).
- `src/CLI/ConfigurationInspector.php` — `explain(key)` provenance block and a flat
  node table; powers the `explain` command and any IDE/debug tooling.
- `src/CLI/GraphVisualizer.php` — `toDot()` (Graphviz source) and `toAscii()`
  (nested tree) graph rendering.
- `src/CLI/CommandInterface.php`, `ExplainCommand`, `DiffCommand`, `GraphCommand`,
  `HelpCommand`, `ConfigurationCli` — a framework-free CLI dispatcher; `bin/configure-it`
  exposes `explain`, `diff`, `graph`, `help`.
- `src/CLI/Output.php` — a stream `Output` sink so commands are unit-testable
  without spawning a subprocess.

Tests: `ConfigurationInspectorTest`, `GraphVisualizerTest`, `ConfigurationCliTest`
(dispatch + every subcommand via an in-memory `Output`). Suite green:
**134 tests / 295 assertions**, PHPStan level 9 **0 errors**, `php-cs-fixer`
**0 files to fix**.

**Deferred:** HTML/PlantUML exporters, interactive TUI, IDE plugins and Composer
script wiring — incremental, no Core changes now that provenance/diagnostics/diff
are exposed.

### Phase 8 — Enterprise Sources

Delivered the Roadmap Phase 8 enterprise configuration sources, all offline and
unit-tested behind the existing reader/loader/normalizer ports:

- `src/Adapter/Enterprise/ConfigMapSource.php`, `KubernetesSecretSource.php`,
  `DockerSecretsSource.php` — Kubernetes ConfigMap / Secret and Docker secrets
  (volume mounts); read by `VolumeReader` into a flat `KEY=VALUE` body for `EnvLoader`.
- `src/Adapter/Enterprise/AwsParameterStoreSource.php`, `AzureAppConfigSource.php`,
  `GcpSecretManagerSource.php` — cloud parameter stores; fetched through
  `RemoteJsonReader` + an injectable `RemoteKeyValueClient` adapter
  (`Client/AwsSsmClient`, `Client/AzureAppConfigClient`, `Client/GcpSecretManagerClient`).
- `src/Adapter/Enterprise/Contract/` (`HttpClient`, `RemoteKeyValueClient`) plus
  `Http/CurlHttpClient` (prod), `Http/FakeHttpClient` and `Client/FakeRemoteKeyValueClient`
  (tests), and `TreeBuilder` (nests flat vendor keys into a config tree).
- `Core/DocumentNormalizer.php`: enterprise `sourceKind` (`configmap`, `k8s-secret`,
  `docker-secret`, `aws-ssm`, `azure-appconfig`, `gcp-secretmanager`) derived from the
  resource URI scheme, so provenance/diagnostics attribute nodes correctly.
- `Adapter/EnvReader.php`: `supports()` scoped to the `env://` scheme so the composite
  reader routes enterprise `ENV` resources to `VolumeReader`.

Tests: `VolumeSourceTest`, `RemoteSourceTest` (end-to-end via fakes + each cloud
client's nesting logic). Suite green: **144 tests / 320 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**.

**Deferred:** Aura.Di DI adapter, Vault/Consul/Redis adapters, and live integration
tests against real cloud endpoints — incremental, no Core changes now that the
source/reader/client ports exist.

### Phase 9 — Observability

Delivered native OpenTelemetry instrumentation built on the `Execution` reporter
spine (`docs/EXECUTION.md`), as a `Bridge` with no Core dependency and no required
OpenTelemetry SDK:

- `src/Bridge/OpenTelemetry/SemanticConventions.php` — OpenTelemetry-aligned span,
  lifecycle-event, metric and attribute names; maps each `Occurrence` type to a span
  + lifecycle event.
- `src/Bridge/OpenTelemetry/OpenTelemetryReporter.php` — decorates
  `ResolutionReporterInterface`, emitting one trace per resolution run, a child span
  + CloudEvents-style lifecycle event per `Occurrence`, a `config.observation` event
  per `Observation`, and metrics (`config.resolution.duration`, `config.occurrences`,
  `config.nodes`, `config.overrides`, `config.unresolved`). Optionally enriched with an
  `ExecutionContext`.
- `src/Bridge/OpenTelemetry/Contract/TelemetrySinkInterface.php` (port) plus
  `InMemoryTelemetrySink.php` (recording/no-op, tests/default) and `OtelTelemetrySink.php`
  — the real bridge delegating to a consumer-supplied
  `Contract/OpenTelemetryBridgeInterface` (the only OpenTelemetry-SDK-aware point).
- `Core/ConfigurationBuilder.php`: `withTelemetry($sink)` and `withExecutionContext($ctx)`
  wrap the reporter in `OpenTelemetryReporter` automatically.

Tests: `OpenTelemetryReporterTest`, `OtelTelemetrySinkTest` (fake
`OpenTelemetryBridgeInterface`; end-to-end trace/span/event/metric emission incl.
validation span, execution-context attributes, diagnostics observation events). Suite
green: **149 tests / 345 assertions**, PHPStan level 9 **0 errors**, `php-cs-fixer`
**0 files to fix**.

**Deferred:** a reference `configure-it-opentelemetry` adapter implementing
`OpenTelemetryBridgeInterface` against `open-telemetry/opentelemetry-api`, and
live export-to-collector integration tests — incremental, no Core changes now that the
`TelemetrySinkInterface` port and `SemanticConventions` exist.

### Additional Features — M1: Typed Consumption ✅

First milestone of `docs/ADDITIONAL_FEATURES_PLAN.md` / `docs/ADDITIONAL_FEATURES_ROADMAP.md`.
Eliminates `mixed` at the consumption edge; misconfigured types fail fast instead of
flowing into application code. Fully additive — behaviour is unchanged when no schema
(and therefore no scalar-type map) is supplied.

- `src/Exception/TypeException.php` — domain exception for missing keys and
  type mismatches at read time (counterpart to `CastException`).
- `src/Core/ScalarCaster.php` — shared coercion rules (numeric strings → int/float,
  truthy strings → bool, scalar → string); mirrors `CastingProcessor` leniency so
  environment-sourced values read cleanly through typed accessors.
- `src/Contract/ConfigurationSnapshotInterface` — new typed accessors `int()`,
  `float()`, `bool()`, `string()`, `list()`, `stringList()` and `exists()`, all
  throwing `TypeException` on a missing required key or incompatible value.
- `src/Core/ConfigurationSnapshot.php` — implements the typed accessors (opt-in
  coercion, default fallback).
- `src/Core/TypedSnapshot.php` — decorator that performs a single freeze-time
  validation pass over a declared scalar-type map (`path => int|float|bool|string|
  list|map`); mis-typed leaves raise `TypeException` before any consumer reads.
- `src/Contract/DeclaresScalarTypesInterface.php` — optional schema capability that
  enumerates expected scalar types per key.
- `src/Adapter/NativeConfigurationSchema.php` — implements
  `DeclaresScalarTypesInterface` by introspecting the Nette definition (reflection
  over `Structure`/`Type` elements); the resolver mints a `TypedSnapshot` whenever a
  schema declares scalar types, so schema-backed resolutions are automatically
  type-checked at freeze.

Tests: `ConfigurationSnapshotTypedTest`, `TypedSnapshotTest`,
`NativeConfigurationSchemaTypedTest` (walker + end-to-end resolver minting).
Suite green: **165 tests / 380 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.


### Additional Features — M1.E2.3: Typed DTO Codegen ✅

Completes M1 (E2.3) of `docs/ADDITIONAL_FEATURES_PLAN.md` / `docs/ADDITIONAL_FEATURES_ROADMAP.md`:
generate strongly-typed, readonly DTOs directly from a Nette Schema definition so
consumers can hydrate a fully-typed `AppConfig` in one call instead of reading the
snapshot through `int()`/`string()` accessors.

- `src/Codegen/SchemaModelBuilder.php` — walks a Nette `Expect` definition (via
  reflection over `Structure`/`Type` internals) into a set of DTO models, capturing
  nested structures, list/map element types, and scalar read methods.
- `src/Codegen/PhpRenderer.php` — emits one self-contained PHP file: each DTO is
  `readonly`, `implements GeneratedConfigDtoInterface`, exposes `fromSnapshot()`
  (root) / `fromSnapshotAt()` (reads every property relative to a dotted base path,
  so nested and list/map-of-structure elements reuse the same reader), and `toArray()`.
  Collection properties are declared `array` with a `@var array<...>` PHPDoc (PHP has
  no runtime generic `array<T>` syntax).
- `src/Codegen/ConfigCodegen.php` — orchestrates build + render.
- `src/Codegen/ConfigDtoHydrator.php` — adapter that invokes the generated
  `fromSnapshot()` factory (T1.3.3).
- `src/Codegen/GeneratedConfigDtoInterface.php` — marker so hydrators return a
  statically-known type.
- `examples/typed-config.php` — generates an `AppConfig` DTO, hydrates it from a
  snapshot, and prints typed getters (T1.3.4).

Tests: `ConfigCodegenTest` (class generation, end-to-end hydration of scalars,
nested structures, lists/maps of scalars, lists/maps of structures, and hydrator
error handling). Suite green: **168 tests / 389 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**.


### Additional Features — M2.E3.1: Layer port + builder ✅

Completes M2 (E3.1) of `docs/ADDITIONAL_FEATURES_PLAN.md` / `docs/ADDITIONAL_FEATURES_ROADMAP.md`:
explicit, explainable layering that replaces implicit "last source wins by
discovery order" with declarative `base → environment → local → override`
composition, including env/tenant/profile gating via a match predicate.

- `src/Contract/ConfigurationLayerInterface.php` — the layer port: `sources()`,
  `precedence(): int` (higher overrides lower), and `match(ExecutionContextInterface): bool`
  for conditional activation.
- `src/Core/ConfigurationLayer.php` — default impl holding a source list, a
  numeric precedence, and an optional `match` predicate (`null` = always match).
- `src/Core/ConfigurationBuilder.php` — new `addLayer()`; at `build()` time the
  registered layers are filtered by `match()` against the execution context, then
  merged in ascending precedence order (so higher precedence wins). With no layers
  registered the flat `addSource()` list is used unchanged — fully backward
  compatible.

Tests: `LayerOrderTest` (precedence ordering regardless of insertion order,
env-gated layer skipped when `match` is false, env-gated layer applied when `match`
is true, and backward compatibility with no layers). Suite green: **172 tests /
395 assertions**, PHPStan level 9 **0 errors**, `php-cs-fixer` **0 files to fix**.


### Additional Features — M2.E3.2: Conditional includes ✅

Completes M2 (E3.2) of `docs/ADDITIONAL_FEATURES_PLAN.md` / `docs/ADDITIONAL_FEATURES_ROADMAP.md`:
the `@include` directive gains an optional `@if <condition>` suffix so imports can
be gated on the execution context (env / tenant / profile).

- `src/Core/IncludeProcessor.php` — directive now parsed as `@include <path> [@if
  <condition>]`. The optional condition grammar is `env=<v> | tenant=<v> |
  profile=<v>`, evaluated via `ExecutionContextInterface::environment()` /
  `attribute()`; an unmet condition drops the include, a malformed one raises
  `ResolutionException`. No suffix preserves today's behaviour.
- `src/Contract/ExecutionContextAwareProcessorInterface.php` — new marker so the
  builder can bind the active `ExecutionContextInterface` into context-aware
  processors at `build()` time (keeps processors immutable/stateless).
- `src/Core/ConfigurationBuilder.php` — at `build()`, any processor implementing
  `ExecutionContextAwareProcessorInterface` is replaced by a context-bound copy
  when an execution context is configured.

Tests: `ConditionalIncludeTest` (included/excluded per context for env/tenant/
profile, invalid condition throws, multi-pass nested includes via double-add, and
a builder integration test asserting the execution context is injected into the
include processor). Suite green: **180 tests / 412 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**.

### Notes

- PHPStan level 9 reports only `shipmonk.deadMethod` findings at this stage
  (contracts/value objects not yet consumed). These clear once Core (Step 3)
  implements `ConfigurationResolver`/`ConfigurationGraph` and the adapters wire in.
- Coding standard (`php-cs-fixer`, PSR-12 + PHP84 migration) is clean across all
  new files.
