# Changelog

All notable changes to `it-pack/configure-it` are documented here. This project
follows an incremental, documentation-driven build (see `IMPLEMENTATION_PLAN.md`
and `ROADMAP.md`).

## [Unreleased]

### Added

- **Milestone 2 — Mutation testing wired onto Codeception (REVIEW NV19)**
  - `infection/codeception-adapter` (dev, v0.4.7) — Infection now drives
    `codecept run Unit` through the published adapter, mirroring the sibling `RunIt`
    setup. Codeception remains the only test framework; no test files were touched.
  - `infection.json5` — `testFramework: codeception`, suite passed via
    `testFrameworkExtraArgs: "Unit"`, `@default` mutators, logs in `var/infection.*`;
    `minMsi` / `minCoveredMsi` provisional at 0 until a CI baseline exists (locally the
    adapter runs the full Unit suite per mutant under this pcov setup — same documented
    constraint as on RunIt).
  - Composer scripts `mutation` and `mutation:changed`
    (`--only-covering-test-cases --no-interaction`; the latter adds
     `--git-diff-lines --ignore-msi-with-no-mutations` for cheap per-PR feedback).

- **Milestone 3 — Coverage ≥ 95 % + suite split + conformance seeds (REVIEW NV1, NV11)**
  - Coverage is now an enforced gate. `scripts/coverage_check.php` parses
    `tests/_output/coverage.xml` (from `composer coverage`) and fails when covered
    statements **or** elements fall below 95 % (methods reported informationally);
    wired as `composer coverage:check`.
  - Composer scripts: `test:integration` → `codecept run Integration`; `coverage`
    now runs `Unit,Integration`; `coverage:check` runs the threshold script.
  - **Suite split (Unit = one SUT + fakes; Integration = real collaborators):** added
    `tests/Integration.suite.yml` + `tests/Support/IntegrationTester.php`; moved the
    stack-level tests `ConfigurationResolverTest`, `ConfigurationLoadersTest`,
    `Wiring/DefaultConfigurationWiringTest` and the OTel reporter test (real sink) out
    of Unit into Integration.
  - **Conformance verifiers (RunIt-style, parameterized over implementations):**
    `tests/Integration/Conformance/CommandRunnerVerifierTest` and
    `DecrypterVerifierTest` exercise the `CommandRunnerInterface` / `DecrypterInterface`
    contracts; binary-dependent adapters (`age`, `sops`) are skipped when their engine
    is absent.
  - **New unit tests close the zero-covered files and lift the worst partials:**
    `FileSystemDocumentCache`, `MessageFactory`/`Message`, `FeatureFlagSchema`,
    `CreatableBehavior`, `ProcessorStage`, `ConfigurationDiff`, `DiagnosticsReport`,
    `TypedSnapshot`, `Provenance`, `FlagDefinition`, `SemanticConventions`, `Schema`,
    `Processor`, `ScalarCaster`, `RedactingRenderer`, `CastingProcessor`, `FsWatcher`,
    `PollingWatcher`, `ReloadableConfiguration`, `ConfigurationDriftDetector`,
    `IncludeProcessor`, `NativeConfigurationSchema`, `ConfigurationCompiler`,
    `ConsulClient`, `EtcdClient`, `CurlHttpClient`, `VolumeReader`, `HttpReader`,
    `RemoteJsonReader`, the loaders (Json/Toml/Yaml/Xml/FrontMatter/Php/Env),
    `FileReader`, `CompositeReader`, `FileModificationProbe`,
    `ConfigurationLoaderFactory`, `ConfigDtoHydrator`, `ConfigurationDocumentFactory`,
    and the CLI commands `DiffCommand`/`GraphCommand`/`ExplainCommand`/`Output`/
    `TempFileFactory`/`SchemaModelBuilder`.
  - Coverage: **98.07 % statements / 97.35 % elements** (was 86.54 % / 84.44 %);
    **889 tests** (868 Unit + 21 Integration), PHPStan level 9 **0 errors**,
    `php-cs-fixer` **0 files to fix**.

- **Milestone 4 — House style mechanized & hygiene sweep (REVIEW NV12, NV14, NV15, NV18, NV21, NV22)**
  - **Conventions now enforced by cs-fixer** (previously hand-policed): `yoda_style`
    (`equal` + `identical`), `global_namespace_import` (classes **and** functions are
    imported, so the 71 previously-unimported global symbols — `\count`, `\sprintf`,
    `\is_string`, … — are now `use function` imports), and `ordered_class_elements`
    (traits → const → property → construct → public-static → public → protected →
    private). `composer cs:fix` auto-fixed the seven NV12 member-order deviations and the
    ~130 NV15 import sites; `composer cs:check` is clean with all three rules active.
  - **120-column budget enforced** via `phpcs.xml.dist` (`Generic.Files.LineLength`,
    limit 120, `src/` only) wired as `composer lint:length`; the ~41 long lines (NV14)
    were hand-wrapped and now report 0 violations.
  - **Resolver iterable edge (NV21):** `resolveResources()` materializes non-array
    iterables with `iterator_to_array($sources, false)` so an empty generator correctly
    falls back to default sources; covered by a dedicated empty-generator test.
  - **Cosmetics (NV22):** dropped the double blank line in `IncludeProcessor`;
    `DefaultConfigurationWiring::composeReporter()` opening brace moved to its own line;
    scratch-dir `0o777` in tests replaced with `0o700`.
  - **Dormant QA tools wired into composer gates:**
    - `composer architecture` → `vendor/bin/deptrac analyse` with `deptrac.yaml` encoding
      today's actual layer edges (Contract / ValueObject / Core / Execution / Cache /
      Adapter / Bridge / Codegen / CLI / Wiring) as a **baseline** — 0 violations; the map
      is intentionally permissive and is tightened to enforce architectural intent in
      Milestone 6.
    - `composer lint:magic` → `vendor/bin/phpmnd src` with a curated `--ignore-numbers`
      list (2, 100, 200, 300 — argument counts, rollout percent, HTTP status range);
      the run reports 0 magic numbers on `src/`.
  - **Upstream watch-item (NV18 residue):** the `pdepend`/`phpmd` PHP 8.5 parser crash
    remains an engine-lag issue in the QA base; complexity metrics will be re-introduced
    when a compatible release ships. Recorded here rather than worked around.

### Security

- **Milestone 1 — Secure transport & storage defaults (REVIEW NV2–NV6, NV17; ADR-0011)**
  - `src/Cache/{FileSystemCache,FileSystemDocumentCache}` — cache directories are created
    at most group-writable (`0o770`, was `0o777`) because artifacts embed resolved and
    decrypted configuration; artifact writes land atomically (temp file + `rename` with
    `finally` cleanup); `FileSystemCache` additionally validates keys against
    `[A-Za-z0-9._-]{1,128}` so a hostile key can never traverse out of the cache directory.
  - `src/Adapter/CommandRunner/NativeCommandRunner` — the argument **array** is passed
    straight to `proc_open` (no shell is invoked anymore, matching the docblock); stdin and
    stdout/stderr are drained concurrently via `stream_select`, removing the pipe-buffer
    deadlock for payloads beyond ~64 KiB; an injectable wall-clock deadline
    (`DEFAULT_TIMEOUT_SECONDS = 30`) terminates runaway processes; stderr in exception
    messages is truncated (`MAX_STDERR_BYTES = 2048`) to keep secret-bearing tool output
    out of logs.
  - `src/Adapter/Enterprise/Http/CurlHttpClient` — redirect following is deliberately
    disabled (authenticated headers must not be forwarded to redirect targets); timeouts
    became named injectable constants (`REQUEST_TIMEOUT_SECONDS = 10`,
    `CONNECT_TIMEOUT_SECONDS = 5`, plus `CURLOPT_CONNECTTIMEOUT`); option assembly was
    extracted into `buildOptions()` for network-free unit testing.
  - `src/Core/IncludeProcessor` — `@include` targets are resolved with `realpath()` and
    must stay inside the processor's base directory; `..` traversal and symlink escapes now
    raise `ResolutionException("… escapes the base directory.")`. The `@if`
    attribute vocabulary is de-duplicated into a class constant map.
  - `src/Adapter/Enterprise/Client/{ConsulClient,EtcdClient}` — TLS-capable URI schemes
    (`consul+tls://…` / `etcd+tls://…` → upstream `https://`); unknown schemes are rejected
    explicitly instead of silently defaulting to plaintext HTTP. Auth credentials remain a
    decorated-transport concern (see PORTS.md §6 recipe).

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

- **Milestone 2 — DIP purity in the composition root (R7 + R8)**
  - `src/Core/ConfigurationResolver` — constructor now depends on
    `ConfigurationPipelineInterface` instead of the concrete `ConfigurationPipeline` (R7), in
    line with AGENTS.md ("Core depends on Contracts, not concrete classes").
  - `src/Contract/Wiring/ConfigurationWiringInterface` — added `baseReporter()` and
    `baseExecutionContext()` (R8), plus `cachingResolver(...)` (optional R8 completeness) so the
    composition root owns all `Execution` / caching concretes.
  - `src/Wiring/DefaultConfigurationWiring` — implements the three new methods; it is now the
    only `new`-site for `ResolutionReporter`, `ExecutionContext`, `CachingResolver`, and
    `ConfigurationCompiler`.
  - `src/Core/ConfigurationBuilder` — no longer instantiates `Execution` concretes:
    `new ResolutionReporter()` → `wiring->baseReporter()` and `new ExecutionContext()` →
    `wiring->baseExecutionContext()`; the `CachingResolver` wrapper is delegated to
    `wiring->cachingResolver(...)`. The builder is now a pure collector of configuration.
  - Tests: `ConfigurationResolverTest::testAcceptsPipelineInterfaceImplementation` and
    `DefaultConfigurationWiringTest::testCachingResolverWrapsInnerResolverInCachingResolver`.

- **Milestone 3 — Hygiene & error visibility (R11, R12, R13)**
  - **R11** — `src/Core/CastingProcessor::cast()` — removed the stray duplicate `@return`
    docblock; the method now has a single `@param`/`@return` docblock.
  - **R12** — `src/Core/CachingResolver` — added an optional `?ResolutionReporterInterface
    $reporter` constructor param; a failing `cache->set()` now records a `Severity::Warning`
    `Observation` (`'Cache write failed: ' . $e->getMessage()`) instead of being silently
    swallowed, while still returning the live snapshot (best-effort semantics preserved).
    `ConfigurationWiringInterface::cachingResolver()` gained a `$reporter` parameter and the
    builder threads the composed reporter through.
  - **R13** — removed the remaining `else` blocks to honor the no-`else` guideline:
    `src/Adapter/FileModificationProbe::__invoke()` (ternary),
    `src/ValueObject/DiagnosticsReport::summary()` (early-branch + `if`), and
    `src/Cache/FileSystemCache::set()` (ternary on `instanceof TypedSnapshot`).
  - Tests: `tests/Unit/Core/CachingResolverTest` — a throwing cache still returns the live
    snapshot and records exactly one `warning` observation when a reporter is wired in (none
    when absent).

- **Milestone 4 — Optional DRY & extensibility (R17, R18)**
  - **R18 — extracted `src/Core/AbstractNodeRewritingProcessor`** (new `abstract readonly`
    base implementing `ConfigurationProcessorInterface` + `ProvenanceAwareProcessorInterface`). It
    owns the immutable-graph rewrite loop (`before()` hook → `new ConfigurationGraph()` →
    `foreach nodes → put(rewrite($node))`) and `stageName()` (via a `protected ProcessorStage
    $stage`). `CastingProcessor` and `ValidationProcessor` now `extend` it and implement only the
    node-level `rewrite()`; `ValidationProcessor` additionally overrides `before()` to run schema
    validation and throw `ValidationException` (preserving the exact pre-loop behavior). Both
    subclasses gained an optional `$stage` constructor parameter that defaults to their stage, so
    all existing positional call-sites are unchanged. Removes the duplicated clone/loop/put
    boilerplate.
  - **R17 — deferred (per the milestone's condition).** The plan adds `ScalarCasterInterface` /
    `RedactingRendererInterface` *only if external customization is required*; no such requirement
    exists here, and `ScalarCaster` is consumed by `ConfigurationSnapshot` / `TypedSnapshot` rather
    than `CastingProcessor` (the plan's note is stale). Both remain internal `final readonly` helpers;
    R17 is tracked as a mechanical follow-up if extension points are later wanted.

- **Milestone 5 — Remove the sole abstract class via composition (R19)**
  - `src/Adapter/Decryption/CommandDecryption` (new `final readonly` helper) — owns the shared
    command-decryption behavior extracted from the deleted `abstract CommandDecrypter`: the
    `CommandRunnerInterface` + `list<string> $command` state and a verbatim copy of its
    `decrypt()` body (ciphertext fed to the command on stdin, stdout returned with trailing
    `\r\n` trimmed, `\Throwable` translated into `DecryptionException`).
  - `src/Adapter/Decryption/{AgeDecrypter,SopsDecrypter}` — no longer extend the abstract base;
    each now `implements DecrypterInterface`, composes a `private readonly CommandDecryption`
    built with its identical command array, and delegates `decrypt()`. Public constructor
    signatures are unchanged. Inheritance is replaced by composition per `AGENTS.md`
    ("Composition over Inheritance"); `CommandDecrypter.php` is deleted.
  - Tests: new direct unit test `tests/Unit/Adapter/Decryption/CommandDecryptionTest` — locks
    the exact command array and stdin ciphertext passed to the runner, the trailing-newline trim,
    and the runner-failure → `DecryptionException` translation (message format included); this
    logic was previously only reachable through the subclasses.

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


### Additional Features — M2.E3.3: Layer attribution in provenance ✅

Completes M2 (E3.1 + E3.2 + E3.3) of `docs/ADDITIONAL_FEATURES_PLAN.md` /
`docs/ADDITIONAL_FEATURES_ROADMAP.md`: layered composition is now explainable —
the winning layer for every value is recorded in provenance and visible in the
inspector. With no layers registered, behaviour (and provenance) is unchanged.

- `src/ValueObject/Provenance.php` + `src/Contract/ValueObject/ProvenanceInterface.php` —
  new `layerId(): ?string` (null when not layered); carried through the immutable
  copies (`withProcessor` / `asOverridden` / `validated`) and `toArray()`.
- `src/Core/ConfigurationDocument.php` + interfaces — carry `layerId(): ?string`
  from source to normalizer.
- `src/Core/DocumentNormalizer.php` + `src/Contract/DocumentNormalizerInterface.php`
  — `normalize()` accepts an optional layer id and stamps it onto each node's
  `Provenance`. Included files (`@include`) inherit the layer id of the directive
  that pulled them in, so layer attribution stays accurate across includes.
- `src/Core/IncludeProcessor.php` — propagates the directive's layer id into the
  normalized included nodes.
- `src/Contract/LayerAwareSourceInterface.php` (new) + `src/Core/LayeredSource.php`
  (new) — the builder wraps each layer's sources in `LayeredSource` carrying the
  layer id; `src/Core/ConfigurationResolver.php` reads it while building documents.
- `src/Core/ConfigurationLayer.php` — layers now expose a stable `id()` (defaults to
  `layer-<precedence>` when unnamed).
- `src/CLI/ConfigurationInspector.php` — `explain` now shows the winning layer
  (a `layer` line per key and a `LAYER` column in the table).

Tests: `LayerProvenanceTest` (layer id stamped onto node provenance, no layer id
when no layers registered, winning layer visible in inspector) and `M2ExitTest`
(end-to-end: layer ordering + conditional includes + provenance compose; included
nodes inherit the directive's layer id; behaviour unchanged without layers).
Suite green: **187 tests / 434 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M3.E7.1: Compiled / cached artifacts ✅

Completes M3 (Epic E7.1) of `docs/ADDITIONAL_FEATURES_PLAN.md` /
`docs/ADDITIONAL_FEATURES_ROADMAP.md`: stable configuration now resolves once
and is served from an opcache-friendly compiled artifact, opt-in via the
builder. Behaviour is identical to a live resolution — same snapshot, same
failure modes — and any change to sources, schema, context or pipeline yields a
fresh resolve + rewrite.

- `src/Contract/ConfigurationCacheInterface.php` (new port) — `get(string): ?Snapshot`
  / `set(string, Snapshot)`; a miss (or corrupt/unreadable artifact) returns
  null so the caller falls back to live resolution.
- `src/Cache/FileSystemCache.php` (new adapter) — writes/reads a single PHP
  artifact (`return [...];`) under a caller-supplied directory. The payload
  carries the tree + provenance plus typed-snapshot metadata, so a
  schema-backed entry is reconstructed as a `TypedSnapshot` that still validates
  at freeze time. Reads `include` the file; writes are best-effort and never
  interrupt resolution.
- `src/Core/ConfigurationCompiler.php` (new) — computes a deterministic
  composite hash from discovered source resources (path + **content hash**, so
  any edit invalidates), the schema identity + declared scalar types, the
  execution context (environment + attributes), and the class of every
  processor / reader / loader. Equal inputs → equal key; any meaningful change →
  a new key.
- `src/Core/ConfigurationSnapshot.php` + `src/ValueObject/Provenance.php` —
  added `toArray()` / `fromArray()` so an immutable snapshot (and its provenance)
  can be serialized to and reconstructed from a cache artifact verbatim.
- `src/Core/ConfigurationSnapshotFactory.php` + interface — `buildFromArray()`
  rebuilds the immutable snapshot from the serialized form (T3.1.4 guard).
- `src/Core/TypedSnapshot.php` — exposes `innerSnapshot()` / `scalarTypes()` so
  the cache can serialize a typed snapshot and reconstruct the exact wrapper.
- `src/Core/CachingResolver.php` (new decorator) — wraps the resolver produced by
  the builder; on each `resolve()` it derives the hash, serves a cache hit when
  present, otherwise resolves live, stores the artifact, and swallows any
  write failure so resolution always succeeds.
- `src/Core/ConfigurationBuilder.php` — new `withCache($cache)` (opt-in); when
  set, `build()` returns a `CachingResolver`. Unused ⇒ identical to today.

Tests: `CompilerCacheTest` (cache hit serves equal snapshot + writes an
artifact; content edit invalidates the cache; a throwing write falls back to
live resolve; a corrupt/missing artifact yields a safe miss; a typed snapshot
reconstructed from cache still validates and throws `TypeException` on a bad
value). Example: `examples/cached.php` resolves twice — the second boot is
served from the artifact.
Suite green: **192 tests / 446 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M4.E5.1 / E5.2: Secrets & redaction ✅

Completes M4 (Epics E5.1 + E5.2) of `docs/ADDITIONAL_FEATURES_PLAN.md` /
`docs/ADDITIONAL_FEATURES_ROADMAP.md`: secrets resolve transparently and are
**never rendered in clear text** on any surface (inspector, diff, graph, CLI,
telemetry), while `get()` still returns the real value. Behaviour is opt-in and
unchanged for non-secret configuration.

- `src/Contract/SecretSourceInterface.php` (new port) — marker for secret-bearing
  sources; `GcpSecretManagerSource`, `AwsParameterStoreSource`,
  `KubernetesSecretSource`, `DockerSecretsSource` and the new `VaultSource`
  implement it. `VaultSource` + `src/Adapter/Enterprise/Client/VaultClient.php`
  (HashiCorp Vault KV v2) reuse the Phase 8 `RemoteKeyValueClient`/`HttpClient`.
- `src/Core/DocumentNormalizer.php` — detects secret schemes
  (`k8s-secret://`, `docker-secret://`, `aws-ssm://`, `gcp-secretmanager://`,
  `vault://`) and flags every resolved node `sensitive`; the specific
  `sourceKind` is preserved for provenance richness.
- `src/ValueObject/Provenance.php` + interface — new `isSensitive()` /
  `asSensitive()`; carried through all immutable copies and `toArray()`/
  `fromArray()` (so the compiled cache preserves it).
- `src/Contract/DecrypterInterface.php` + `src/Exception/DecryptionException.php`
  + `src/Contract/CommandRunnerInterface.php` — decryption ports.
- `src/Adapter/Decryption/{CommandDecrypter,AgeDecrypter,SopsDecrypter,DecryptingReader}.php`
  — adapters. `AgeDecrypter`/`SopsDecrypter` shell out via
  `src/Adapter/CommandRunner/{NativeCommandRunner,FakeCommandRunner}.php`
  (offline-testable). `DecryptingReader` decrypts `enc:` / `sops:`-annotated
  resources before the loader parses them; unannotated resources pass through.
- `src/Core/ConfigurationSnapshot.php` + interface + `src/Core/TypedSnapshot.php`
  — new `isSensitive(string)` derived from per-key provenance (rebuilt from cache).
- `src/Core/RedactingRenderer.php` (new) — the single place that masks sensitive
  values as `****`; non-sensitive values format exactly as before.
- `src/CLI/ConfigurationInspector.php`, `src/CLI/GraphVisualizer.php` — route
  value rendering through `RedactingRenderer`.
- `src/Core/ConfigurationDiffer.php` — new `summarize()` masks sensitive
  added/removed/changed values (consumed by the `bin/configure-it diff`
  command); `ConfigurationDiff` carries per-key sensitivity.
- `src/Bridge/OpenTelemetry/OpenTelemetryReporter.php` — routes emitted scalar
  values through `RedactingRenderer` (defensive: it emits only metadata today,
  never resolved secret values).
- `src/Core/ConfigurationBuilder.php` — new `withDecrypter($decrypter)` (opt-in);
  wraps the reader chain in `DecryptingReader` when set.

Tests: `RedactionTest` (secret masked on every render surface; non-secret
untouched; redaction cannot be bypassed), `VaultSourceTest` (Vault KV v2/v1
unwrapping + sensitive marking), `DecryptionTest` (`DecryptingReader` decrypts
`enc:`/`sops:` and passes through; `Age`/`Sops` decrypters via command runner;
failure → `DecryptionException`). Example: `examples/secrets.php` (secret volume
+ transparent decryption, both masked in the inspector).
Suite green: **208 tests / 479 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M5.E6.1: Remote sources + CachingReader ✅

Completes M5 (Epic E6.1) of `docs/ADDITIONAL_FEATURES_PLAN.md` /
`docs/ADDITIONAL_FEATURES_ROADMAP.md`: first-class dynamic backends
(Consul/etcd/custom HTTP) with caching and last-good fallback. Behaviour is
additive and opt-in; non-remote configuration is unchanged.

- `src/Adapter/Enterprise/HttpSource.php` (new) — discovers a single
  `http(s)://` JSON resource. `src/Adapter/Enterprise/HttpReader.php` (new)
  performs the GET via `Contract/HttpClient` (`CurlHttpClient` in prod,
  `FakeHttpClient` in tests) and surfaces failures as `ReadException`.
- `src/Adapter/Enterprise/ConsulSource.php` + `src/Adapter/Enterprise/Client/ConsulClient.php` (new) — HashiCorp Consul KV. The client implements `Contract/RemoteKeyValueClient`, fetches
  `GET /v1/kv/{prefix}?recurse`, base64-decodes values and re-roots the tree
  under the requested prefix; carried by `RemoteJsonReader('consul', …)`.
- `src/Adapter/Enterprise/EtcdSource.php` + `src/Adapter/Enterprise/Client/EtcdClient.php` (new) — etcd v2 REST. The client implements `Contract/RemoteKeyValueClient`, walks the nested node tree and
  re-roots it under the requested prefix; carried by `RemoteJsonReader('etcd', …)`.
- `src/Contract/RemoteDocumentCacheInterface.php` (new) — raw-document, TTL-aware
  cache with a `getLastGood()` for stale fallback. `src/Cache/FileSystemDocumentCache.php` (new) implements it using the same include/`var_export`
  mechanism as the M3 `FileSystemCache`. (A dedicated port rather than force-fitting `ConfigurationCacheInterface`, which stores *snapshots*, not raw documents.)
- `src/Adapter/CachingReader.php` (new) — reader decorator: serves a fresh cached
  document without touching the network, refreshes the cache on miss/stale, and —
  crucially — serves the last-good document when the inner fetch throws (resilience against backend outages). Re-throws only when no last-good copy exists.
- `src/Core/DocumentNormalizer.php` — source kinds `http`/`consul`/`etcd` are now
  derived from the resource URI scheme for provenance richness.

Tests: `RemoteSourceTest` extended with HTTP/Consul/etcd fetch + resolution
(asserting values and `sourceKind`), plus `CachingReader` fresh-serve, stale-serve
and failure-fallback via `FakeHttpClient`. Example: `examples/remote.php` resolves
an HTTP endpoint and demonstrates the last-good fallback (runs offline via
`FakeHttpClient`; production wiring with `CurlHttpClient` is shown in comments).
Suite green: **216 tests / 494 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M6.E4.1: Live configuration (Watcher + Reloadable facade) ✅

Completes E4.1 of Epic E6.1 / M6 (`docs/ADDITIONAL_FEATURES_PLAN.md` §4,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). Configuration can now evolve at runtime
without restart, while the immutability contract (ADR-0003) is preserved.
Behaviour is additive and opt-in.

- `src/Contract/ConfigurationWatcherInterface.php` (new) — port: `watch()`,
  `stop()`, `onChange()`. Emits a `ConfigurationChanged` `Occurrence` through
  the reporter spine and invokes listeners on detected change.
- `src/Adapter/PollingWatcher.php` (new) — portable adapter: polls a pluggable
  signature on an interval (`tick()` / blocking `watch()` loop). Default
  signature is `FileModificationProbe` (content-hash based, so same-size edits
  in the same second are still detected). The first tick primes a baseline and
  never dispatches.
- `src/Adapter/FileModificationProbe.php` (new) — default signature provider for
  `PollingWatcher`; inject a different `callable` to watch non-filesystem
  targets (e.g. an HTTP ETag).
- `src/Adapter/FsWatcher.php` (new) — kernel-level adapter over `ext-inotify`;
  opens the watch in the constructor and dispatches on modify/attrib/close-write/
  create/delete. `watch()` is an interruptible blocking loop; `await()` performs
  a single bounded wait so it can be driven from a cooperative loop or tests.
- `src/Core/ReloadableConfiguration.php` (new) — facade: wraps a
  `ConfigurationBuilder` + `ConfigurationWatcherInterface`, resolves the initial
  snapshot, and subscribes to change events. On change it re-resolves and
  **atomically swaps** the snapshot via a single property assignment; any
  snapshot previously handed out stays valid. A failed reload keeps the
  last-good snapshot and records an `error` `Observation` (never crashes the
  host). Exposes `snapshot()` plus `get/has/int/float/bool/string/list/
  stringList/all` and `watch()/stop()/reload()`.

Tests: `PollingWatcherTest` (baseline/tick dispatch, stability, custom probe,
default probe), `FsWatcherTest` (dispatch on modify, idle, stop releases
resources), `ReloadableConfigurationTest` (initial resolve, atomic swap with a
captured old reference staying valid, watcher-driven reload, failed reload keeps
last-good + observed). Example: `examples/live.php` edits a file and drives one
`tick()`, proving the live swap and the last-good invariant (runs offline via
`PollingWatcher`). Drift detection (E4.2) is not part of this milestone.
Suite green: **228 tests / 516 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M6.E4.2: Live configuration drift detection ✅

Completes E4.2 of Epic E6.1 / M6 (`docs/ADDITIONAL_FEATURES_PLAN.md` §4,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). Configuration can now be watched for
divergence from a pinned baseline at runtime, complementing E4.1's live reload.
Behaviour is additive and opt-in.

- `src/Contract/ConfigurationDriftDetectorInterface.php` (new) — port: `pin()`,
  `check()`, `watch()`, `stop()`, `onDrift()`. Drift is reported on *transition*
  (entering drift, a further change while already in drift, or a return to the
  baseline) so an interval loop does not re-emit the same divergence every tick.
- `src/Core/ConfigurationDriftDetector.php` (new) — takes an injectable
  `callable(): ConfigurationGraphInterface` (the live graph), an optional pinned
  baseline, an optional reporter, and a `ConfigurationDiffer`. On divergence it
  records a `ConfigurationDrift` `Occurrence` and a `warning` `Observation`
  (with a readable `config.diff` summary), invokes `onDrift` listeners, and on
  return-to-baseline records an `info` `Observation`. Uses content hashing
  (`md5(serialize(graph->toArray()))`) to detect state changes.
- `src/Bridge/OpenTelemetry/SemanticConventions.php` — maps the `ConfigurationDrift`
  occurrence to the OTel `config.drift` span + event, so drift is surfaced as a
  native OpenTelemetry event through the existing reporter spine.
- `examples/drift.php` (new) — runnable, offline demo: pins a baseline graph,
  mutates the live store, and shows the `ConfigurationDrift` occurrence + the
  warning observation listing the exact changed keys.

Tests: `ConfigurationDriftDetectorTest` (baseline match → no drift and no
telemetry; divergence detected + reported + listener invoked; drift resolved when
the live graph returns to baseline; `watch()` loop stops on drift without
re-emitting) and an `OpenTelemetryReporterTest` asserting the `config.drift`
event + span. Suite green: **233 tests / 539 assertions**, PHPStan level 9
**0 errors**, `php-cs-fixer` **0 files to fix**.

### Additional Features — M7.E8.1: Validation severity (warnings vs errors) ✅

Completes E8.1 of Epic E8 / M7 (`docs/ADDITIONAL_FEATURES_PLAN.md` §8,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). Validation now distinguishes blocking
errors from non-blocking warnings, so deprecations and other soft notices no
longer crash resolution — they surface as observations. Behaviour is backward
compatible (errors still throw by default).

- `src/Contract/ValueObject/ValidationResultInterface.php` (changed) — adds
  `issues()`, `warnings()`, `hasErrors()`, `hasWarnings()`; every issue now
  carries a `severity` of `'error'` or `'warning'`. `errors()` returns only the
  blocking (error-severity) issues.
- `src/ValueObject/ValidationResult.php` (changed) — splits issues by severity;
  `valid()` means "no errors" (warnings do not make a result invalid).
- `src/ValueObject/ValidationResultFactory.php` + its contract — docblock updated
  to the severity-aware issue shape.
- `src/Adapter/NativeConfigurationSchema.php` (changed) — now reads Nette's
  separate warning channel (`Processor::getWarnings()`) and maps those messages
  to `severity => 'warning'`, while `ValidationException` messages map to
  `severity => 'error'`. A warning-only result is reported as valid.
- `src/Core/ConfigurationResolver.php` (changed) — `validateGraph()` records a
  `ConfigurationValidated` occurrence (with error/warning counts) and emits one
  `Observation` per issue (level = its severity). Resolution only aborts on
  errors, unless the new `failOnWarnings` flag is set.
- `src/Core/ConfigurationBuilder.php` (changed) — new `failOnWarnings(bool)`
  (default `false`); threaded through `ConfigurationFactory` →
  `ConfigurationResolver` so warnings can optionally be made fatal.
- `src/Contract/ConfigurationFactoryInterface.php` + `src/Core/ConfigurationFactory.php`
  (changed) — to carry the `failOnWarnings` flag; `CachingResolver` is unaffected
  (the inner resolver is already built with the flag by the builder).

Tests: `SeverityTest` (warning-only resolves and emits a `warning` observation;
error throws regardless of `failOnWarnings`; `failOnWarnings(true)` makes a
warning fatal) plus updated `ValidationResultTest` and `ValidationProcessorTest`.
Suite green: **239 tests / 561 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M7.E8.2: Feature flags (env/tenant/user/attribute gating + rollout) ✅

Completes E8.2 of Epic E8 / M7 (`docs/ADDITIONAL_FEATURES_PLAN.md` §8,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). Feature flags are now a thin,
schema-driven evaluation layer built on top of the resolved configuration —
they introduce **no new source of truth** (see plan §8). Flags are declared in
config under a single key (default `feature_flags`) and evaluated against an
`ExecutionContextInterface`.

- `src/Contract/FlagEvaluatorInterface.php` (new) — port: `definitions()`,
  `has()`, `isEnabled()`, `evaluate(ExecutionContextInterface)`.
- `src/Core/FlagEvaluator.php` (new) — reads flag definitions from a
  `ConfigurationSnapshotInterface` (default key `feature_flags`) and evaluates
  each against the context. Unknown flags are off; a non-array `feature_flags`
  value throws.
- `src/ValueObject/FlagDefinition.php` (new) — parses a flag definition from a
  bool shortcut or a full structure, then decides enablement per context.
  Evaluation order: master `enabled` switch → environment allow-list → tenant
  allow-list → required attribute match → percentage `rollout` (a stable
  `crc32(bucket + flag) % 100 < rollout`, bucketed by `bucketBy`:
  `tenant` | `user` | `environment`).
- `src/Adapter/FeatureFlagSchema.php` (new) — reusable Nette Schema fragment
  (`FeatureFlagSchema::structure()` / `::definition()`) so consumers compose
  *validated* flag definitions into their own schema. Keeps Nette behind the
  adapter boundary; Core never sees it.

Tests: `FlagDefinitionTest` (bool shortcut; env/tenant/attribute gates; stable
50% rollout split across 200 tenants; rollout boundaries; invalid `bucketBy` /
`rollout` / malformed data throw) and `FlagEvaluatorTest` (no flags → empty
state; reads definitions from snapshot; tenant + custom-path evaluation; unknown
flag off; malformed flags throw). Runnable `examples/feature-flags.php` shows
env-, tenant-, and attribute-gated flags across execution contexts. Suite
green: **255 tests / 593 assertions**, PHPStan level 9 **0 errors**,
`php-cs-fixer` **0 files to fix**.

### Additional Features — M8: Framework Adoption (Epic E9.1 — framework bundles) ✅

Completes E9.1 of Epic E9 / M8 (`docs/ADDITIONAL_FEATURES_PLAN.md` §9,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). ConfigureIt now meets teams in their
stack through standalone, framework-agnostic **companion packages** (per the
"No Framework Bias" principle — each only adapts the public
builder/snapshot ports, no Core changes).

- **`configure-it-psr11`** (`src/ConfigureItPsr11/`, PSR-11 entry exposing the
  snapshot): `ConfigureItContainer` implements `Psr\Container\ContainerInterface`
  and serves the resolved `ConfigurationSnapshotInterface` under a single, stable
  entry id (default: the interface FQCN; overridable). Unknown ids throw a
  `Psr\Container\NotFoundExceptionInterface`. `ConfigureItContainerFactory`
  offers `fromSnapshot()` (already-resolved) and `fromBuilder()` (owns the final
  resolve step).
- **`configure-it-symfony`** (`src/ConfigureItSymfony/`, full Symfony bundle):
  `ConfigureItBundle` (extends `Symfony\HttpKernel\Bundle\Bundle`) registers
  `ConfigureItExtension`, which translates the `configure_it` bundle config
  (`sources` / `environment` / `schema` / `cache`) into service definitions. The
  container exposes the resolved `ConfigurationSnapshotInterface` **and** the
  configured `ConfigurationBuilder` as public services. Wiring is delegated to
  `ConfigureItSnapshotFactory` (a pure, testable data transformation) so the
  extension stays thin. Verified by booting a `ContainerBuilder` both from an
  array config and from a real YAML bundle config via `YamlFileLoader`.

Each companion is an independent composer package (own `composer.json`,
`phpstan.neon`, `.php-cs-fixer.php`, `phpunit.xml.dist`, README) and runs its
own gate: **PHPStan level 9 — 0 errors**, `php-cs-fixer` — **0 files**,
**PHPUnit — 4 tests** (PSR-11: `ConfigureItContainerTest`; Symfony:
`ConfigureItExtensionTest` + `ConfigureItBundleTest`). The core `ConfigureIt`
package is unchanged by this milestone and remains green (**255 tests / 593
assertions**, PHPStan level 9, php-cs-fixer clean).

`configure-it-laravel` (T8.1.3) is deferred: the plan scopes framework bundles
as separate companions and the M8 Exit only requires PSR-11 + one full framework
bundle, both now delivered. Laravel is tracked as a follow-up companion.

### Additional Features — M9: Tooling & Operability (Epic E9.2 — web explorer + graph query) ✅

Completes E9.2 of Epic E9 / M9 (`docs/ADDITIONAL_FEATURES_PLAN.md` §9,
`docs/ADDITIONAL_FEATURES_ROADMAP.md`). ConfigureIt now has a read-only product
surface so teams can *see* their resolved configuration — provenance, overrides
and structure — in a browser, and query it programmatically.

- **`configure-it-explorer`** (`src/ConfigureItExplorer/`, read-only web explorer
  + graph query API): a framework-free, dependency-light companion (only
  `it-pack/configure-it` as a runtime dependency). It reuses the Core
  diagnostics services so it behaves identically to `bin/configure-it`:
  - `Http\ExplorerController` serves `/explain`, `/diff`, `/graph`
    (Graphviz DOT / ASCII) and `/query`. Every route answers HTML by default and
    JSON when `?format=json` (or `Accept: application/json`) is requested, so the
    explorer doubles as an HTTP query API.
  - `Query\GraphQuery` + `QueryParser`/`QueryCriterion`/`QueryPredicate` implement
    a tiny, **safe, enumerate-driven** query language over a resolved graph:
    `source`, `overridden-by` (e.g. "keys overridden by `local.json`"),
    `sensitive`, `adapter`, `layer`, `format`, `trail`, plus `changed` (keys that
    differ between two graphs, built on `ConfigurationDiffer`). A bare value falls
    back to `source`, so "keys from `aws-ssm`" is just `aws-ssm`.
  - `Server\` (`Request`/`Response`/`Router`) is a minimal HTTP layer kept fully
    decoupled from the built-in server; `ExplorerApplication::bootstrap()` wires
    it and `public/index.php` + `bin/configure-it-explorer` serve it via
    `php -S`. `View\HtmlRenderer` escapes all output and Core masks secrets, so
    nothing sensitive is ever rendered in clear text.

Each companion is an independent composer package (own `composer.json`,
`phpstan.neon`, `.php-cs-fixer.php`, `phpunit.xml.dist`, README) and runs its own
gate: **PHPStan level 9 — 0 errors**, `php-cs-fixer` — **0 files**, and
**PHPUnit — 22 tests / 42 assertions** (query predicates + cross-graph changes,
and HTML/JSON controller behaviour incl. end-to-end over real fixture files). The
core `ConfigureIt` package is unchanged by this milestone and remains green
(**255 tests / 593 assertions**, PHPStan level 9, php-cs-fixer clean).

Epic E9.3 (`configure-it-schema-docs`, `configure-it-testing`) is also delivered
(see the companion packages under `src/`), completing M9 in full.

### Additional Features — M9: Tooling & Operability (Epic E9.3 — schema docs & testing utilities) ✅

- **`configure-it-schema-docs`** (`src/ConfigureItSchemaDocs/`): reflects a
  `Nette\Schema\Expect` definition (via `ReflectionProperty`, the same boundary
  the Core crosses in `NativeConfigurationSchema::scalarTypes()`) into a
  normalized `SchemaNode` tree, then renders Markdown reference docs or a
  JSON-Schema (draft 2020-12) document (`SchemaDocGenerator`, `SchemaIntrospector`,
  `Renderer\MarkdownRenderer`, `Renderer\JsonSchemaRenderer`). A
  `bin/configure-it-schema-docs` CLI emits either format from a PHP file that
  returns an `Expect` definition.
- **`configure-it-testing`** (`src/ConfigureItTesting/`): fakes and a PHPUnit
  trait for consumers — `Fake\FakeConfigurationSource`, `Fake\FakeLoader`,
  `Fake\SpyProcessor`, `Fake\InMemoryGraph`, `Node\FakeNode`, and
  `Assertions\ConfigurationAssertions` (key presence, resolved value, source kind,
  override status, sensitivity, format, adapter, priority, processor trail,
  whole-graph equality, and `assertConfigValid` against an `Expect` definition).

These companions run the same per-package gate as the explorer: **PHPStan level 9
— 0 errors**, `php-cs-fixer` — **0 files**, and **PHPUnit** —
`configure-it-schema-docs` **25 tests / 84 assertions**,
`configure-it-testing` **20 tests / 63 assertions**. The core `ConfigureIt`
package remains unchanged and green (**255 tests / 593 assertions**, PHPStan
level 9, php-cs-fixer clean).

**M9 Exit:** explorer (explain/diff/graph + query, HTML + JSON) plus
`configure-it-schema-docs` (Markdown + JSON-Schema from `Expect`) and
`configure-it-testing` (fakes + `ConfigurationAssertions` trait) are all
delivered and pass their gates.

### Notes

- PHPStan level 9 reports only `shipmonk.deadMethod` findings at this stage
  (contracts/value objects not yet consumed). These clear once Core (Step 3)
  implements `ConfigurationResolver`/`ConfigurationGraph` and the adapters wire in.
- Coding standard (`php-cs-fixer`, PSR-12 + PHP84 migration) is clean across all
  new files.
