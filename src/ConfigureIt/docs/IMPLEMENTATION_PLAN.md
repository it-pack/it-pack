# ConfigureIt — Implementation Plan

> Companion to `AGENTS.md`, `PORTS.md`, `PIPELINE.md`, `GRAPH.md`, `MODEL.md`, `ROADMAP.md`
> and the root `../../docs/EXECUTION.md` execution model.
>
> Status: **Phase 0 in progress.** Source code does not yet exist; this document
> is the authoritative build plan.

---

## 1. Context

`ConfigureIt` is, as of this writing, the least-developed package in the
`it-pack` monorepo. It ships a complete design surface — `AGENTS.md`,
`PORTS.md`, `PIPELINE.md`, `GRAPH.md`, `MODEL.md`, five ADRs
(`ADR-0001`…`ADR-0005`), `ROADMAP.md`, `VISION.md` — plus a working
`composer.json`, `phpstan.neon`, `codeception.yml`, `.php-cs-fixer.php` and
`extension.neon`. **It contains zero source code and zero tests.**

This plan builds `ConfigureIt` from scratch, strictly following its own
documented architecture, while honoring two cross-cutting requirements:

1. **Execution model** (`../../docs/EXECUTION.md`) — every package in the
   ecosystem builds upon a single execution vocabulary (Context, Activity,
   Measurement, Observation, Occurrence, Snapshot). `ConfigureIt`'s
   *ConfigurationSnapshot* is the execution Snapshot; the resolution pipeline
   must emit *Occurrences* (`ConfigurationLoaded`, `Merged`, `Validated`,
   `Resolved`, `SnapshotCreated`) and *Observations* (warnings, diagnostics).
2. **Static analysis** — PHPStan **level 9** (current `phpstan.neon` is level 5).
3. **Testing** — comprehensive **Codeception** unit suite (mirroring the
   `HashIt` reference package).

### Established conventions (from sibling packages)

- PSR-4: `ItPack\ConfigureIt\` → `src/`. Tests: `Tests\` → `tests/`,
  `tests/Unit` suite, `UnitTester` actor, `verify()` assertions.
- Dev toolchain already installed in `vendor/`: `codeception`, `phpstan`,
  `php-cs-fixer`, plus `nette/schema`, `vlucas/phpdotenv`, `internal/toml`,
  `webuni/front-matter`, `aura/di` (dev deps in `composer.json`).
- PHP **8.5.x**. Coding standard: `@PSR12` + `@PHP84Migration`, strict types,
  readonly, enums, constructor promotion (see `.php-cs-fixer.php`).

### Architecture (from `MODEL.md` / `PORTS.md`)

```
ConfigurationSource  →  discovers  →  ConfigurationResource
ConfigurationReader  →  reads      →  Raw Content
ConfigurationLoader  →  parses     →  ConfigurationDocument
ConfigurationPipeline(Processor[]) → ConfigurationGraph
ConfigurationResolver →  orchestrates →  ConfigurationSnapshot (immutable)
```

Dependency direction (**Contracts → Core → Ports → Adapters**): Core never
imports vendor code. Adapters confine all third-party libraries (ADR-0002).

### Scope

This plan delivers **ROADMAP Phase 0 + Phase 1**:

- Contracts, Sources, Loaders, Builder, Resolver, Snapshot.
- Native formats: **PHP**, **JSON** (plus ENV source/reader).
- Schema layer (Nette Schema adapter).
- Execution-model wiring.

Later phases (YAML/TOML/NEON, graph diagnostics, CLI, OTel) are out of scope
here and tracked in `ROADMAP.md`.

---

## 2. Package layout (target)

```
src/ConfigureIt/
├── composer.json            (exists)
├── phpstan.neon             (exists, → level 9 in Step 0)
├── codeception.yml          (exists)
├── extension.neon           (exists)
├── .php-cs-fixer.php        (exists)
├── src/
│   ├── Contract/
│   │   ├── ConfigurationResourceInterface.php
│   │   ├── ConfigurationSourceInterface.php
│   │   ├── ConfigurationReaderInterface.php
│   │   ├── ConfigurationLoaderInterface.php
│   │   ├── ConfigurationDocumentInterface.php
│   │   ├── ConfigurationProcessorInterface.php
│   │   ├── ConfigurationPipelineInterface.php
│   │   ├── ConfigurationSchemaInterface.php
│   │   ├── ConfigurationGraphInterface.php
│   │   ├── ConfigurationSnapshotInterface.php
│   │   ├── ConfigurationResolverInterface.php
│   │   └── ResolutionReporterInterface.php
│   ├── ValueObject/
│   │   ├── Format.php                  (enum)
│   │   ├── ConfigurationResource.php
│   │   └── ValidationResult.php
│   ├── Execution/
│   │   ├── ExecutionContext.php
│   │   ├── Occurrence.php
│   │   ├── Observation.php
│   │   └── ResolutionReporter.php      (implements ResolutionReporterInterface)
│   ├── Core/
│   │   ├── ConfigurationDocument.php
│   │   ├── ConfigurationNode.php
│   │   ├── ConfigurationGraph.php
│   │   ├── ConfigurationSnapshot.php
│   │   ├── ConfigurationPipeline.php
│   │   ├── ConfigurationResolver.php
│   │   └── ConfigurationBuilder.php
│   ├── Adapter/
│   │   ├── FileSource.php
│   │   ├── FileReader.php
│   │   ├── EnvSource.php
│   │   ├── EnvReader.php
│   │   ├── PhpLoader.php
│   │   ├── JsonLoader.php
│   │   └── NativeConfigurationSchema.php   (wraps nette/schema)
│   └── Exception/
│       ├── ConfigurationException.php       (marker, extends \RuntimeException)
│       ├── LoadException.php
│       ├── ParseException.php
│       └── ValidationException.php
├── examples/
│   └── resolve.php
└── tests/
    ├── Unit.suite.yml
    ├── Support/UnitTester.php
    ├── Support/_generated/UnitTesterActions.php   (codecept build)
    ├── Support/Data/                              (fixture config files)
    │   ├── app.php
    │   ├── app.json
    │   └── .env
    └── Unit/
        ├── Contract/FormatTest.php
        ├── ValueObject/ConfigurationResourceTest.php
        ├── Core/ConfigurationDocumentTest.php
        ├── Core/ConfigurationGraphTest.php
        ├── Core/ConfigurationSnapshotTest.php
        ├── Core/ConfigurationPipelineTest.php
        ├── Core/ConfigurationResolverTest.php
        ├── Core/ConfigurationBuilderTest.php
        ├── Adapter/PhpLoaderTest.php
        ├── Adapter/JsonLoaderTest.php
        ├── Adapter/FileSourceTest.php
        ├── Adapter/EnvSourceTest.php
        ├── Adapter/NativeConfigurationSchemaTest.php
        └── Execution/ResolutionReporterTest.php
```

---

## 3. Step-by-step plan

### Step 0 — Tooling & config baseline
- Bump `phpstan.neon` to **level 9** (`level: 9`, `reportUnmatchedIgnoredErrors: true`,
  `treatPhpDocTypesAsCertain: true`, `checkMissingIterableValueType: true`).
  Empty `ignoreErrors` (no vendor shims in Core).
- Validate `composer.json` (`composer validate`).
- Run `vendor/bin/codecept build` to generate `tests/Support/_generated/UnitTesterActions.php`
  (requires `tests/Unit.suite.yml` + `tests/Support/UnitTester.php`; create if absent).
- Confirm `vendor/bin/{codecept,phpstan,php-cs-fixer}` resolve.

### Step 1 — Contracts (interfaces + value objects)
- `Format` enum (PHP, JSON, YAML, ENV, NEON, TOML, INI, XML, FRONTMATTER; backed `string`,
  `fromString()`).
- All `Contract/*Interface` files as listed in layout. Methods typed; tree values wrapped
  in typed `Document`/`Node` rather than raw `mixed` arrays where possible; use precise
  PHPDoc generics for level 9.
- `ConfigurationResource`, `ValidationResult` value objects.

### Step 2 — Execution-model adapters (EXECUTION.md requirement)
- `ExecutionContext` (env, tenant, correlation id), `Occurrence`, `Observation` value objects.
- `ResolutionReporter` implementing `ResolutionReporterInterface`: the Resolver feeds it
  during resolution, emitting typed Occurrences. Core-only, no vendor dependency.

### Step 3 — Core implementation
- `ConfigurationDocument`, `ConfigurationNode`, `ConfigurationGraph` (nodes carry value +
  metadata: source, priority, provenance, schema).
- `ConfigurationSnapshot` (immutable; `get(string): mixed`, `has(string): bool`).
- `ConfigurationPipeline` (ordered, deterministic processor execution).
- `ConfigurationResolver` (source → reader → loader → pipeline → schema → snapshot + reporter).
- `ConfigurationBuilder` (mutable fluent API; `addSource()`, `loader()`, `processor()`,
  `schema()`, `build(): ConfigurationResolver`).

### Step 4 — Native adapters (Ports & Adapters; ADR-0002)
- `FileSource` + `FileReader` (filesystem discovery/read).
- `EnvSource` + `EnvReader` (environment variables).
- `PhpLoader` (`require` of `<?php return [...]` file).
- `JsonLoader` (`ext-json`).
- `NativeConfigurationSchema` wrapping `nette/schema` — Core sees only `ConfigurationSchemaInterface`.
- Each adapter implements a Core port; no Core class imports vendor FQCNs.

### Step 5 — Default wiring & example
- Wire `FileSource`, `PhpLoader`, `JsonLoader`, `EnvSource` as the default resolution path so
  `ConfigureIt::builder()` works out of the box (per `VISION.md` example).
- Add `examples/resolve.php`: file + env + programmatic merge → snapshot.

### Step 6 — Tests (Codeception, comprehensive)
- Unit suite mirroring `src/`, HashIt `HashTest.php` style (`verify()` assertions,
  `Codeception\Test\Unit`).
- Cover: every Core class, every adapter, the builder, and the resolution reporter.
- Assert behaviours (merge-by-priority, immutability, exception translation, occurrence order),
  not internals. Fixtures under `tests/Support/Data/`.

### Step 7 — Static analysis (PHPStan level 9)
- Run `composer analyse` (`vendor/bin/phpstan analyse`); fix every level-9 error iteratively.
- Zero errors required; prefer precise PHPDoc generics over `ignoreErrors`.

### Step 8 — Coding standards & verification gate
- `vendor/bin/php-cs-fixer fix` (PSR-12 + PHP84 migration).
- `vendor/bin/codecept run Unit --coverage` — all green, coverage report generated.
- Final gate: cs clean + phpstan level 9 + full Codeception suite green.
- Update `ROADMAP.md` (mark Phase 0 + Phase 1 done) and add a changelog note per `CONTRIBUTING.md`.

---

## 4. Dependency / sequencing summary

| Step | Produces | Verifiable |
|------|----------|-----------|
| 0 | phpstan lvl 9 config, codecept build | `phpstan` runs; `codecept build` succeeds |
| 1 | Contracts + value objects | phpstan passes on `src/` |
| 2 | Execution adapters | phpstan green |
| 3 | Core (Document/Graph/Snapshot/Pipeline/Resolver/Builder) | phpstan green |
| 4 | Native adapters (PHP/JSON/ENV/File/Nette schema) | phpstan green |
| 5 | Default wiring + example | example runs |
| 6 | Codeception suite | `codecept run Unit` green |
| 7 | PHPStan level 9 clean | `composer analyse` 0 errors |
| 8 | cs-fixer + final gate + docs | all green |

---

## 5. Key risks & mitigations

- **Level 9 strictness on `mixed`** (documents are tree-shaped): mitigate with typed
  `Document`/`Node` wrappers and precise `@template`/`@phpstan-return` generics instead of
  raw arrays.
- **Vendor leak** (Nette/phpdotenv): confined to `Adapter/`; Core depends only on ports.
  Optionally add a Deptrac layer rule.
- **EXECUTION.md coupling**: keep the execution model inside Core as plain value objects,
  not a vendor dependency — matches "external standards are adapters".

---

## 6. Definition of done

- [ ] `phpstan.neon` at level 9, `composer analyse` → 0 errors.
- [ ] All files in §2 layout present and PSR-12-clean (`php-cs-fixer` green).
- [ ] Codeception `Unit` suite green with meaningful coverage of Core + Adapters.
- [ ] `examples/resolve.php` runs and prints a resolved immutable snapshot.
- [ ] `ROADMAP.md` updated; changelog note added.
