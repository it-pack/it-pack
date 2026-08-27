# AGENTS.md

# ConfigureIt

> **Configuration as Data. Resolution as a Pipeline. Adapters for Everything.**

---

# Mission

ConfigureIt is a modular, framework-agnostic configuration SDK for PHP.

Its goal is **not** to become another YAML parser or configuration library.

Instead, ConfigureIt provides a unified abstraction for loading, validating, transforming, resolving, and consuming configuration from arbitrary sources through a clean Ports & Adapters architecture.

Everything is replaceable.

Everything is composable.

Everything is testable.

---

# Vision

Configuration should be treated as a first-class domain model.

Instead of thinking in terms of files:

- YAML
- TOML
- JSON
- ENV

ConfigureIt thinks in terms of:

- Configuration Sources
- Configuration Documents
- Configuration Graphs
- Configuration Pipelines
- Configuration Schemas
- Configuration Contexts

Formats are merely adapters.

---

# Design Principles

## SOLID

Every component should follow SOLID principles.

Avoid "god classes".

Prefer many focused objects.

---

## Ports & Adapters

The Core must never depend on infrastructure.

External libraries belong inside adapters.

Never allow vendor-specific code to leak into Core.

---

## Dependency Inversion

Core defines interfaces.

Adapters implement them.

Never the other way around.

---

## Composition over Inheritance

Favor decorators, processors, builders and composition.

Inheritance should be rare.

---

## Immutable by Default

Configuration objects should be immutable whenever possible.

Resolution produces immutable snapshots.

---

## Explicit over Magic

No hidden conventions.

Everything should be discoverable.

---

## Strong Typing

Use PHPStan max level.

Prefer readonly objects.

Prefer enums over strings.

Avoid mixed.

---

# Architectural Layers

```
Application

↓

Core

↓

Contracts

↓

Ports

↓

Adapters
```

Dependencies only flow downward.

---

# Core Concepts

Everything revolves around a few domain concepts.

## Sources

Responsible for retrieving configuration.

Examples:

- Files
- Environment
- CLI
- Database
- HTTP
- Vault
- Programmatic

---

## Loaders

Responsible for parsing data.

Examples:

- YAML
- TOML
- JSON
- NEON
- XML
- PHP
- Markdown Front Matter

---

## Documents

Raw parsed configuration.

Independent from source.

---

## Pipeline

Transforms documents.

Example:

Load

↓

Normalize

↓

Merge

↓

Validate

↓

Resolve Variables

↓

Apply Defaults

↓

Freeze

↓

Snapshot

---

## Resolver

Produces the final configuration graph.

---

## Schema

Responsible for validation.

The preferred implementation is Nette Schema through an adapter.

The Core must never depend on Nette directly.

---

## Snapshot

Immutable runtime configuration.

Consumers should depend on snapshots rather than mutable builders.

---

# Adapters

Adapters wrap third-party libraries.

Examples:

- Aura.Di
- Nette Schema
- vlucas/phpdotenv
- php-internal/toml
- ext-yaml
- webuni/front-matter
- nette/neon
- frictionlessdata/tableschema

Adapters must never contain business logic.

---

# Pipeline Philosophy

Everything should be a processor.

Examples:

- MergeProcessor
- IncludeProcessor
- VariableProcessor
- PlaceholderProcessor
- ValidationProcessor
- CastingProcessor
- FreezeProcessor
- CacheProcessor

Users should be able to insert custom processors.

---

# Configuration Sources

The system should support any source through adapters.

Examples:

- Local Files
- Directories
- Environment Variables
- CLI Arguments
- HTTP Endpoints
- S3
- Redis
- Consul
- Vault
- Kubernetes ConfigMaps
- Docker Secrets
- Programmatic Arrays

---

# Supported Formats

Native support should remain adapter-driven.

Examples:

- JSON
- YAML
- TOML
- NEON
- XML
- PHP
- ENV
- Markdown Front Matter
- CSV
- TSV

Future formats should require no Core changes.

---

# Graph Philosophy

Internally configuration should evolve toward a graph model.

Every value should know:

- where it came from
- which adapter loaded it
- which schema validated it
- which processor modified it
- whether it was overridden
- which source has priority

Eventually this enables:

- provenance
- diagnostics
- semantic diffing
- visualization
- explainability

---

# Dependency Rules

Core may depend on:

- Contracts
- Value Objects
- PHP Standard Library

Core may NOT depend on:

- Symfony
- Laravel
- Nette
- Aura
- Composer Plugins
- Frameworks

Adapters may depend on anything.

---

# Coding Standards

## PHP

Target latest stable PHP.

Use:

- readonly
- constructor promotion
- enums
- intersection types
- union types

Avoid:

- dynamic properties
- mixed
- magic arrays
- global state

---

## Declaration order

Within a class, members follow a fixed, enforceable order (mirrors the
`ordered_class_elements` php-cs-fixer rule):

1. `use` traits
2. constants
3. properties
4. constructor
5. public static factories / helpers
6. public instance methods
7. protected static
8. protected instance methods
9. private static
10. private instance methods

The enforceable approximation is **public statics first, then instance methods,
then protected, then private** — named constructors (factories) therefore sit
with the other public statics, above instance methods. Keep it consistent.

Global symbols (`\count`, `\sprintf`, `\is_string`, …) are imported via
`use function` (`global_namespace_import`), so they are referenced unqualified
and alphabetically ordered with the other imports.

Yoda conditions (`5 === $x`, not `$x === 5`) are enforced.

## Vocabulary home

String vocabularies have a single, intentional home so call sites stop leaking
raw literals:

- A **single-use** literal becomes a `private const` / `class const` on the
  class that owns it (e.g. `IncludeProcessor::INCLUDE_SOURCE_PREFIX`,
  `DocumentNormalizer::SECRET_SCHEMES`).
- A **shared / cross-layer** vocabulary becomes an interface constant or a
  `Contract` enum. The canonical example is `OccurrenceType`
  (`ValueObject`): every call site uses `OccurrenceType::…->value` — never the
  raw occurrence string (`'ConfigurationValidated'`, …). The same applies to
  loader/schema/processor identity: they expose `name(): string`
  (`'json-loader'`, `'nette-schema'`, …) instead of `get_class()`.

Rule of thumb: if the same string could appear in two files, it belongs in a
shared `Contract` constant/enum, not duplicated as a literal.

## Instantiation policy

Dependencies are supplied by the composition root; they are never
default-constructed inside another service's constructor (REVIEW NV10, ADR-0012).

- **Injectable collaborators** (services, factories, runners, reporters, wiring
  objects — anything that *does work*) arrive through the constructor from
  `Wiring\DefaultConfigurationWiring`. A constructor parameter must never carry a
  `= new X()` default for such a type. `tests/Unit/Wiring/NoInjectableConstructorDefaultsTest`
  is a tokenizer guard that fails the build if one reappears.
- **Value objects are the carve-out.** Immutable domain data
  (`ConfigurationNode`, `Provenance`, `ValidationMessage`, `Observation`,
  `TypedSnapshot` wrappers, graph instances, scalar/secret vocabularies) *are*
  data, not services; constructing them with `new` at a call site is correct and
  explicitly allowed.
- **Core depends only on `Contract/` + `ValueObject/`.** It must never `use` a
  class from `Wiring/`, `Adapter/`, or `Bridge/`. The `Builder` takes an optional
  `?ConfigurationWiringInterface $wiring = null` and is normally obtained from
  `Wiring\Configurations::builder()`; a null wiring at `build()` time is a
  programming error, not a silent default.
- The composition root (`Wiring/`) is the only place that `new`s `Adapter/` and
  `Bridge/` concretes. deptrac enforces these edges (see
  `docs/architecture/decisions/ADR-0012-Instantiation-Policy.md`).

Prefer domain language.

Good:

ConfigurationSource

ConfigurationPipeline

ConfigurationResolver

ConfigurationSnapshot

ConfigurationSchema

Avoid:

LoaderManager

ConfigHelper

Utility

Common

Misc

---

## Functions

Functions should do one thing.

Prefer many small methods.

Cyclomatic complexity should stay low.

---

## Exceptions

Domain exceptions belong inside Core.

Vendor exceptions should be translated by adapters.

Never leak vendor exceptions.

---

## Testing

Every adapter requires integration tests.

Every Core component requires unit tests.

Tests should describe behavior.

Not implementation.

---

# Package Structure

```
src/

 Contracts/

 Core/

 Builder/

 Pipeline/

 Processor/

 Resolver/

 Schema/

 Source/

 Loader/

 Adapter/

 Bridge/

 Context/

 Discovery/

 Graph/

 Cache/

 Event/

 Exception/

 Support/
```

---

# Future Packages

The project should remain modular.

Potential companion packages:

- configure-it-env
- configure-it-yaml
- configure-it-neon
- configure-it-toml
- configure-it-frontmatter
- configure-it-schema
- configure-it-di
- configure-it-vault
- configure-it-tableschema

---

# AI Agent Guidelines

When contributing to ConfigureIt:

## Prefer Interfaces

New functionality should begin with contracts.

---

## Respect Boundaries

Never introduce vendor code into Core.

---

## Build Adapters

If integrating an external library:

- create a dedicated adapter
- expose Core interfaces
- translate exceptions
- isolate dependencies

---

## Extend Pipelines

Prefer adding processors over modifying existing resolution logic.

---

## Preserve Immutability

Avoid mutable shared state.

Builders are mutable.

Snapshots are not.

---

## Avoid Framework Bias

ConfigureIt is framework-neutral.

Do not optimize solely for Symfony, Laravel, or any single ecosystem.

---

## Favor Extensibility

Assume every component will eventually have:

- multiple implementations
- decorators
- caching
- instrumentation
- custom processors

Design accordingly.

---

# Long-Term Goals

- Universal configuration abstraction for PHP
- Graph-based configuration model
- Pluggable resolution pipeline
- Rich provenance tracking
- Explainable configuration
- Semantic validation
- Configuration visualization
- IDE-friendly diagnostics
- Native OpenTelemetry instrumentation
- Zero framework lock-in

---

# Motto

> **Configuration is Data.**
>
> **Sources are Adapters.**
>
> **Schemas define Truth.**
>
> **Pipelines create Knowledge.**
>
> **Snapshots power Applications.**