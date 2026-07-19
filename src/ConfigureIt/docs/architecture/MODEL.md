# MODEL.md

# ConfigureIt Domain Model

> **The ubiquitous language of ConfigureIt.**

---

# Purpose

This document defines the core domain concepts used throughout ConfigureIt.

Every class, interface, package, document, issue, pull request, and architectural discussion should use the terminology defined here.

The goal is to establish a consistent language shared by developers, users, documentation, and AI agents.

If a concept is not defined here, it should probably not exist in the Core.

---

# Domain Philosophy

ConfigureIt models **configuration as a domain**, not as nested arrays or parsed files.

The lifecycle of configuration is:

```text
Source
    ↓
Resource
    ↓
Reader
    ↓
Loader
    ↓
Document
    ↓
Pipeline
    ↓
Graph
    ↓
Snapshot
    ↓
Application
```

Each stage has a clearly defined responsibility.

---

# Core Concepts

## Configuration

A logical collection of related configuration values.

Configuration is independent of:

- file formats
- storage locations
- transport protocols
- frameworks

Configuration is a domain object.

---

## Configuration Value

A single resolved value within a configuration.

Examples:

```text
database.host

cache.ttl

mail.sender

app.debug
```

Values may contain metadata.

---

## Configuration Resource

A resource that contains configuration.

Examples:

- File
- Directory
- URL
- Environment
- Stream
- Secret
- Database Record

Resources are not parsed.

They simply exist.

---

## Configuration Source

A source discovers and retrieves configuration resources.

Examples:

```text
FilesystemSource

EnvironmentSource

CliSource

RedisSource

VaultSource

HttpSource
```

Responsibilities:

- discovery
- ordering
- resource enumeration

A source never parses data.

---

## Configuration Reader

A reader converts a resource into raw content.

```text
File

↓

Reader

↓

String
```

Examples:

```text
FileReader

HttpReader

RedisReader

VaultReader
```

Readers know **how to read**.

They do not understand formats.

---

## Configuration Loader

A loader parses raw content.

```text
String

↓

Loader

↓

ConfigurationDocument
```

Examples:

```text
YamlLoader

JsonLoader

TomlLoader

PhpLoader

NeonLoader
```

A loader understands syntax.

Nothing else.

---

## Configuration Document

A parsed representation of configuration.

Documents are independent from:

- file format
- storage
- transport

Every loader produces documents.

Every processor consumes documents.

---

## Configuration Pipeline

The ordered sequence of processors that transforms configuration.

Typical stages:

```text
Normalize

↓

Merge

↓

Validate

↓

Resolve

↓

Freeze
```

Pipelines should be deterministic.

---

## Configuration Processor

A processor performs exactly one transformation.

Examples:

```text
MergeProcessor

VariableProcessor

IncludeProcessor

DefaultsProcessor

ValidationProcessor

FreezeProcessor
```

Processors should be:

- composable
- independent
- replaceable

---

## Configuration Resolver

Coordinates the entire resolution process.

Responsible for producing the final configuration graph.

It does not parse.

It orchestrates.

---

## Configuration Schema

Defines constraints for configuration.

Examples:

- required fields
- types
- defaults
- ranges
- enums

Schemas validate documents.

Schemas do not load configuration.

---

## Configuration Graph

The internal semantic representation of resolved configuration.

Unlike arrays, graphs preserve relationships and metadata.

Nodes contain:

- value
- provenance
- schema
- source
- priority
- diagnostics

The graph is implementation-independent.

---

## Configuration Node

Represents one logical configuration value.

Example:

```text
database.host
```

Metadata may include:

```text
Source

Priority

Schema

Origin

Default

Override

Validation Status

Timestamp
```

---

## Configuration Edge

Represents a relationship between nodes.

Examples:

```text
database

↓

host

database

↓

credentials
```

Future versions may include semantic relationships.

---

## Configuration Snapshot

An immutable runtime representation of resolved configuration.

Applications consume snapshots.

Snapshots never change.

If configuration changes, a new snapshot is produced.

---

## Configuration Context

Defines the scope in which configuration is resolved.

Examples:

```text
Application

Environment

Module

Package

Plugin

Tenant

Workspace

Feature
```

Contexts isolate configuration.

---

## Configuration Builder

Provides a fluent API for constructing a configuration pipeline.

Builders are mutable.

Snapshots are not.

---

## Configuration Discovery

The process of locating configuration resources.

Examples:

```text
config/

config/packages/

config/local/

config/modules/
```

Discovery precedes loading.

---

## Configuration Merge

Combines multiple configuration documents.

Strategies include:

- Replace
- Deep Merge
- Recursive
- Append
- Unique
- First Wins
- Last Wins

Merge strategy is explicit.

---

## Configuration Resolution

The complete process that transforms raw resources into a snapshot.

Resolution includes:

- loading
- normalization
- validation
- interpolation
- merging
- defaults
- graph creation
- snapshot generation

---

## Variable Resolution

The process of resolving placeholders.

Examples:

```text
${env(APP_ENV)}

${database.host}

${vault(secret)}

${baseDir}
```

Variable resolution is performed by processors.

---

## Configuration Adapter

An implementation of a Port that integrates external infrastructure.

Examples:

```text
YamlAdapter

DotenvAdapter

TomlAdapter

AuraDiAdapter
```

Adapters isolate third-party libraries.

---

## Configuration Bridge

Provides optional integration with external ecosystems.

Examples:

```text
Symfony

Laravel

Aura.DI

PHP-DI
```

Bridges are optional.

Core never depends on bridges.

---

## Configuration Cache

Stores intermediate or resolved configuration.

Examples:

```text
Filesystem

APCu

Redis

Memory
```

Caching is an implementation concern.

---

## Configuration Event

Represents lifecycle events.

Examples:

```text
ConfigurationLoaded

ConfigurationMerged

ConfigurationValidated

ConfigurationResolved

SnapshotCreated
```

Useful for logging and observability.

---

# Relationships

```text
ConfigurationSource
        │
discovers
        ▼
ConfigurationResource
        │
read by
        ▼
ConfigurationReader
        │
produces
        ▼
Raw Content
        │
parsed by
        ▼
ConfigurationLoader
        │
creates
        ▼
ConfigurationDocument
        │
processed by
        ▼
ConfigurationPipeline
        │
composed of
        ▼
ConfigurationProcessors
        │
produces
        ▼
ConfigurationGraph
        │
frozen into
        ▼
ConfigurationSnapshot
        │
consumed by
        ▼
Application
```

---

# Dependency Rules

| Concept | Depends On |
|----------|------------|
| Resource | Nothing |
| Source | Resource |
| Reader | Resource |
| Loader | Reader Output |
| Document | Loader |
| Processor | Document |
| Pipeline | Processor |
| Resolver | Pipeline |
| Graph | Resolver |
| Snapshot | Graph |

Dependencies always flow downward.

Never upward.

---

# Vocabulary Rules

Use these terms consistently.

Prefer:

- Configuration Source
- Configuration Loader
- Configuration Snapshot
- Configuration Pipeline
- Configuration Graph
- Configuration Context

Avoid generic terms such as:

- Helper
- Utils
- Manager
- Service
- Common
- Misc
- Data
- Handler

Names should communicate domain intent.

---

# Future Concepts

The following concepts are intentionally reserved for future versions.

## Provenance

Tracks the origin of every value.

---

## Diagnostics

Explains why a value has a specific state.

---

## Explainability

Allows answering questions such as:

> Why is `database.host` equal to `localhost`?

---

## Semantic Graph

Represents configuration as a navigable knowledge graph.

---

## Graph Diff

Compares two configuration graphs.

---

## Live Resolution

Supports hot-reloading and incremental graph updates.

---

## Observability

Native OpenTelemetry instrumentation for:

- discovery
- loading
- validation
- pipeline execution
- resolution
- caching

---

# Design Principles

The domain model should remain:

- Small
- Cohesive
- Explicit
- Stable
- Framework-agnostic
- Strongly typed
- Extensible

New concepts should only be introduced if they represent a genuine domain abstraction rather than an implementation detail.

---

# Ubiquitous Language

> **Resources contain configuration.**
>
> **Sources discover resources.**
>
> **Readers retrieve content.**
>
> **Loaders parse documents.**
>
> **Processors transform documents.**
>
> **Pipelines orchestrate processors.**
>
> **Resolvers build graphs.**
>
> **Graphs become snapshots.**
>
> **Applications consume snapshots.**