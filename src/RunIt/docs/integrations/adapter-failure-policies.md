# Adapter failure policies

Every RunIt adapter emits through a single port, `ArtifactPublisherInterface`. That
means adapter failures are handled uniformly: the artifact the adapter failed to
emit is caught by the surrounding publication pipeline, not by the adapter itself.
This page consolidates how each integration behaves when its transport fails and
how that composes with the pipeline's failure policy.

## The pipeline wraps every adapter

A publisher is normally built as
`guard → fail-safe → filter → redact → sample → buffer → sink` (see
[`../architecture/publication.md`](../architecture/publication.md)). The adapter is
the **sink**. Two stages own failure handling:

- `GuardedArtifactPublisher` (`PublicationGuard`) prevents recursive publication and
  routes recursion events to the health reporter.
- `FailSafeArtifactPublisher` (`PublicationFailurePolicyInterface`) decides what
  happens when the sink (the adapter) throws.

The default `PublicationFailurePolicyInterface` is **fail-open**: an adapter error is
swallowed so it never replaces the operation's business result or business
exception. The original `Throwable` from the business operation is always rethrown
by the runner.

## Domain failure policies

| Policy | Behaviour when the sink throws |
|---|---|
| `FailOpenPolicy` (default) | Swallow the error; business result/exception preserved. |
| `FailClosedPolicy` | Rethrow the *original* adapter error unchanged. |
| `CollectAndContinuePolicy` | Forward the error and artifact to a collector; continue. |
| `ReportToFallbackPolicy` | Republish the artifact to a separate fallback publisher; a fallback failure is routed to the health reporter, not re-published. |

## Per-adapter behaviour

### OpenTelemetry

The bridge exposes the exporter as the sink. A failing exporter (network error,
shutdown SDK, dropped span) throws into the fail-safe stage. The bridge never
configures global providers and never retries; batching and flushing happen at the
exporter edge, and the exporter's own activity is recorded as a single batched
operational activity (see [`../architecture/self-instrumentation.md`](../architecture/self-instrumentation.md)),
so a failed export cannot recurse.

### CloudEvents

`Psr18CloudEventSink` performs a single best-effort `POST` with
`application/cloudevents+json`. It does **not** retry, buffer, or add durable
delivery. A failed request throws and is handled by the surrounding publication
failure policy. Delivery is **at-most-once** by default; stronger guarantees are the
transport's responsibility. Oversized events fail closed at serialization time
(`CloudEventMappingPolicy::$maxSerializedBytes`).

### PSR-3 (observation export, logger decorator, log-to-observation bridge)

- `ObservationLoggerPublisher` maps an observation to a PSR-3 log record. If the
  underlying logger throws, the fail-safe policy applies; redaction already ran
  upstream in the pipeline.
- `ExecutionAwareLogger` decorates a logger and passes through unchanged when no
  execution is active; it does not publish, so it has no transport failure of its
  own.
- `ObservationRecordingLogger` records log calls as RunIt observations. It is guarded
  by a `PublicationGuardInterface` so a log → observation → log cycle cannot recurse:
  when publication is already active on the path it returns without recording.

### PSR-14 (occurrence dispatch)

`OccurrencePublisher` wraps each occurrence and dispatches it through the
`EventDispatcherInterface`. If dispatch throws, the configured
`PublicationFailurePolicyInterface` is applied (fail-open to swallow, fail-closed to
rethrow). When no policy is set the publisher rethrows so the caller observes the
dispatcher failure; inside a RunIt pipeline the pipeline already wraps it in a
fail-safe guard. Dispatch is synchronous; stoppable-event behaviour follows the
dispatcher. The occurrence itself stays immutable — only propagation stopping is
mutable.

### PSR-15 / PSR-18 (HTTP)

- `ExecutionMiddleware` runs the handler inside one execution and relies on the
  runner's `finally` to restore the prior context on every path (success, client
  error, server error, thrown failure). A handler `Throwable` is recorded and
  rethrown; the failure policy does not apply because no artifact transport is
  involved.
- `Psr18CorrelationClientDecorator` injects correlation headers into outbound
  requests and passes through unchanged when no execution is active. It does not
  publish artifacts.

### Swoole / OpenSwoole

`ServerExecutionMiddleware` wraps each request in one execution and depends on the
runner's `finally` for scope cleanup; `WorkerLifecycleReporter` reports worker
start/stop/failure as occurrences and accepts `reset`/`flush` hooks for reload
handlers. The adapter never configures a global provider and imposes no retry or
durable-delivery guarantee; delivery semantics are the consumer's responsibility.

### Fiber

The Fiber adapter has no transport of its own; scope isolation and cleanup are the
runner's `finally`. No adapter-specific failure policy applies.

## Recursion prevention

Self-observation (an exporter that records its own export) and fallback reporting
are routed to `InternalHealthReporterInterface` (`CallbackHealthReporter`,
`ErrorLogHealthReporter`), which must never call the guarded publisher. This is what
keeps every adapter safe under the fail-safe policy.

## Choosing a policy

- Default (fail-open) is appropriate for telemetry: losing a span must never fail a
  request.
- Use `FailClosedPolicy` only when an artifact is critical and the caller must know
  the transport failed.
- Use `ReportToFallbackPolicy` when a secondary sink (for example a local log) should
  receive artifacts the primary sink dropped.

Because failures are a pipeline concern, the choice is made once at wiring time and
applies uniformly to OpenTelemetry, CloudEvents, PSR, and any custom sink.

## Related

- [`../architecture/publication.md`](../architecture/publication.md) — the pipeline and
  its stages.
- [`../architecture/security.md`](../architecture/security.md) — redaction before
  external publication.
- [`../architecture/self-instrumentation.md`](../architecture/self-instrumentation.md) —
  recursion guard and health reporter.
- [`psr.md`](psr.md), [`opentelemetry.md`](opentelemetry.md),
  [`cloudevents.md`](cloudevents.md), [`swoole.md`](swoole.md).
