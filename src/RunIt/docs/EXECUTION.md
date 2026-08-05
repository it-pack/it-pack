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

# Vision

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