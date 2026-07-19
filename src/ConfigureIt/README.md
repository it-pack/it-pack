# ConfigureIt

> **Configuration as Data. Resolution as a Pipeline. Adapters for Everything.**

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg)](#)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#)
[![Status](https://img.shields.io/badge/status-Planning-orange.svg)](#)

---

## Overview

**ConfigureIt** is a framework-agnostic configuration SDK and resolution engine for PHP.

Unlike traditional configuration libraries that focus on parsing a single format, ConfigureIt provides a unified abstraction for discovering, loading, validating, merging, transforming, and resolving configuration from virtually any source.

Configuration is treated as a first-class domain model rather than an associative array.

Its architecture is based on:

- SOLID
- Ports & Adapters (Hexagonal Architecture)
- Dependency Inversion
- Immutable Value Objects
- Extensible Resolution Pipelines

The goal is to become the universal configuration foundation for modern PHP applications.

---

## Philosophy

ConfigureIt is built around one simple idea:

> **Configuration is data.**

Everything else is an implementation detail.

Instead of asking:

> "Can this library read YAML?"

ConfigureIt asks:

- Where does this configuration come from?
- How should it be validated?
- How should multiple sources be merged?
- How are variables resolved?
- How should consumers access it?

---

## Features

### Unified Configuration API

Load configuration from many different sources through a single API.

Examples include:

- Files
- Directories
- Environment Variables
- CLI Arguments
- Programmatic Arrays
- HTTP Endpoints
- Secret Stores
- Databases
- Cloud Providers

---

### Multiple File Formats

Supported through optional adapters.

Current roadmap includes:

- JSON
- PHP
- YAML
- TOML
- NEON
- XML
- INI
- Markdown Front Matter
- CSV
- Table Schema

---

### Validation

Configuration can be validated using reusable schemas.

Planned integrations include:

- Nette Schema
- Custom Schema Providers

---

### Resolution Pipeline

Configuration passes through a configurable processing pipeline.

Typical flow:

```text
Sources
    ↓
Readers
    ↓
Loaders
    ↓
Documents
    ↓
Normalization
    ↓
Merge
    ↓
Validation
    ↓
Variable Resolution
    ↓
Defaults
    ↓
Configuration Graph
    ↓
Immutable Snapshot
```

Each stage can be replaced or extended.

---

### Ports & Adapters

The Core never depends on external libraries.

Third-party packages are isolated behind adapters.

Examples:

- Aura.DI
- vlucas/phpdotenv
- nette/schema
- nette/neon
- php-internal/toml
- ext-yaml
- webuni/front-matter
- frictionlessdata/tableschema

---

### Immutable Snapshots

Applications consume immutable configuration snapshots.

Builders and pipelines remain mutable.

Runtime configuration does not.

---

## Example

```php
$config = ConfigureIt::builder()

    ->addSource(
        new FileSource('config')
    )

    ->addSource(
        new EnvironmentSource()
    )

    ->addSource(
        new CliSource()
    )

    ->schema(
        new ApplicationSchema()
    )

    ->build()

    ->resolve();
```

No matter where configuration originates, consumers use the same API.

---

## Architecture

```text
                     Application
                           │
                    ConfigureIt API
                           │
                    Configuration Builder
                           │
                 Resolution Pipeline Engine
                           │
       ┌────────────────────────────────────┐
       │ Sources │ Loaders │ Processors │
       └────────────────────────────────────┘
                           │
                Configuration Graph
                           │
                Immutable Configuration
                     Snapshot
```

---

## Planned Packages

The project is intentionally modular.

```text
it-pack/configure-it
```

Core library.

Optional packages:

```text
it-pack/configure-it-env
it-pack/configure-it-yaml
it-pack/configure-it-neon
it-pack/configure-it-toml
it-pack/configure-it-frontmatter
it-pack/configure-it-schema
it-pack/configure-it-di
it-pack/configure-it-tableschema
it-pack/configure-it-vault
```

Applications install only the integrations they need.

---

## Project Structure

```text
src/
├── Adapter/
├── Bridge/
├── Builder/
├── Cache/
├── Context/
├── Contracts/
├── Core/
├── Discovery/
├── Event/
├── Exception/
├── Graph/
├── Loader/
├── Pipeline/
├── Processor/
├── Resolver/
├── Schema/
├── Snapshot/
├── Source/
└── Support/

docs/
├── architecture/
└── decisions/

tests/
```

---

## Guiding Principles

- Framework agnostic
- Dependency inversion
- Strong typing
- Immutability
- Composition over inheritance
- Adapter-based integrations
- Small focused interfaces
- Extensible pipelines
- Explainable configuration
- Zero vendor lock-in

---

## Long-Term Vision

ConfigureIt aims to evolve beyond a configuration loader into a complete **Configuration Platform**.

Future capabilities include:

- Configuration provenance
- Semantic configuration graphs
- Explainable configuration resolution
- Interactive diagnostics
- Configuration diffing
- Graph visualization
- OpenTelemetry instrumentation
- Secret provider integrations
- Multi-environment resolution
- Configuration inspection CLI

---

## Status

The project is currently in the design and architecture phase.

The initial milestone focuses on establishing:

- Core contracts
- Builder API
- Resolution pipeline
- Source abstractions
- Loader abstractions
- Immutable snapshots
- Basic JSON and PHP support

Additional adapters and integrations will be delivered incrementally.

---

## Documentation

- **README.md** — Project overview
- **AGENTS.md** — Guidance for human and AI contributors
- **ARCHITECTURE.md** — Architectural overview
- **DECISIONS.md** — Architectural Decision Records (ADR index)
- **ROADMAP.md** — Project milestones and future direction
- **CONTRIBUTING.md** — Contribution guidelines

---

## Contributing

Contributions, ideas, architectural discussions, and adapter implementations are welcome.

Please read:

- `AGENTS.md`
- `ARCHITECTURE.md`
- `CONTRIBUTING.md`

before opening a pull request.

---

## License

MIT

---

> **Configuration is Data.**
>
> **Sources are Adapters.**
>
> **Schemas define Truth.**
>
> **Pipelines create Knowledge.**
>
> **Snapshots power Applications.**