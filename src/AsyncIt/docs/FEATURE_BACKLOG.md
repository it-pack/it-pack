# AsyncIt — Next-Gen Enterprise Feature Backlog

> **Status:** Brainstorm / strategic backlog.
> **Scope:** Additional enterprise-grade vertical, horizontal, and architectural features to take `it-pack/async-it` to the next level.
> **Purpose:** Reason about *what* to build next, *why* it matters, *how* it fits the architecture, and *what* the trade-offs are — so future implementation work (by humans or AI agents) starts from shared reasoning rather than ad-hoc invention.
> **Companion docs:** `README.md`, `AGENTS.md`, `docs/BRAINSTORM.md`, `docs/ROADMAP.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/REFACTORING_PLAN.md`, `docs/REVIEW.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002), and the `vocab/async-it*.ttl` ontologies.

---

## 0. Where AsyncIt Stands Today

AsyncIt has completed its core implementation roadmap (M0–M10). The package ships with:

| Layer | Delivered |
|---|---|
| **Domain** | `Task` aggregate (`TaskStatus` matrix with `canTransitionTo()`), `CancellationScope`, `EmulatedChannel`, `FiberScopeSlot`, lifecycle exceptions, `StreamSelectPollerTrait` (shared poller probe). |
| **Contract** | `TaskInterface`, `SchedulerInterface` + `EngineSchedulerInterface`, `PollerPortInterface`, `CancellationScopeInterface`, `CapabilityDescriptor`, `RuntimeDiscoveryInterface`, `TaskChannelInterface`, `TelemetryBridgeInterface`, `PollerPortInterface`, enums (`TaskStatus`, `CapabilityKind`, `CapabilityStatus`, `RuntimeAdapterKind`, `ViolationSeverity`), vocabulary interfaces, `StreamPollHandle`, `TaskId`, `ContextToken`, `ValueObjectInterface`. |
| **Application** | `FiberTaskScheduler` (reference reactor), `SelectPoller`, `NativePoller` (PHP 8.6 `Io\Poll`), `MemoryContextStorage`, `NullArtifactPublisher`, `DeterministicMonotonicClock`, `ReferenceRuntimeDiscovery`, factory assemblers. |
| **SDK** | `AsyncRuntime` (implements `RunIt\ExecutionEngineInterface`), `AsyncRuntimeFactory` (composition root, capability-negotiated). |
| **Adapters** | Swoole, Swow, Revolt (Fiber), ReactPHP interop bridge, Amp interop bridge. |
| **Conformance** | `AsyncRuntimeVerifier` composing `ContextIsolationVerifier` (Assertion A) + `DeterministicCleanupVerifier` (Assertion C); negative-test siblings; fixtures for every runtime. |
| **Quality gates** | PHPStan (max + bleeding edge) = 0 errors; phparckitect = 0 violations; CS-Fixer = 0 violations; line-length = OK. Coverage is the open item (R6.3). |

### What Is *Not* Yet Shipped

The README's "Conceptual Usage Example" shows an intended public surface that the current `AsyncRuntime` class does **not** yet expose:

- **`spawn()`** — spawn a task on the scheduler. Currently `AsyncRuntime::run()` only executes a *single* root operation per call; there is no API to spawn multiple concurrent tasks within one execution.
- **`awaitReadable()` / `awaitWritable()`** — await readiness on a stream handle. Currently `PollerPortInterface` exposes `register()`, `release()`, `await(timeout)`, `activeHandleCount()`, but there are no high-level awaiting primitives that tie poll handles to task suspension.
- **Timer queue** — `FiberTaskScheduler::run()` calls `poller->await(0)` with a zero timeout. There is no timer heap, no deadline scheduling, and no `delay()` / `timeout()` combinator.
- **Structured concurrency** — no task groups/nurseries, no parent→child cancellation cascade, no fan-out/fan-in.
- **Priority scheduling** — FIFO-only runnable queue.
- **Backpressure** — unbounded task queue; channels are bounded-unbounded by parameter but nothing feeds back into spawn rate.
- **Rich observability** — `TelemetryBridgeInterface` is a lazy no-op `ArrayTelemetryRecorder`; no metrics export, no span/trace linkage, no scheduler-internal event publication into RunIt occurrences.

These gaps map directly to the "next level" features catalogued below.

---

## 1. Gap Analysis — Motivating the Backlog

The current implementation is a **correct, minimal, conforming scheduling core**. It proves the mechanical split (`Polling ≠ Scheduling ≠ Semantics`), passes all conformance assertions, and integrates with RunIt as a first-party `ExecutionEngineInterface`.

What it lacks for **enterprise-grade** production use:

| Concern | Current State | Enterprise Need |
|---|---|---|
| **Concurrency** | One root op per `run()`; no spawning | Multi-task workflows, fan-out/fan-in, task groups |
| **Timeouts & deadlines** | No timer queue | SLA enforcement, deadline propagation, graceful degradation |
| **Observability** | Lazy no-op telemetry | Distributed tracing, metrics, alerts, debugging |
| **Resilience** | Basic cancellation only | Retry, circuit breaking, bulkheading, backpressure |
| **Resource governance** | Unbounded queues | Multi-tenant isolation, resource quotas, DoS protection |
| **Operational maturity** | No health/ready/liveness | Container orchestration, graceful shutdown, capacity planning |
| **Developer experience** | Low-level `submit()` / `run()` | High-level combinators (`spawn`, `awaitReadable`, `timeout`, `select`, `join`) |

Each feature below is scored against: **Why it matters**, **What's missing today**, **Suggested approach**, **Architectural fit (Domain/Application/Adapter/SDK)**, **Trade-offs & risks**, and **Priority**.

---

## 2. Vertical Features — AsyncIt Domain Semantics

Vertical features extend AsyncIt's *own* scheduling domain. They deepen the Task/Scheduler/Cancellation/Channel model.

### V.1 Structured Concurrency with Task Groups (Nurseries)

**Why it matters.** Enterprise workflows rarely execute a single root operation. They fan out parallel sub-tasks (call 5 downstream services, aggregate results), require all to complete (or first-failure cancellation), and must propagate cancellation + errors cleanly. Without nurseries, consumers build ad-hoc fan-out on raw `spawn()` + `join` primitives, which almost inevitably leaks handles, loses errors, or deadlocks.

**What's missing.** `AsyncRuntime::run()` accepts one operation. There is no `spawn()` API at all (the README promises it but R15 has not implemented it), and no group construct. `FiberTaskScheduler` has no task-parent/child relationship; cancellation scopes do not nest or cascade.

**Suggested approach.**
- Introduce `TaskGroupInterface` (Domain contract) with `go(callable): TaskInterface`, `await()`, `close()`/`__destruct()` auto-cancellation.
- `TaskGroup` carries a `CancellationScope` as its parent; children observe cancellation at await boundaries. When any child fails, the group cancels all siblings and propagates the **first** failure (or collects all failures depending on policy — `FirstFailure` vs `CollectAll`).
- `AsyncRuntime` gains `spawn(callable)` that submits to a root `TaskGroup` bound to the current execution scope.
- Conformance Assertion A must be extended: prove that a group cancellation does not bleed context between sibling tasks across the suspend/resume boundary.

**Architectural fit.** Domain (`TaskGroup`, `TaskGroupInterface`) + Application (`FiberTaskScheduler` gains a runnable-group queue) + SDK (`AsyncRuntime::spawn()`). New vocabulary: `@see async-it#TaskGroup` (ontology individual) + `@see async-it-taxonomy#task-group-strategy` (taxonomy concept for `FirstFailure` / `CollectAll`).

**Trade-offs & risks.** Nurseries that auto-cancel on scope exit are powerful but surprising if consumers don't understand structured-concurrency semantics. Must ship with a conformance test proving that orphaned un-awaited tasks are cancelled on group exit (the "always cancel" invariant).

**Priority.** HIGH. Unlocks the entire multi-task workflow story; without it AsyncIt can only run single-operation executions.

### V.2 Timer Queue: `delay()`, `timeout()`, Deadline Propagation

**Why it matters.** Every production async system needs timeouts (SLA, circuit-breaker, health-check) and delays (retry backoff, rate-limit windows, polling). Without a timer heap, `FiberTaskScheduler::run()` spins on `poller->await(0)`, which is correct for the reference reactor but cannot express "resume this task at time T."

**What's missing.** No timer heap, no `MonotonicClockInterface`-driven deadline scheduling. The scheduler holds no pending-timer set and does not use the nearest deadline to compute the `await()` timeout.

**Suggested approach.**
- Add `awaitDelay(int $nanoseconds): void` or `awaitDelay(Duration $duration)` as a scheduler port method (or an `AwaitInterface` that the scheduler implements).
- Maintain a timer heap (sorted by absolute monotonic deadline) in the scheduler. On each reactor tick, fire all timers whose deadline ≤ `now()`, resuming each task.
- `timeout(Duration, callable)` wraps an operation: spawn it as a child task, register a timer that cancels the scope; on cancellation, publish a `timed_out` occurrence.
- The `await(timeoutNanoseconds)` call on the poller should use `min(nearestTimer, requestedPollTimeout)` so the reactor wakes at the right time.

**Architectural fit.** Domain (`Task` deadline association, `CancelledForTimeout` reason) + Application (timer heap in `FiberTaskScheduler`) + `SchedulerInterface`/`EngineSchedulerInterface` gains an `awaitDelay`/`timeout` seam. Vocabulary: `@see async-it#Timer` (existing ontology entry), `@see async-it-taxonomy#timeout-strategy`.

**Trade-offs & risks.** Timer heap correctness must be conformance-tested under clock-skew injection (Assertion B: durations free of wall-clock skew). The `DeterministicMonotonicClock` fixture already supports `advance()`, which makes this testable without sleeping.

**Priority.** HIGH. Required for any real-world timeout/retry/resilience pattern.

### V.3 Priority & Fairness-Based Scheduling

**Why it matters.** In mixed-workload systems (request handlers + background cleanup + periodic telemetry flush), low-priority system tasks must not starve foreground user work, and background tasks must not block foreground latency. A pure FIFO queue with no priority is correct but not enterprise-grade.

**What's missing.** Runnable queue is a flat `array<string, TaskRecord>` iterated in insertion order. No priority, no aging, no fair-share.

**Suggested approach.**
- Introduce `SchedulingPolicy` as an enum-valued capability (`Fifo`, `Priority`, `WeightedFair`, `ShortestJobFirst`) — advertised in the `CapabilityDescriptor` and selected by `AsyncRuntimeFactory`.
- `Task` gains an optional `priority: int` (validated range, e.g. -128..127) and an `EnqueueOrder` monotonic sequence for stable tie-breaking.
- The scheduler's runnable queue becomes a `SplPriorityQueue` (or a dedicated heap). Priority-aware `spawn()` accepts a priority hint.
- Aging: demote long-waiting tasks to prevent starvation — configurable via a policy.

**Architectural fit.** Domain (`Task` priority field, `TaskStatus` unchanged) + Application (`FiberTaskScheduler` queue strategy selected by `SchedulingPolicy`) + SDK (`AsyncRuntime::spawn(callable, priority)`). Vocabulary: `@see async-it-taxonomy#scheduling-policy-fifo` / `#scheduling-policy-priority` etc.

**Trade-offs & risks.** Priority inversion (low-priority task holding a resource a high-priority task needs) requires priority inheritance, which is complex. Start with non-preemptive priority + aging; defer inheritance until there is a concrete consumer deadlock report.

**Priority.** MEDIUM. Valuable for mixed workloads but FIFO is acceptable for MVP. Defer until V.1 (task groups) lands, since priority is most meaningful within a group's fan-out.

### V.4 Lifecycle Observability Hooks

**Why it matters.** Production schedulers need to instrument their own behavior: "how many tasks are runnable?", "what is the await-dispatch latency?", "which task is suspended on what handle?". The current `Task` carries no lifecycle-event emission, and the `TelemetryBridgeInterface` is a no-op recorder.

**What's missing.** No lifecycle hooks (`onSpawn`, `onResume`, `onSuspend`, `onComplete`, `onCancel`). `FiberTaskScheduler` emits nothing internally. `TaskChannelInterface` has no observability seam.

**Suggested approach.**
- Introduce a `SchedulerEventListenerInterface` (or reuse RunIt's `Occurrence`/`Observation` publication model) with `onTaskSpawned(TaskInterface)`, `onTaskResumed(...)`, `onTaskSuspended(TaskInterface, awaitPoint)`, `onTaskCompleted(TaskInterface, TaskStatus)`.
- The scheduler publishes these as lightweight RunIt Occurrences (reusing `ArtifactPublisherInterface`) — but **with the recursion guard** (ADR-014) and **fail-open** default, so a telemetry failure never stalls the reactor.
- `awaitPoint` is a discriminated union (`AwaitPointReadable`, `AwaitPointDelay`, `AwaitPointChannel`, etc.) so dashboards can show "suspended on: timer / IO / channel".

**Architectural fit.** Application (scheduler emits events) + Domain (await-point value objects) + Reuse RunIt's `ArtifactPublisherInterface` port (already wired through `FailSafeArtifactPublisher`). Vocabulary: `@see async-it-taxonomy#occ-task-spawned`, `#occ-task-suspended`, `#occ-task-completed`.

**Trade-offs & risks.** Must be zero-cost when no listener is attached (AGENTS.md: "Instrument batch/flush boundaries rather than every internal scheduler method"). Use a `NullSchedulerEventEmitter` no-op by default; only materialise the publisher when a consumer attaches a listener.

**Priority.** MEDIUM. High value for debugging, medium implementation risk if not gated by capability.

### V.5 Advanced Cancellation: Deadlines, Reasons, Scopes

**Why it matters.** Enterprise cancellation needs more than a boolean. "This task was cancelled" must carry *why* (deadline exceeded, parent cancelled, explicit user cancel, resource quota exceeded). Downstream systems need structured cancellation reasons for compensating actions.

**What's missing.** `CancellationScope` is a simple boolean flag with `onCancel()` callbacks. No reason, no deadline, no structured reason propagation.

**Suggested approach.**
- `CancellationScope` gains a `CancellationReason` enum: `UserInitiated`, `DeadlineExceeded`, `ParentCancelled`, `ResourceQuotaExceeded`, `SystemShutdown`.
- `CancellationScopeInterface::cancel(CancellationReason $reason)` — reason is immutable once set.
- `throwIfCancelled()` throws `CancellationException` carrying the reason.
- `timeout()` (V.2) sets `DeadlineExceeded` as the reason.

**Architectural fit.** Domain (`CancellationScope`, `CancellationReason` enum). Vocabulary: `@see async-it#CancellationReason` (ontology individual) + `@see async-it-taxonomy#cancel-reason-deadline-exceeded`.

**Trade-offs & risks.** Adding a parameter to `cancel()` breaks the existing `CancellationScopeInterface`. This is a backward-compatibility concern for post-1.0.0 (AGENTS.md: "Before 1.0.0, breaking changes may be necessary but must be deliberate and documented"). Mitigate with a deprecation cycle: `cancel(bool $cascade = true)` → `cancel(?CancellationReason $reason = null)`.

**Priority.** MEDIUM. Needed for structured cancellation but not blocking MVP.

### V.6 Backpressure & Flow Control

**Why it matters.** Without backpressure, a fast upstream can overwhelm a slower downstream, exhausting memory (unbounded task queue) or file descriptors (unbounded handles). Enterprise systems need bounded queues, explicit backpressure signals, and load-shedding under saturation.

**What's missing.** `FiberTaskScheduler` accepts unlimited `submit()` calls. `EmulatedChannel` is bounded-only-by-constructor-parameter but has no backpressure signal to producers. No queue-depth feedback into spawn rate.

**Suggested approach.**
- `TaskGroup::go()` blocks (suspends) the caller when the group's in-flight task count exceeds a configurable `maxConcurrency`.
- `TaskChannelInterface` gains a `trySend(): bool` non-blocking variant and a `waitForCapacity(Duration)` blocking variant; producers can choose fail-fast vs wait vs shed.
- Scheduler emits a `SchedulerEvent\QueuePressure` event when runnable-queue depth exceeds a threshold (feeds into V.4 observability).

**Architectural fit.** Domain (`BackpressurePolicy` enum: `Block`, `Drop`, `Error`) + Application (scheduler enforces `maxConcurrency`; channel enforces capacity) + SDK (`AsyncRuntime::spawn(callable, options)`).

**Trade-offs & risks.** Backpressure that blocks the spawning task creates potential for deadlock (both producer and consumer are tasks in the same scheduler). Must document the "don't block-and-wait on same scheduler" invariant or implement a work-stealing fallback. Start with `Block` policy (simplex: suspend spawn until queue drains) and defer `Drop`/`Error` until a concrete consumer needs them (AGENTS.md: "Do not add a public extension point until there is a concrete consumer").

**Priority.** MEDIUM-HIGH. Required for stable long-running services; can be introduced as an opt-in policy without breaking the minimal core.

### V.7 Retry with Exponential Backoff & Circuit Breaking

**Why it matters.** Distributed systems fail transiently (network blips, DB connection blips). Enterprise workloads need structured retry with backoff, jitter, and circuit-breaking to avoid cascading failures and thundering herds.

**What's missing.** No retry combinator. No circuit breaker. `CancellationScope` has no deadline concept (V.5 needed first).

**Suggested approach.**
- `retry(callable, RetryPolicy)` — wraps a task; on `Throwable`, checks if retryable (predicate), waits `backoff` (with jitter), retries up to `maxAttempts`; gives up → throws original.
- `RetryPolicy`: `maxAttempts`, `baseDelay`, `maxDelay`, `jitter (full/random)`, `retryable(Exception:class)`.
- `CircuitBreaker` as a separate Domain value object: `Closed → Open → HalfOpen → Closed/Fail`. Tracks consecutive failures, opens on threshold, retries one probe in HalfOpen.
- Both leverage `delay()` (V.2) for backoff waits.

**Architectural fit.** Domain (`RetryPolicy`, `CircuitBreaker`, `CircuitState` enum) + Application (scheduler executes retried tasks as child groups) + SDK (`AsyncRuntime::retry(...)`). Vocabulary: `@see async-it-taxonomy#retry-strategy-exponential`, `#circuit-breaker-state-open`.

**Trade-offs & risks.** Retry must rethrow the *original* exception after exhaustion (AGENTS.md: "Preserve the original business exception"). Circuit-breaker state is inherently shared/mutable — must be per-channel or per-target, not global. Start with per-task retry (no shared circuit) and add circuit breaker as a separate Domain component with a conformance test.

**Priority.** LOW-MEDIUM. Only needed when AsyncIt is used for I/O-heavy distributed workloads. Defer until V.2 (timers) + V.1 (task groups) exist.

### V.8 Work-Stealing & Multi-Worker Scheduling

**Why it matters.** PHP Fibers are single-threaded by nature. But enterprise systems use process pools (RoadRunner, Swoole worker pools, `fork()`) for CPU-bound parallelism. AsyncIt should support multiple worker schedulers sharing a poll context, with work-stealing to balance load.

**What's missing.** Single `FiberTaskScheduler` per execution. No worker pool, no task handoff.

**Suggested approach.**
- `WorkerPool` (Application): N `FiberTaskScheduler` instances, each with its own task queue, sharing one `PollerPort`.
- Work-stealing: when a worker's queue is empty, it steals from the busiest peer's queue (requires queue introspection or a shared stealable ring buffer).
- `TaskAffinity`: optional pinning of a task to a specific worker (for cache locality or resource affinity).

**Architectural fit.** Application (`WorkerPool`, `WorkStealingQueue`) + Domain (`TaskAffinity`). Does *not* change scheduling semantics per AGENTS.md — adapters map to the runtime's model.

**Trade-offs & risks.** Work-stealing in pure PHP has overhead (locking, atomic queues). PHP does not have true shared-memory atomics without extensions. Start with a **round-robin dispatcher** (no stealing) and add stealing only if a concrete multi-core benchmark shows imbalance. This is a hard problem; defer to Phase 2 of the roadmap.

**Priority.** LOW. PHP's single-threaded Fiber model means the primary gain is CPU-bound parallelism via processes, which is an integration concern (RoadRunner/Swoole worker pool), not a scheduler-core concern.

---

## 3. Horizontal Features — Ecosystem & Cross-Cutting

Horizontal features span the broader ItPack ecosystem, touch external systems, and provide cross-cutting infrastructure concerns.

### H.1 OpenTelemetry / PSR-3 Tracing Bridge

**Why it matters.** Enterprise observability stacks (Datadog, New Relic, Grafana Tempo, Honeycomb) all speak OpenTelemetry. Without OTLP trace export, AsyncIt tasks are invisible to distributed tracing. Correlation IDs must flow through Fiber suspension/resume boundaries.

**What's missing.** `TelemetryBridgeInterface` is a `LazyTelemetryBridge` wrapping an `ArrayTelemetryRecorder` (in-memory, no export). No span linkage, no trace-context propagation across `Fiber::suspend()`.

**Suggested approach.**
- `OpenTelemetryTelemetryBridge` (Adapter) implementing `TelemetryBridgeInterface`: creates a span on task spawn, resumes span context on `Fiber::resume()`, ends span on terminal transition.
- Reuses `open-telemetry/api` + `open-telemetry/sdk` (already in `require-dev`, move to `suggest` or `require` for the adapter).
- Correlation ID: stored in the RunIt `ExecutionContext` (deny-by-default, `secret` classification) and propagated via W3C `traceparent` header on outbound I/O.
- The bridge is **lazy** (like `LazyTelemetryBridge`) — only materialises the OTLP exporter + span processor on the first `record()` call.

**Architectural fit.** Adapter (`Adapter/OpenTelemetry/OpenTelemetryTelemetryBridge`) + SDK (`AsyncRuntimeFactory` wires it as the default bridge when OTel extension is detected). Must respect AGENTS.md: "Public APIs coupled to one runtime adapter" is *avoided* — the bridge is selected by capability negotiation, not hardcoded.

**Trade-offs & risks.** Span propagation across `Fiber::suspend()` requires context attachment (Assertion A guarantees this — the RunIt scope is re-attached before resume). But the OTLP exporter must **never** run on the scheduler hot path (AGENTS.md: "Avoid self-instrumentation that materially changes hot-path behavior"). Use a `BatchSpanProcessor` with async export.

**Priority.** HIGH. Without it, AsyncIt is invisible to modern observability stacks — a blocker for production adoption.

### H.2 RunIt Activity Publication: Occurrences, Measurements, Observations

**Why it matters.** AsyncIt must publish its scheduler events back into the RunIt artifact stream as `Occurrence`s (task spawned, resumed, completed), `Measurement`s (task duration, await-suspend latency, poll-wait time), and `Observation`s (anomalies: queue pressure, handle leaks, starvation). This is the "RunIt integration" horizontal — AsyncIt is an engine, and engines produce telemetry.

**What's missing.** `AsyncRuntime` implements `ExecutionEngineInterface` but the scheduler itself emits nothing to the `ArtifactPublisherInterface`. The `SchedulerFailureArtifact` is published on throw, but normal-lifecycle events (spawn, resume, suspend) are not.

**Suggested approach.**
- The scheduler publishes structured occurrences for each lifecycle event. Each carries a `Duration` measurement (monotonic) and a `SequenceAllocatorInterface`-allocated sequence number (RunIt's per-execution ordering).
- `Observation` for anomalies: `StarvationDetected` (task runnable > N ms), `PollHandleLeak` (Assertion C violation reported as an observation, not just a conformance failure), `QueuePressure` (runnable depth > threshold).
- Reuses RunIt's `ArtifactPublisherInterface`, `MonotonicClockInterface`, `SequenceAllocatorInterface` — no new ports invented.

**Architectural fit.** Application (scheduler emits) + Domain (event value objects) + Reuse RunIt ports. Vocabulary: extend `async-it-taxonomy.ttl` with `#occ-task-spawned`, `#occ-task-suspended`, `#occ-task-completed`, `#obs-scheduler-starvation`, `#ms-task-duration`.

**Trade-offs & risks.** Must be recursion-guarded (ADR-014) and fail-open. Publishing on every resume/suspend is hot-path — use batch/flushed publication (AGENTS.md: "Instrument batch/flush boundaries rather than every internal scheduler method"). Conformance must prove zero recursive publication.

**Priority.** HIGH. Core to AsyncIt's RunIt-integration story; the AGENTS.md explicitly says "AsyncIt publishes scheduler events as RunIt Occurrences."

### H.3 Health, Liveness, and Graceful Shutdown

**Why it matters.** Containerized deployments (Kubernetes, RoadRunner) require `/healthz` and `/readyz` endpoints, and graceful shutdown on SIGTERM (finish in-flight tasks, drain poll handles, cancel pending timers). Without it, orchestrators kill containers mid-flight, losing in-flight work.

**What's missing.** No health endpoint. No graceful shutdown. `AsyncRuntime::run()` has no timeout or cancellation on shutdown.

**Suggested approach.**
- `SchedulerHealthInterface`: `isHealthy(): bool` (no leaked handles, no deadlocks), `pendingTaskCount(): int`, `activeHandleCount(): int`, `uptime(): Duration`.
- `GracefulShutdown`: listens for SIGTERM/SIGINT, triggers `CancellationScope::cancel()`, waits for in-flight tasks with a deadline (uses V.2 timer queue), then drains handles.
- Kubernetes probe adapter: HTTP handler returning health JSON (lives in `Adapter/Kubernetes/` or an example).

**Architectural fit.** Application (`SchedulerHealth`, `GracefulShutdown`) + Adapter (probe HTTP handler) + SDK (`AsyncRuntime::health()`). Health endpoints are external effects → ports (AGENTS.md: "External effects use ports").

**Trade-offs & risks.** Signal handling in PHP is process-global (`pcntl_signal`). Must guard for non-CLI SAPI (web SAPI has no process control). Graceful shutdown with deadline must itself use the timer queue (V.2) — circular dependency, so V.2 must land first.

**Priority.** MEDIUM. Needed for production but not for library MVP. Defer until V.2 (timers) exists.

### H.4 Metrics Export: Prometheus / OpenMetrics

**Why it matters.** Enterprise SRE teams standardize on Prometheus for alerting. Scheduler metrics (queue depth, task throughput, poll-wait latency, handle count) must be exportable as OpenMetrics for dashboards and alerting.

**What's missing.** `ArrayTelemetryRecorder` only stores events in-memory. No metrics endpoint, no histogram buckets, no cardinality controls.

**Suggested approach.**
- `PrometheusTelemetryBridge` (Adapter) implementing `TelemetryBridgeInterface`:
  - Gauges: `asyncit_scheduler_runnable_tasks`, `asyncit_scheduler_active_handles`, `asyncit_scheduler_active_timers`.
  - Counters: `asyncit_tasks_spawned_total`, `asyncit_tasks_completed_total` (by status).
  - Histograms: `asyncit_task_duration_seconds` (by task kind), `asyncit_poll_wait_seconds`.
- Cardinality control (AGENTS.md): **never** use user-controlled values as metric labels. `task_kind` is drawn from the closed `TaskKind` taxonomy; `status` from `TaskStatus`. No arbitrary labels.
- Uses `prom/phpprometheus` (suggest) or the OTel Prometheus exporter (already in dev deps via open-telemetry).

**Architectural fit.** Adapter (`Adapter/Prometheus/`) + SDK wiring. Must satisfy AGENTS.md "Use-user-controlled values as metric names" prohibition.

**Trade-offs & risks.** Metrics collection on hot paths is expensive. Sample (not record-every-event); use OTel's sampling layer. Histogram bucket selection affects alert resolution — provide sensible defaults but make configurable.

**Priority.** MEDIUM. Observability-tier; defer after H.1 (tracing) since both share the `TelemetryBridgeInterface`.

### H.5 Multi-Tenant Scheduler Isolation

**Why it matters.** SaaS platforms run multiple tenants in one process. AsyncIt must guarantee that Tenant A's tasks, handles, timers, and context never bleed into Tenant B's (the "invisible leak" problem from `docs/background/`).

**What's missing.** Single scheduler per process. No tenant tag or isolation boundary. `CancellationScope` is not tenant-tagged.

**Suggested approach.**
- `TenantId` value object (Domain) — a validated, bounded string (not a raw `string`).
- `Task` gains an immutable `tenantId: TenantId`. Context storage and telemetry are partitioned by tenant.
- `IsolationBoundary`: a scheduler wrapper that only runs tasks tagged with its tenant, and enforces resource quotas per tenant.
- Conformance: extend Assertion A with a multi-tenant isolation test — interleave tasks from two tenants across a suspend/resume, assert zero context bleed and zero handle cross-contamination.

**Architectural fit.** Domain (`TenantId`, `IsolationBoundary`) + Application (tenant-aware scheduler). Must satisfy AGENTS.md "Tenant isolation (persistent-worker request leakage)" security requirement.

**Trade-offs & risks.** Tenant tagging adds a field to every task — overhead on the hot path. Mitigation: `TenantId` is a single `int`/validated-string; the scheduler only checks it on spawn (not on every tick). Defer until there is a concrete multi-tenant deployment scenario.

**Priority.** MEDIUM-LOW. Enterprise concern but only relevant for multi-tenant deployments. Defer until core is stable.

### H.6 Resilience: Bulkhead & Rate Limiting

**Why it matters.** A slow downstream (e.g., a database) should not cascade-fail unrelated tasks. Bulkheads isolate failure domains; rate limiting prevents overwhelming downstream capacity.

**What's missing.** No isolation between task groups; no rate limiter.

**Suggested approach.**
- `Bulkhead` (Domain): a semaphore limiting concurrent in-flight tasks per resource pool. `Bulkhead::acquire()` suspends the task if the pool is full; `release()` on terminal.
- `RateLimiter` (Domain): token-bucket or leaky-bucket limiting operations per second. `awaitPermit()` suspends until a token is available.
- Both compose with V.7 (retry) — a bulkhead-full task retries with backoff.

**Architectural fit.** Domain (`Bulkhead`, `RateLimiter`, `TokenBucket`) + Application (scheduler integrates). These are scheduling primitives, not adapter concerns.

**Trade-offs & risks.** Bulkhead state is mutable and shared — must be fiber-safe (it is, since Fibers are cooperative single-threaded). Rate limiter uses `MonotonicClockInterface` for time-bucketed token refill. Defer until V.2 (timers/delay) exists.

**Priority.** MEDIUM-LOW. Resilience pattern; defer until structured concurrency (V.1) stabilizes the multi-task story.

### H.7 Configuration & Policy Engine

**Why it matters.** Hardcoded scheduling policies don't adapt to workload. Enterprises need declarative configuration of queue depths, timeouts, retry budgets, and scheduling policies — reloadable without restart.

**What's missing.** All policies are constructor arguments or constants. No configuration schema, no hot-reload.

**Suggested approach.**
- `SchedulerConfiguration` (Domain, `readonly`): a validated value object holding `maxConcurrency`, `defaultTimeout`, `schedulingPolicy`, `backpressurePolicy`, etc.
- `PolicyEngine` (Application): selects `SchedulingPolicy`, `BackpressurePolicy`, telemetry sampling rate based on the `CapabilityDescriptor` + observed runtime metrics (adaptive).
- Hot-reload: `SchedulerConfiguration::withChanges(array $diffs)` returns a new immutable config; the scheduler swaps it at a safe point (between ticks).
- Validation: reject configs that violate resource bounds (e.g., `maxConcurrency > 1000` triggers a warning).

**Architectural fit.** Domain (`SchedulerConfiguration`, policy enums) + Application (`PolicyEngine`). Must respect AGENTS.md "Large configuration arrays without typed builders" — use immutable builders, not arrays.

**Trade-offs & risks.** Hot-reload of scheduling policy mid-flight is tricky (in-flight tasks use the old policy, new tasks use the new). Must document the boundary clearly. Defer to Phase 2 of the roadmap.

**Priority.** LOW. Architectural feature; defer until core semantics stabilize.

### H.8 Scheduler Introspection & Debugging Toolkit

**Why it matters.** Production schedulers are opaque black boxes. Engineers need to introspect: "what tasks are running?", "what are they waiting on?", "is there a deadlock?". A debugging toolkit is an operational necessity.

**What's missing.** No introspection API. No deadlock detection. `TaskRecord` is internal.

**Suggested approach.**
- `SchedulerIntrospectionInterface` (Application, NOT public Contract — internal): `runningTasks(): list<TaskStatus>`, `suspendedTasks(): list<{task, awaitPoint}>`, `callGraph(): TaskDependencyTree`.
- Deadlock detector: if the runnable queue is empty but pending handles exist and no timers are due, and no handle has fired in N seconds → emit a `DeadlockDetected` observation + dump state.
- CLI tool (`bin/asyncit inspect`) or HTTP debug endpoint (gated behind a `debug` capability).

**Architectural fit.** Application (introspection is scheduler-internal) + Conformance (debug fixtures). Must NOT expose internal types from the Domain contract (AGENTS.md: "Do not add a public extension point until there is a concrete consumer"). Keep introspection `internal` or `Application`-level only.

**Trade-offs & risks.** Introspection on hot paths is expensive. Snapshot the state (copy) under a lock; never iterate the live runnable queue. Defer to Phase 2.

**Priority.** LOW. Operational convenience; defer.

---

## 4. Architectural Features — Structural & Platform-Level

Architectural features reshape the platform's structure, deployment model, or capability framework.

### A.1 Multi-Task Execution Model (Beyond Single-Operation `run()`)

**Why it matters.** The current `AsyncRuntime::run(ExecutionScopeInterface, callable $operation)` executes one operation. Real workflows need to spawn multiple tasks, coordinate them (V.1 nurseries), await subsets, and return. The single-operation model is a limitation.

**What's missing.** `run()` takes one callable. No `spawn()`, no multi-task dispatch. The README's example shows `spawn()` and `awaitReadable()` as intended but unimplemented (R15).

**Suggested approach.**
- `AsyncRuntime::spawn(callable $operation, ?TaskId $id = null): TaskInterface` — submits to the current execution's root task group.
- `AsyncRuntime::run(ExecutionScopeInterface, callable $operation): mixed` — wraps the operation in a task group, spawns it, then `await()`s the group. Backward-compatible: single-op callers still work, but the implementation is now group-based.
- The `EngineSchedulerInterface` gains `spawn()` / `awaitAll()` / `currentTask()` methods.

**Architectural fit.** Contract (`EngineSchedulerInterface` extension) + SDK (`AsyncRuntime::spawn`). Must maintain backward compatibility (AGENTS.md: "After 1.0.0, treat public interfaces as compatibility-sensitive"). Add `spawn` to `EngineSchedulerInterface` (it's the engine contract) without modifying `SchedulerInterface`.

**Trade-offs & risks.** Adding a method to `EngineSchedulerInterface` breaks implementors (AGENTS.md: "Adding an interface method breaks third-party implementations"). Mitigate: extract a new `SpawningSchedulerInterface` that `EngineSchedulerInterface` extends, OR add a default method with `NotImplemented` fallback. Prefer the focused-extension-interface pattern (AGENTS.md: "Introduce a new focused interface and compose it into new APIs").

**Priority.** CRITICAL. This is the foundational gap that blocks V.1, V.2, and all multi-task features. Must be implemented before any vertical feature.

### A.2 Capability Evolution: Dynamic & Versioned Descriptors

**Why it matters.** Runtimes evolve (PHP 8.6 → 8.7 → 9.0). Capabilities may change mid-process (e.g., a Swoole coroutine enters a blocking context that disables certain capabilities). Enterprises need capability descriptors that are version-aware and potentially dynamic.

**What's missing.** `CapabilityDescriptor` is a static `readonly` snapshot at discovery time. No versioning, no dynamic updates.

**Suggested approach.**
- `CapabilityDescriptor` gains a `version: int` (monotonic schema version) and an optional `RuntimeHandle` for dynamic re-query.
- `CapabilityObserverInterface`: adapters can push capability changes (e.g., "native channels became unavailable"); the scheduler reacts by switching the `TaskChannel` backend from native to emulated at runtime.
- Capability deprecation policy: old capability kinds remain `Unavailable` (not removed) so consumers can distinguish "never supported" from "supported but disabled."

**Architectural fit.** Contract (`CapabilityDescriptor` version field, `CapabilityObserverInterface`) + Domain (re-negotiation logic). Vocabulary: add `@see async-it-taxonomy#capability-version` and version the ontology.

**Trade-offs & risks.** Dynamic capability changes mid-flight are dangerous (a task using native channels could break if the capability flips to emulated). Must gate dynamic changes behind capability-gated adapters that support hot-swap. Defer to Phase 2.

**Priority.** LOW. Forward-compatibility concern; the current static descriptor is correct for PHP 8.4/8.5/8.6.

### A.3 Persistent Task Queue & Crash Recovery

**Why it matters.** Long-running schedulers need to survive process restarts. If the worker dies mid-task, the task state must be recoverable (at-least-once or exactly-once semantics). Enterprise workflows (payment processing, job queues) cannot lose in-flight work.

**What's missing.** All task state is in-memory (`FiberTaskScheduler` $records array). Fibers are non-serializable. No persistence.

**Suggested approach.**
- Separate **scheduling state** (in-memory, ephemeral) from **task definitions** (serializable, durable). A `PersistentTask` is a serializable value object (`TaskId`, `operation` as a `SerializableClosure` or queued job reference, `deadline`, `payload`).
- `PersistentTaskQueue` (Adapter, e.g., `Adapter/Redis/` or using RunIt's artifact store): stores pending task definitions in Redis; on restart, rehydrates them into `FiberTaskScheduler`.
- Delivery semantics: at-least-once by default; exactly-once via idempotent operation payloads + a `DeduplicationRecord` (RunIt's `IdentifierGeneratorInterface` keys).

**Architectural fit.** Domain (`PersistentTask`, `DeduplicationKey`) + Adapter (persistence backend) + Application (rehydration). Must respect AGENTS.md: "Do not serialize arbitrary objects as scheduling state" — only serialize task *definitions* (callables as `opis/closure` or job descriptors), never suspended Fibers.

**Trade-offs & risks.** Exactly-once is extremely hard (requires distributed transactions or idempotent writes). Start with at-least-once + idempotency keys. Persistence backend must be pluggable (Redis, PostgreSQL, RunIt's store). Defer to Phase 2.

**Priority.** LOW-MEDIUM. Critical for job-queue use cases but requires a persistence backend; defer until core scheduler is stable.

### A.4 Scheduler Federation & Hierarchical Scheduling

**Why it matters.** Microservice architectures distribute work across process and machine boundaries. A task may need to delegate sub-work to a remote scheduler (another AsyncIt instance in another service). Hierarchical/federated scheduling coordinates these.

**What's missing.** Single-process scheduler. No cross-process task handoff.

**Suggested approach.**
- `FederatedScheduler` (Application): accepts `RemoteTask` submissions and routes them via a `RemoteSchedulerPortInterface` (gRPC, AMQP, message queue).
- Parent scheduler tracks child schedulers as `TaskGroup`s; cancellation cascades across the federation boundary via serializable `CancellationScope`.
- `DistributedCancellationScope`: a scope whose state is synchronized across processes (via the remote scheduler port).

**Architectural fit.** Application (`FederatedScheduler`) + Contract (`RemoteSchedulerPortInterface`) + Adapter (gRPC/AMQP bridges). Reuses RunIt's `ExecutionEngineInterface` — a federated scheduler is itself an engine.

**Trade-offs & risks.** Distributed cancellation is fundamentally unreliable (network partitions). Must degrade gracefully (local timeout + dead-letter). Correlation IDs (RunIt) must propagate across the federation. Defer to Phase 2.

**Priority.** LOW. Architectural feature for distributed systems; defer.

### A.5 Plugin / Middleware Pipeline for Scheduling

**Why it matters.** Enterprises need to inject cross-cutting concerns (auth, logging, tracing context injection, rate limiting) into the scheduling pipeline without modifying core logic. A middleware pipeline makes this composable.

**What's missing.** `FiberTaskScheduler` has no middleware chain. Concerns are hardcoded (context attach/detach, handle release, publication).

**Suggested approach.**
- `SchedulerMiddleware` interface: `process(TaskInterface, callable $next): mixed`.
- A `MiddlewareScheduler` decorator wraps `FiberTaskScheduler` with a pipeline: [TraceContextInjector, ResourceQuotaChecker, BackpressureGate, ContextAttacher, ...].
- Ordering is declared (not implicit); each middleware is a `final` class implementing a single responsibility.

**Architectural fit.** Application (decorator pattern) + Contract (`SchedulerMiddlewareInterface`). Must respect AGENDS.md: "Decorators for non-intrusive instrumentation."

**Trade-offs & risks.** Middleware ordering is a common source of bugs. Provide a `PipelineBuilder` with validated ordering. Avoid middleware on the hot path (per-tick) — apply only at task spawn and terminal transition. Defer to Phase 2.

**Priority.** LOW. Architectural enhancement; defer.

### A.6 Schema Registry for Telemetry

**Why it matters.** As telemetry schemas evolve (new occurrence types, new measurement dimensions), consumers must handle schema evolution without breaking. A schema registry provides versioned, documented telemetry contracts.

**What's missing.** No schema versioning. `SchedulerFailureArtifact::normalize()` produces an ad-hoc array shape.

**Suggested approach.**
- `TelemetrySchemaRegistry` (Domain): versioned schema descriptors for each occurrence/measurement/observation type. Each schema has a `@see` to its ontology IRI and a JSON-Schema shape.
- `ArtifactNormalizer` (Application): produces version-tagged, schema-conformant arrays. Consumers validate against the registry.
- Backward compatibility: new fields are optional; removed fields are deprecated for N versions.

**Architectural fit.** Domain (`TelemetrySchema`, `SchemaVersion`) + Application (`ArtifactNormalizer`, `TelemetrySchemaRegistry`). Vocabulary: add `@see async-it#TelemetrySchema`.

**Trade-offs & risks.** Schema evolution adds complexity. Start with a single schema version and add the registry only when a second version is needed (AGENTS.md: "Do not add a public extension point until there is a concrete consumer"). Defer to Phase 2.

**Priority.** LOW. Defer.

### A.7 Hot Configuration Reload & Canary Deployments

**Why it matters.** Rolling updates must not restart the scheduler. New policies should deploy via canary (route 5% of tasks to the new policy, monitor, then ramp up).

**What's missing.** No config reload. No canary routing.

**Suggested approach.**
- `SchedulerConfiguration` (immutable, A.4) + `withChanges()` produces a new config snapshot atomically.
- `CanaryScheduler` (Application): wraps a base scheduler; a `weight` determines what fraction of tasks use the new config. Metrics (latency, error rate) are compared between canary and baseline.
- Health-check integration: if canary error rate exceeds threshold → auto-rollback to baseline.

**Architectural fit.** Application (`CanaryScheduler`, `SchedulerConfiguration`) + Reuse `TelemetryBridgeInterface` for comparison metrics.

**Trade-offs & risks.** Canary logic is complex and must not introduce latency on the hot path. Defer until H.4 (metrics) and A.4 (config) exist.

**Priority.** LOW. Defer to Phase 2.

---

## 5. Cross-Cutting: Security & Compliance Themes

Several features above touch security. Aggregating them for enterprise compliance review:

| Theme | Feature(s) | AGENTS.md Requirement | Status |
|---|---|---|---|
| **Context isolation** | V.1, H.3 | "Detach at yield point, re-attach on resume; zero bleed" | ✅ Existing (Assertion A) |
| **Handle leak prevention** | V.6, H.3 | "Release StreamPollHandle on terminal transition; Assertion C" | ✅ Existing |
| **Secret redaction** | H.1, H.2 | "Classification delegated to RunIt; deny-by-default" | ✅ RunIt-owned |
| **Tenant isolation** | H.5 | "Tenant isolation (persistent-worker request leakage)" | ◻ V.4 design |
| **Resource bounds** | V.6, H.6 | "No unbounded queues/channels/payloads" | ◻ V.6 design |
| **Metric cardinality** | H.4 | "Do not use user-controlled values as metric names" | ◻ H.4 design |
| **No global state** | H.2, H.3 | "No global/static request-specific scheduling metadata" | ✅ Core avoids it |

All new features must satisfy these invariants. Any feature that introduces mutable shared state (bulkhead, rate limiter, multi-tenant isolation) must be fiber-safe (cooperative single-threaded, so `Mutex` via `Fiber::suspend()` is sufficient).

---

## 6. Priority Matrix & Implementation Sequencing

Features are sequenced by dependency and impact. The `✅` marks map to the REFACTORING_PLAN cleanup tasks (R7–R15) that should be completed first to provide clean extraction points.

| Phase | Feature(s) | Rationale |
|---|---|---|
| **0 (Cleanup first)** | Complete R7–R15 from `REFACTORING_PLAN.md` | Shared poller trait, shared context storage trait, vocabulary completion, test isolation, declaration ordering, performance optimizations, assembler decomposition |
| **1 (Foundational)** | A.1 (multi-task model), V.2 (timers), V.1 (task groups) | Unblocks all multi-task features; timers are prerequisite for timeouts/cancellation |
| **2 (Core verticals)** | V.5 (cancellation reasons), V.6 (backpressure), V.4 (lifecycle hooks) | Enhance the scheduling domain; backpressure prevents resource exhaustion |
| **3 (Resilience)** | V.7 (retry/circuit breaker), H.6 (bulkhead/rate limit) | Build on timers + task groups for structured resilience |
| **4 (Observability)** | H.1 (OTel tracing), H.2 (RunIt publication), H.4 (Prometheus) | Make the scheduler visible to production monitoring |
| **5 (Operational maturity)** | H.3 (health/shutdown), H.7 (config/policy), H.8 (introspection) | Production runtime management |
| **6 (Scale-out)** | A.2 (dynamic capabilities), A.3 (persistence), A.4 (federation), A.5 (middleware), A.6 (schema registry), A.7 (canary) | Enterprise deployment at scale |

---

## 7. Dependency Graph

```text
                     A.1 (multi-task model)
                          │
            ┌─────────────┼─────────────┐
            ▼             ▼             ▼
         V.2 (timers)   V.1 (groups)  V.4 (hooks)
            │             │             │
            ▼             ▼             ▼
    H.3 (shutdown)  V.5 (cancel-reason) V.6 (backpressure)
            │             │             │
            ▼             ▼             ▼
        H.1 (tracing)  V.7 (retry)    H.6 (bulkhead)
            │             │             │
            ▼             ▼             ▼
        H.2 (RunIt pub)  H.4 (metrics)  H.5 (multi-tenant)
            │             │             │
            ▼             ▼             ▼
        A.3 (persist)  A.5 (middleware)  A.2 (dynamic caps)
            │             │             │
            ▼             ▼             ▼
        A.4 (federation) A.6 (schema)  A.7 (canary)
```

Each edge is a genuine implementation dependency. Features in the same tier are independent and can be developed in parallel.

---

## 8. Conformance & Testing Strategy for New Features

Every new feature must follow the existing AsyncIt conformance discipline (§10 of `docs/ROADMAP.md`):

1. **New assertions must be added to `AsyncRuntimeVerifier`** as new verifiers in `src/Conformance/Verifier/`, each returning a `ConformanceResult`.
2. **Negative-test siblings** (`*VerifierNegativeTest`) must break the invariant and assert the expected violation id + `ViolationVocabulary::SEVERITY_ERROR`.
3. **Framework-agnostic**: verifiers must never extend a test-framework base class (the `verify(port, fixture): ConformanceResult` shape is the contract).
4. **All adapters must pass** the new assertions via the same suite — semantic portability is the whole point.

Proposed new assertions:

| Assertion | Verifier | Feature(s) |
|---|---|---|
| **A.2 — Group Cancellation Isolation** | `GroupCancellationIsolationVerifier` | V.1 |
| **A.3 — Tenant Isolation** | `TenantIsolationVerifier` | H.5 |
| **C.2 — Timer Cleanup** | `TimerCleanupVerifier` | V.2 |
| **D — Backpressure Boundedness** | `BackpressureBoundednessVerifier` | V.6 |
| **E — Trace Parentage** | `TraceParentageVerifier` | H.1 |
| **F — Graceful Shutdown** | `GracefulShutdownVerifier` | H.3 |

Each new verifier must be backed by a `DeterministicMonotonicClock` fixture (for timers) or a `FakeTelemetryRecorder` (for trace/events), so tests are deterministic and fast.

---

## 9. Risk Register for the Backlog

| Risk | Mitigation | Affected Feature(s) |
|---|---|---|
| Hot-path instrumentation overhead | Batch/flush publication only at spawn/terminal, not per-tick | H.2, V.4 |
| Context bleed via OTel span injection | Reuse Assertion A; prove via conformance | H.1 |
| Backpressure deadlock (block on same scheduler) | Document invariant; default to `Block` only when safe | V.6 |
| Tenant isolation bypass via handle leak | Extend Assertion C to per-task handles; per-tenant handle registry | H.5 |
| Schema drift breaking consumers | Version registry; deprecation cycles | A.6 |
| Capability downgrade mid-flight | Atomic config swap; adapters must support hot-swap | A.2 |
| Distributed cancellation unreliability | Local timeout + dead-letter | A.4 |
| Metric cardinality explosion | Closed `TaskKind` taxonomy labels only; no user values | H.4 |

---

## 10. One-Line Summary

AsyncIt's next evolution is to grow from a **conforming, minimal scheduling core** into an **enterprise-grade, observable, resilient, multi-tenant scheduling platform** — by first shipping the missing multi-task model (`spawn` + task groups + timers), then layering resilience (retry, bulkheads, backpressure), observability (OTel, RunIt publication, Prometheus), and operational maturity (health, config, isolation) — each new capability proven by an extension of the `AsyncRuntimeVerifier` conformance suite, never by interface shape alone.

