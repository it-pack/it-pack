# AsyncIt Examples

Enterprise-grade, fully runnable examples for the [it-pack/async-it](https://github.com/Kilo-Org/kilocode) scheduling and progression engine.

Each example is a standalone PHP script under `examples/` that can be executed directly:

```bash
php examples/01-hello-world.php
```

All examples use the reference runtime assembly (SelectPoller + EmulatedChannel + MemoryContextStorage + SystemMonotonicClock) via `examples/bootstrap.php`, so they run identically regardless of which async extensions (Swoole, Swow, Revolt) are installed.

## Example index

| File | Category | What it demonstrates |
|------|----------|---------------------|
| `01-hello-world.php` | Developer Experience | Simplest possible `AsyncRuntime::run()` returning a value |
| `02-structured-concurrency.php` | Vertical | `spawn()` + `await()`, `TaskGroup` with `go()`/`await()`, `maxConcurrency` backpressure |
| `03-cancellation.php` | Vertical | `CancellationScope`: `cancel()`, `isCancelled()`, `throwIfCancelled()`, `onCancel()` cleanup |
| `04-timers-delays.php` | Vertical | `awaitDelay()` with monotonic `Duration`, timer heap ordering, deterministic clock |
| `05-channels.php` | Vertical | `TaskChannelInterface`: `send()`/`receive()`, `trySend()`, `close()`, bounded buffer, exceptions |
| `06-resilience.php` | Vertical | `RetryPolicy` (exponential backoff, jitter), `CircuitBreaker` (closed→open→half-open→closed) |
| `07-bulkhead-rate-limiter.php` | Vertical | `Bulkhead` (concurrency isolation), `RateLimiter` (token bucket throttling) |
| `08-capability-negotiation.php` | Architectural | `CapabilityDescriptor`, `CapabilityKind`/`CapabilityStatus`, `EngineCapability` |
| `09-health-telemetry.php` | Horizontal | `SchedulerHealth` snapshot, `TelemetryBridge` event recording |
| `10-graceful-shutdown.php` | Horizontal | `GracefulShutdown`: root scope cancellation, reactor drain, deadline, signal handlers |
| `11-enterprise-workflow.php` | Vertical + Horizontal | Combined order-processing pipeline: fan-out, bulkhead, channel, cancellation, retries |
| `12-context-isolation.php` | Vertical / Security | `MemoryContextStorage` attach/detach protocol, LIFO token stack, no context bleed |

## Architecture notes

All examples live under `examples/` in the `it-pack/async-it` package and are fully runnable with:

```bash
php examples/<file>.php
```

Each example:

- Starts with `<?php declare(strict_types=1);`
- Bootstraps a reference `AsyncRuntime` via `require __DIR__ . '/bootstrap.php'`
- Uses only public AsyncIt and RunIt contracts
- Prints its results to stdout for verification

The `bootstrap.php` helper constructs the reference runtime manually (rather than `AsyncRuntime::discover()`) so examples are portable across environments with or without Swoole/Swow/Revolt installed.

## Vocabulary alignment

Every public concept used in these examples maps to the AsyncIt scheduling vocabulary:

- Ontology (`vocab/async-it.ttl`): `Task`, `Scheduler`, `CancellationScope`, `TaskGroup`, `CircuitBreaker`, `Bulkhead`, `RateLimiter`, `RetryPolicy`, `CapabilityDescriptor`, `TaskChannel`, `Poller`
- Taxonomy (`vocab/async-it-taxonomy.ttl`): `task-kind`, `capability-key`, `cancellation-reason`, `retry-strategy`, `jitter-strategy`, `backpressure-policy`, `runtime-adapter-kind`
- Consumed RunIt terms: `Execution`, `Context`, `Duration` (monotonic)

See `docs/background/` for the ADRs and capability spec governing these APIs.
