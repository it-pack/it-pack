# OBSERVABILITY.md

# ConfigureIt Observability

> **Every configuration operation should be observable, explainable, and reproducible.**

---

# Purpose

Observability is a first-class architectural concern in ConfigureIt.

Every significant operation performed during configuration discovery, loading,
resolution, validation and snapshot creation should be observable through
standard, vendor-neutral APIs.

ConfigureIt adopts:

- **OpenTelemetry** as its observability standard
- **CloudEvents** as its event envelope
- **PSR-3** for logging
- **PSR-14** for optional in-process events

The Core depends only on the **OpenTelemetry API**.

The SDK remains an optional development dependency.

---

# Philosophy

Configuration is often the least observable part of an application.

When something goes wrong, developers frequently ask:

- Why was this file loaded?
- Which environment variable won?
- Why wasn't this configuration used?
- Which schema rejected the document?
- Which source overrode another?
- Why is this value different in production?

ConfigureIt should always be able to answer these questions.

---

# Design Principles

## API First

Core depends on

```
otel/api
```

only.

Never depend on

```
otel/sdk
```

inside the Core.

Applications choose their own SDK.

---

## Vendor Neutral

No exporter should ever be required.

Examples:

- OTLP
- Jaeger
- Zipkin
- Tempo
- Honeycomb
- Grafana Cloud

should all work without ConfigureIt changes.

---

## Structured Events

Every significant lifecycle step produces a structured event.

CloudEvents provides the canonical envelope.

---

## Zero Configuration

Observability should be optional.

Without an SDK installed:

- no failures
- no exceptions
- minimal overhead

---

## Explainability

Observability is not only for operators.

It is also a debugging tool.

Every observable operation should help explain configuration behavior.

---

# OpenTelemetry

## Dependency Strategy

Core

```
open-telemetry/api
```

Development

```
open-telemetry/sdk
```

Example

```text
configure-it

├── requires
│     otel/api
│
└── require-dev
      otel/sdk
```

---

# Tracing

Every configuration resolution creates a root span.

Example

```
Configuration Resolution

├── Discovery

├── Reading

├── Loading

├── Parsing

├── Validation

├── Merge

├── Variable Resolution

├── Graph Building

└── Snapshot Creation
```

Nested processors become child spans.

---

## Example Span Hierarchy

```
configure.resolve

    configure.discover

    configure.read

    configure.load.yaml

    configure.load.toml

    configure.validate

    configure.merge

    configure.interpolate

    configure.defaults

    configure.graph

    configure.snapshot
```

---

# Span Attributes

Recommended attributes:

| Attribute | Example |
|------------|----------|
| configure.source | filesystem |
| configure.loader | yaml |
| configure.resource | config/app.yaml |
| configure.schema | AppSchema |
| configure.processor | MergeProcessor |
| configure.context | production |
| configure.environment | prod |
| configure.snapshot.version | 42 |
| configure.cache.hit | true |
| configure.validation.success | true |

---

# Metrics

ConfigureIt should expose metrics for operational insight.

Examples

## Counters

```
configure.documents.loaded

configure.resources.discovered

configure.snapshots.created

configure.schemas.validated

configure.pipeline.executions

configure.cache.hits

configure.cache.misses

configure.validation.errors
```

---

## Histograms

```
configure.discovery.duration

configure.loader.duration

configure.validation.duration

configure.pipeline.duration

configure.snapshot.duration

configure.resolve.duration
```

---

## Gauges

```
configure.active.sources

configure.active.adapters

configure.loaded.documents

configure.graph.nodes

configure.graph.edges
```

---

# Logging

Logging remains implementation-independent.

ConfigureIt should log through PSR-3.

Suggested levels

DEBUG

- discovered resources
- merge decisions
- processor execution

INFO

- snapshot creation
- cache rebuild

NOTICE

- fallback sources

WARNING

- deprecated configuration

ERROR

- validation failures

CRITICAL

- unrecoverable resolution failures

---

# CloudEvents

CloudEvents represent lifecycle events.

Every event should be serializable.

CloudEvents may be published to:

- message brokers
- event buses
- audit systems
- debugging tools
- telemetry collectors

---

## Event Naming

Recommended event types

```
configure.discovery.started

configure.discovery.completed

configure.resource.discovered

configure.reader.completed

configure.loader.completed

configure.document.loaded

configure.pipeline.started

configure.processor.completed

configure.validation.completed

configure.merge.completed

configure.graph.created

configure.snapshot.created

configure.cache.hit

configure.cache.miss

configure.resolve.completed

configure.resolve.failed
```

---

## Example CloudEvent

```json
{
  "specversion": "1.0",
  "id": "9d7c6a5d-...",
  "source": "/configure-it",
  "type": "configure.snapshot.created",
  "subject": "application",
  "time": "2026-07-19T04:20:00Z",
  "datacontenttype": "application/json",
  "data": {
    "environment": "production",
    "documents": 12,
    "graphNodes": 164,
    "durationMs": 37
  }
}
```

---

# Event Lifecycle

```
Discovery Started

↓

Resources Found

↓

Document Loaded

↓

Schema Validated

↓

Pipeline Executed

↓

Graph Created

↓

Snapshot Created

↓

Resolution Completed
```

---

# Ports

Observability should follow the same Ports & Adapters architecture.

## Tracer Port

```
ConfigurationTracer
```

Responsible for

- spans
- attributes
- timing

---

## Metrics Port

```
ConfigurationMeter
```

Responsible for

- counters
- gauges
- histograms

---

## Event Publisher

```
ConfigurationEventPublisher
```

Responsible for

- CloudEvents
- PSR-14
- message buses

---

## Logger Port

```
ConfigurationLogger
```

Wraps PSR-3.

---

# Correlation

Every operation should propagate context.

```
Trace

↓

Span

↓

CloudEvent

↓

Log Entry

↓

Metrics
```

Developers should be able to correlate all emitted telemetry using the active trace context.

---

# Pipeline Instrumentation

Every processor should be observable.

```
MergeProcessor

↓

Span

↓

Metric

↓

CloudEvent
```

Example

```
ValidationProcessor

duration

12ms

status

success

schema

ApplicationSchema
```

---

# Graph Instrumentation

Graph creation should expose useful diagnostics.

Metrics

```
graph.nodes

graph.edges

graph.references

graph.cycles

graph.depth
```

Future versions may include graph visualization exports.

---

# Resolution Report

Every resolution may optionally produce a structured report.

Example

```
Resources

17

Documents

11

Processors

8

Validation Errors

0

Merge Operations

15

Variables Resolved

42

Duration

31ms
```

This report may be exported as:

- JSON
- CloudEvent
- OpenTelemetry Event
- Markdown

---

# Development Mode

When `otel/sdk` is installed, ConfigureIt should provide additional diagnostics.

Examples

- Console Span Exporter
- In-memory Exporter
- Pretty Resolution Report
- Pipeline Timeline
- Graph Statistics
- Resolution Tree

These features are intended for development and testing only.

---

# Testing

Integration tests should verify:

- spans are emitted
- metrics are recorded
- CloudEvents are generated
- trace context is propagated
- telemetry never changes business behavior

Observability must remain non-invasive.

---

# Future Directions

Potential future integrations include:

- OpenTelemetry Logs API
- OpenFeature (feature flag context during resolution)
- Dapr pub/sub for distributed CloudEvents
- Apache Kafka / RabbitMQ event publishing
- Prometheus exporters
- Grafana dashboards
- Jaeger and Tempo trace visualizations
- Configuration provenance timelines
- Semantic graph instrumentation

---

# Guiding Principle

> **Configuration should never be a black box.**

Every configuration value should be traceable.

Every transformation should be observable.

Every decision should be explainable.

Every lifecycle event should be reproducible.

---

# Reference Stack

| Concern | Standard |
|----------|----------|
| Tracing | OpenTelemetry API |
| Metrics | OpenTelemetry Metrics API |
| Context Propagation | OpenTelemetry Context API |
| Events | CloudEvents 1.0 |
| Logging | PSR-3 |
| In-process Events | PSR-14 |
| Development SDK | OpenTelemetry SDK |
| Architecture | Ports & Adapters |

---

## Example Resolution Flow

```text
┌──────────────────────────────────────────────────────┐
│ configure.resolve (Root Span)                        │
├──────────────────────────────────────────────────────┤
│                                                      │
│  discover resources                                 │
│      │                                               │
│      ├── CloudEvent                                 │
│      ├── Span                                       │
│      └── Counter                                    │
│                                                      │
│  read documents                                     │
│      │                                               │
│  parse loaders                                      │
│      │                                               │
│  validate schemas                                   │
│      │                                               │
│  execute processors                                 │
│      │                                               │
│  build graph                                        │
│      │                                               │
│  create snapshot                                    │
│      │                                               │
│  publish configure.snapshot.created                 │
│      │                                               │
│  end root span                                      │
└──────────────────────────────────────────────────────┘
```