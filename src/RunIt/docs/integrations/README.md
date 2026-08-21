# RunIt integration documentation

RunIt models execution with its own vendor-neutral vocabulary. The integrations
below translate that vocabulary to and from external standards and runtimes. The
core has no mandatory external dependency: every integration is opt-in (see
[`optional-dependencies.md`](optional-dependencies.md)).

## Guides

- [OpenTelemetry](opentelemetry.md) — map executions, activities, measurements, and
  observations to OpenTelemetry signals.
- [CloudEvents](cloudevents.md) — map occurrences to and from CloudEvents 1.0
  (SDK-free).
- [PSR interoperability](psr.md) — PSR-3, PSR-14, PSR-15, PSR-18, PSR-20, and PSR-7
  adapters.
- [Fibers](fibers.md) — per-Fiber scope isolation and a Fiber execution engine.
- [Swoole / OpenSwoole](swoole.md) — coroutine context storage, server middleware,
  and worker lifecycle.

## Cross-cutting

- [Optional dependencies](optional-dependencies.md) — what the core requires and
  what each integration adds.
- [Adapter failure policies](adapter-failure-policies.md) — how adapter transport
  failures compose with the publication pipeline.
- [Compatibility matrix](compatibility-matrix.md) — package, version, and capability
  matrix.

## Architecture

- [Hexagonal architecture](../architecture/hexagonal-architecture.md)
- [Context propagation](../architecture/context-propagation.md)
- [Publication pipeline](../architecture/publication.md)
