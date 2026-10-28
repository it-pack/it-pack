# EXECUTION.md

# It_Pack Execution Model

> **Execution is the root domain. Context, activities, observations, measurements and events are different views of an execution.**

---

# Purpose

This document defines the execution model shared across the It_Pack ecosystem.

Rather than modelling observability, configuration or tracing independently, It_Pack models **execution itself**.

Everything that happens during runtime belongs to an execution.

Examples include:

- configuration resolution
- dependency injection
- workflow execution
- event processing
- queue workers
- HTTP requests
- CLI commands
- schedulers
- LLM pipelines

Every package should build upon this common model.

---

# Philosophy

Execution is the umbrella concept.

Everything else is merely metadata describing an execution.

Instead of:

```
OpenTelemetry

↓

Trace

↓

Span
```

we model

```
Execution

├── Context

├── Activity

├── Measurement

├── Observation

├── Occurrence

└── Snapshot
```

OpenTelemetry becomes an adapter.

CloudEvents become an adapter.

PSR-3 becomes an adapter.

---

# Execution

Execution represents one logical runtime operation.

Examples

```
HTTP Request

CLI Command

Queue Job

Configuration Resolution

Workflow

Background Worker

Cron Job

Build Process

Migration
```

Everything belongs to an execution.

---

# Execution Context

Context describes the immutable state surrounding an execution.

Examples

```
Environment

Tenant

Workspace

Locale

Principal

Feature Flags

Configuration Snapshot

Correlation ID

Trace ID
```

Context answers

> Where am I executing?

---

# Activity

Activities describe work performed during an execution.

Examples

```
Resolve Configuration

Validate Schema

Merge Documents

Load YAML

Download Secrets
```

Activities may be nested.

```
Execution

↓

Activity

↓

Activity

↓

Activity
```

An Activity maps naturally to an OpenTelemetry Span but is intentionally not defined in those terms.

Activities answer

> What is currently happening?

---

# Measurement

Measurements capture quantitative information.

Examples

```
Duration

Memory

CPU

Files Loaded

Documents Parsed

Variables Resolved

Graph Nodes

Validation Errors
```

Measurements answer

> How much?

---

# Observation

Observations capture qualitative information.

Examples

```
Log Messages

Warnings

Validation Diagnostics

Debug Messages

Resolution Reports

Errors
```

Observations answer

> What did we notice?

---

# Occurrence

Occurrences represent immutable facts that happened.

Examples

```
Configuration Loaded

Snapshot Created

Schema Validated

Secrets Retrieved

Pipeline Completed
```

Occurrences answer

> What happened?

CloudEvents are one possible representation.

---

# Snapshot

Snapshots represent immutable state after execution.

Examples

```
Configuration Snapshot

Execution Snapshot

Pipeline Snapshot

Workflow Snapshot
```

Snapshots answer

> What is the resulting state?

---

# Relationships

```
Execution
    │
    ├──────────────┐
    ▼              ▼
Context        Activity
                    │
                    ▼
            Measurements
                    │
                    ▼
             Observations
                    │
                    ▼
             Occurrences
                    │
                    ▼
               Snapshot
```

---

# Adapter Mapping

| Execution Model | OpenTelemetry | CloudEvents | PSR |
|-----------------|---------------|-------------|-----|
| Execution | Trace | Event Stream | — |
| Context | Context | Extensions | — |
| Activity | Span | — | — |
| Measurement | Metric | Data | — |
| Observation | Log Record | Data | PSR-3 |
| Occurrence | Span Event / Event | CloudEvent | PSR-14 |
| Snapshot | — | Event Payload | — |

Notice that no external standard defines the domain model.

Instead, external standards implement portions of it.

---

# ConfigureIt Example

```
Execution

Configuration Resolution

        │

        ▼

Execution Context

Environment

↓

Tenant

↓

Snapshot

↓

Feature Flags

        │

        ▼

Activities

Discover Files

↓

Read Resources

↓

Load YAML

↓

Merge

↓

Validate

↓

Resolve Variables

↓

Build Graph

↓

Freeze Snapshot

        │

        ▼

Measurements

Duration

Files

Memory

Graph Nodes

        │

        ▼

Observations

Warnings

Diagnostics

Debug Information

        │

        ▼

Occurrences

Configuration Loaded

Schema Validated

Snapshot Created
```

---

# Future Packages

```
it-pack/run-it
```

Core semantic model.

```
it-pack/run-it-context

it-pack/run-it-otel

it-pack/run-it-cloudevents

it-pack/run-it-psr

it-pack/run-it-logger

it-pack/run-it-metrics

it-pack/run-it-tracing
```

Each package implements one concern.

---

# Design Principles

Execution owns context.

Execution owns activities.

Execution owns observations.

Execution owns measurements.

Execution owns occurrences.

Execution produces snapshots.

External standards are adapters.

---

# Tooling Standard

Every it-pack package converges on one QA / tooling stack (see
`docs/CONSOLIDATION_PLAN.md`):

- **Tests:** Codeception (the `Unit` suite subclasses `PHPUnit\Framework\TestCase`).
- **Architecture / dependency verification:** phparkitect (replaces deptrac).
- **Dev baseline:** `just-bases/*` for phpstan / php-cs-fixer / infection pins.
- **Shared templates:** `tools/qa/` holds the canonical configs that every
  package copies or includes.

## QA lane contract

Every package with source code exposes these composer scripts (the lead
packages already do; migrants align to them):

| Script | Command | Notes |
|---|---|---|
| `test` | `vendor/bin/codecept run` | All suites. |
| `test:unit` | `vendor/bin/codecept run Unit` | Fast dev-loop subset. |
| `cs:check` | `vendor/bin/php-cs-fixer fix --dry-run --diff` | |
| `cs:fix` | `vendor/bin/php-cs-fixer fix` | |
| `analyse` | `vendor/bin/phpstan analyse` | |
| `architecture` | `vendor/bin/phparkitect` | Replaces deptrac. |
| `mutation` | `vendor/bin/infection --min-msi=<pkg-appropriate>` | Codeception adapter. |
| `quality` | `cs:check` → `analyse` → `architecture` → `test` → `mutation` → `security:audit` | Single green gate. |

Optional but encouraged: `security:audit` (`composer audit --abandoned=report`),
`lint:length`, `lint:types`.

## Root orchestration

The monorepo root provides unified entry points:

- **`composer.json`** (root, scripts-only): `composer quality`, `composer test`,
  `composer cs:check`, `composer analyse`, `composer architecture`, `composer
  mutation` — all delegate to the Makefile.
- **`Makefile`**: `make quality` runs `composer quality` in every package that
  defines it (currently 15 packages) plus lane aliases (`make test`, `make
  cs-check`, `make analyse`, etc.). Packages without the lane are silently
  skipped.
- **`.github/workflows/quality.yml`**: 14×2 matrix (14 packages × PHP 8.4/8.5)
  running `composer quality` per package. Fails the build on any lane error.

## Architecture rules

The hexagonal layer contract and the "ports-not-imports" doctrine are encoded
in shared phparkitect helpers (`tools/qa/phparkitect/`):

- `layeredArchitecture($root, $components, $dependencies)` — builds the standard
  Domain / Contract / Application / SDK / Adapter layer rules.
- `forbidSibling($namespace, $forbiddenPrefix, $because)` — forbids a namespace
  from importing a sibling (e.g. a consumer's `Core` must not import
  `ItPack\RunIt\*`; RunIt is wired in via a bridge package instead).

RunIt is the base execution layer. Consumers must not import `ItPack\RunIt\*`
directly; they depend on it through a bridge package (`it-pack/run-it-<consumer>`
or `<consumer>-run-it`).

## PSR posture

The ItPack ecosystem adopts a **tiered PSR policy**: core packages define their
own ports and may reference PSR interfaces only behind an adapter; integration
packages that *exist to provide* a PSR bridge carry the PSR as a hard dependency.

| Posture | Packages | Example |
|---|---|---|
| **Core — PSR-free runtime** | RunIt, CommandIt, DisenchantIt, ConfigureIt, CommitIt, HashIt, DeriveIt | No PSR in `require`. PSR interfaces appear only behind adapters or in `suggest`. |
| **PSR-as-suggest (bring-your-own)** | CommandIt, HashIt, RunIt | PSR package listed in `suggest` with a description of the adapter it enables. |
| **PSR-for-adapter-dev** | RunIt, AsyncIt, HashIt | PSR packages in `require-dev` for adapter test suites; not a runtime dependency. |
| **PSR-adapter package** | ConfigureItPsr11 | The package's sole purpose is to satisfy a PSR (`psr/container`); PSR is a hard `require`. |
| **PSR-as-core-feature** | DeriveIt | `psr/cache` is a hard `require` because caching is core to the package's operation. |

**Policy:** A core package must never take a PSR as a hard `require` unless the PSR
*is* the package's reason for existing (e.g. ConfigureItPsr11). All other PSR
dependencies are either `suggest` (optional integration surface) or `require-dev`
(adapter test suites). Bridge packages may import PSR packages that their target
adapter requires, but must not re-export them as part of their own public API.

---

Execution becomes the semantic backbone of the It_Pack ecosystem.

Every package—from ConfigureIt and ChaosCockpit to AExO, HoppaDB and Spindle—shares the same execution vocabulary, allowing observability, orchestration, diagnostics and configuration to interoperate naturally without coupling to any specific vendor or runtime.

---

# Motto

> **Everything happens within an execution.**
>
> **Execution carries context.**
>
> **Activities perform work.**
>
> **Measurements quantify work.**
>
> **Observations describe work.**
>
> **Occurrences record work.**
>
> **Snapshots preserve work.**