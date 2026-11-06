# AsyncIt — Strategic Feature Backlog

> **Status:** Active / rolling backlog.
> **Scope:** Enterprise-grade vertical, horizontal, architectural, and developer-experience enhancements for `it-pack/async-it`.
> **Purpose:** Reason about *what* to build next, *why* it matters, *how* it fits the architecture, and *what* the trade-offs are — so future implementation work (by humans or AI agents) starts from shared reasoning rather than ad-hoc invention.
> **Companion docs:** `README.md`, `AGENTS.md`, `docs/ROADMAP.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/REFACTORING_PLAN.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002), `vocab/async-it.ttl`, `vocab/async-it-taxonomy.ttl`.
> **Conventions:** Every public term carries a PHPDoc `@see` to its ontology/taxonomy IRI. Closed sets → `async-it.ttl` individuals; extensible kinds/keys → `async-it-taxonomy.ttl` concepts; consumed RunIt terms → `run-it#*` / `run-it-taxonomy#*`.

---

## 0. Where AsyncIt Stands Today

| Phase | Scope | Status |
|---|---|---|
| M0–M5 | Tooling, contracts, reference runtime, RunIt integration | ✅ Complete |
| M6–M8 | Core adapters (Swoole, Swow, Revolt), native `Io\Poll`, ReactPHP/Amp interop | ✅ Complete |
| M11–M12 | Multi-task model: `spawn()`, `await()`, `awaitDelay()`, timer heap, `TaskGroup` | ✅ Complete |
| M13 | Cancellation reasons, backpressure, lifecycle hooks/events | ✅ Complete |
| M14 | Retry (exponential/fixed/Fibonacci + jitter), circuit breaker, bulkhead, rate limiter | ✅ Complete |
| M15–M16 | OpenTelemetry bridge, RunIt lifecycle publication, Prometheus metrics, Assertion E | ✅ Complete |
| M17 | Health snapshot, graceful shutdown, `SchedulerConfiguration`, Assertion F | ✅ Complete |
| M18–M20 | Scale-out: persistence, federation, middleware, schema registry, canary | ◻ Planned (A.3–A.7) |

The core scheduling kernel is now **conforming, capability-negotiated, and multi-adapter**. AsyncIt is no longer a minimal core — it is a full scheduling platform. The remaining backlog is therefore **scale-out and developer-experience** work, not foundational scheduling.

---

## 1. Vertical Features — Scheduling Domain Semantics

### V.9 High-Level IO Await Primitives: `awaitReadable()` / `awaitWritable()`

**What should be built.** First-class `awaitReadable(StreamPollHandle, ?Duration): bool` and `awaitWritable(StreamPollHandle, ?Duration): bool` methods on `EngineSchedulerInterface` / `AsyncRuntime`, plus a `StreamPollHandle` registry integrated with the timer heap so a deadline can cancel an await.

**Why it matters.** The README's conceptual example and the original roadmap promise these primitives, but no contract exists for them (confirmed: zero matches for `awaitReadable`/`awaitWritable` in `src/`). Without them, consumers must reach directly into `PollerPortInterface::register()`/`await()` and manage `StreamPollHandle` lifecycles by hand — exactly the handle-leak risk AGENTS.md warns against. Every production async PHP library exposes ready/waitable await as its primary IO primitive.

**How it fits.**
- Contract: `EngineSchedulerInterface` gains `awaitReadable(StreamPollHandleInterface, ?DurationInterface): bool` and `awaitWritable(...)`.
- Application: `FiberTaskScheduler` registers the handle with the poller, associates a timer (deadline) with the task's await record, suspends via `Fiber::suspend()`, and resumes when the poller signals readiness or the timer fires.
- Domain: `AwaitPointReadable` / `AwaitPointWritable` variants added to the `AwaitPoint` discriminated union (`async-it-taxonomy#await-point-readable`, `#await-point-writable`).
- Vocabulary: `@see https://w3id.org/itpack/asyncit#StreamPollHandle`, `@see https://w3id.org/itpack/asyncit-taxonomy#await-point-read-wait`.

**Trade-offs & risks.** Adds surface to `EngineSchedulerInterface` (breaking for third-party implementors post-1.0.0 — mitigate via a focused `AwaitableIOInterface` extension rather than modifying the core). Timer integration must not break Assertion C (deterministic cleanup): every registered handle must be released on terminal transition, timeout, or cancellation. Handle ownership must be explicit (task-owned vs caller-owned) to avoid double-release.

**Priority.** HIGH. This is the single most visible gap between the README's promised API and the shipped API; it unblocks all IO-driven workflows.

---

### V.10 Structured-Combinator Suite: `race()`, `all()`, `any()`, `select()`, `join()`

**What should be built.** Named combinators on `TaskGroup` / `AsyncRuntime`:
- `race(callable...): mixed` — returns the result of the first child to complete; cancels the rest.
- `all(callable...): array` — waits for every child; fails fast on first error (or collects all, policy-driven).
- `any(callable...): mixed` — returns the first *successful* result; ignores failures unless all fail.
- `select(callable[]): mixed` — waits on the first ready channel/stream/handle among multiple await points (Go-style select).
- `join(TaskInterface...): array` — awaits an explicit set of already-spawned tasks.

**Why it matters.** Nurseries (`TaskGroup::go()` + `await()`) are the low-level primitive, but enterprise workflows are usually expressed as combinators. Without them, consumers re-implement `Promise.race`/`Promise.all` semantics ad hoc over raw tasks — the #1 source of handle leaks, lost errors, and deadlocks in hand-rolled structured concurrency.

**How it fits.**
- Domain: combinator value objects (`RaceGroup`, `AllGroup`, `AnyGroup`, `SelectGroup`) each wrapping a `CancellationScope` and a set of child tasks.
- Application: `FiberTaskScheduler` drives each combinator's await loop; `select()` integrates with `AwaitPoint` discrimination so the scheduler knows which handle fired.
- SDK: `AsyncRuntime::race(...)`, `::all(...)`, `::any(...)`, `::select(...)`, `::join(...)` delegate to the active scheduler.
- Vocabulary: `@see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race`, `#task-group-strategy-all`, `#task-group-strategy-any`, `#await-point-select`.

**Trade-offs & risks.** `select()` over heterogeneous await points (channels + streams + timers) requires the scheduler to track multiple pending awaits per task — more complex than single-await suspension. `race()`/`any()` cancellation semantics (cancel losers vs let them run to completion) must be explicit and documented. Start with `all()`/`any()`/`race()` (task-level), defer `select()` (multi-await-point) until V.9 IO await primitives exist.

**Priority.** HIGH. Directly reduces consumer-facing complexity and the surface for handle-leak bugs.

---

### V.11 Task Result Types: `TaskResult<T, E>` / Either-Style Outcome

**What should be built.** A `TaskResult` value object (or `TaskOutcome`) encapsulating a terminal task's result: `isSuccess()`, `isFailure()`, `value(): T`, `error(): ?Throwable`, `status(): TaskStatus`, `duration(): Duration`. `EngineSchedulerInterface::result(TaskInterface): TaskResult` returns it instead of raw `mixed`.

**Why it matters.** `result()` returning `mixed` forces callers to check `isTerminal()` and catch exceptions manually. A typed result type makes success/failure explicit at the type level, enables functional composition (`map`, `flatMap`, `orElse`), and integrates cleanly with the combinator suite (V.10).

**How it fits.**
- Domain: `TaskResult<T>` generic value object (PHP 8.4 class-template generics via PHPDoc `@template`), `readonly`, final, interface-backed.
- Contract: `EngineSchedulerInterface::result(TaskInterface): TaskResultInterface` (or keep `mixed` for backward compat and add `resultTyped()`).
- Application: `FiberTaskScheduler` captures the return value or caught `Throwable` into `TaskResult` on terminal transition.
- Vocabulary: `@see https://w3id.org/itpack/asyncit#TaskResult`, `@see https://w3id.org/itpack/runit#Observation` (for the error side).

**Trade-offs & risks.** Generic classes in PHP 8.4 are PHPDoc-only (no runtime enforcement) — document that clearly. Changing `result()`'s return type breaks `SchedulerInterface` implementors; prefer a new method or a focused `ResultAwareSchedulerInterface` extension.

**Priority.** MEDIUM. DX polish; valuable but not blocking.

---

### V.12 Async Generator / AsyncIterator Task Streams

**What should be built.** `AsyncTaskGenerator` / `TaskStream` — an `AsyncIterator`-compatible lazy sequence of tasks produced by a generator function, scheduled one-at-a-time or bounded-concurrently, with backpressure (V.6) applied between emissions.

**Why it matters.** PHP 8.1+ async generators are the idiomatic way to express streaming workloads (paginate an API, read a file line-by-line, process a queue). AsyncIt has no bridge from `async generator` → `Task` stream, so consumers must manually `spawn()` each iteration.

**How it fits.**
- Domain: `TaskStreamInterface` (`AsyncIterator`-compatible), `TaskStream` implementation wrapping a `Generator`/`AsyncGenerator`.
- Application: scheduler pulls from the generator at await boundaries, spawning each yielded callable as a task; backpressure gates emission rate.
- SDK: `AsyncRuntime::stream(callable $generator): TaskStreamInterface`.
- Vocabulary: `@see https://w3id.org/itpack/asyncit-taxonomy#task-kind-stream`.

**Trade-offs & risks.** Async generators and Fibers both use `yield`/`suspend` — composition must be carefully specified (does the generator yield control to the scheduler, or does the scheduler drive the generator?). Defer until V.9/V.10 land; start with synchronous generators adapted one-task-at-a-time.

**Priority.** LOW-MEDIUM. Niche streaming use case; defer until core DX primitives (V.9, V.10) exist.

---

## 2. Horizontal Features — Ecosystem & Cross-Cutting

### H.9 Persistent Task Queue & Crash Recovery (A.3)

**What should be built.** `PersistentTask` (Domain, `readonly`): `TaskId`, operation as serializable descriptor (job class name + payload array — **never** a raw closure), `deadline`, `payload`. `PersistentTaskQueueInterface` (Application port) + `RedisTaskQueue` adapter storing pending task definitions in Redis. On restart, rehydrate pending definitions into `FiberTaskScheduler`. At-least-once delivery by default; exactly-once via idempotency keys (`DeduplicationKey` from RunIt's `IdentifierGeneratorInterface`).

**Why it matters.** All task state is in-memory; Fibers are non-serializable. A process restart loses in-flight work. For payment processing, job queues, and long-running workflows, crash recovery is a production requirement.

**How it fits.**
- Domain (`PersistentTask`, `DeduplicationKey`) + Application (`PersistentTaskQueueInterface`, rehydration logic) + Adapter (`Adapter/Redis/RedisTaskQueue`).
- AGENTS.md compliance: only task *definitions* serialized; suspended Fibers never persisted.
- Vocabulary: `@see https://w3id.org/itpack/asyncit#PersistentTask`, `@see https://w3id.org/itpack/runt-taxonomy#occ-task-rehydrated`.

**Trade-offs & risks.** Exactly-once requires distributed coordination or idempotent writes — start with at-least-once + idempotency keys. Persistence backend must be pluggable (Redis, PostgreSQL, RunIt's artifact store). Serialized operation descriptors are a versioning concern (job class renames break old payloads).

**Priority.** LOW-MEDIUM. Critical for job-queue use cases; defer until core is rock-solid.

---

### H.10 Scheduler Federation & Hierarchical Scheduling (A.4)

**What should be built.** `RemoteSchedulerPortInterface` (Application port): `execute(RemoteTask): mixed` with implementations (gRPC, AMQP). `FederatedScheduler` accepts `RemoteTask` submissions and routes them via the port. Parent scheduler tracks child schedulers as `TaskGroup`s; cancellation cascades across the federation boundary via serializable `CancellationScope` tokens. Correlation IDs (RunIt `IdentifierGeneratorInterface`) propagate across federation.

**Why it matters.** Microservice architectures distribute work across process and machine boundaries. A task may need to delegate sub-work to a remote AsyncIt instance. Without federation, AsyncIt is a single-process scheduler.

**How it fits.**
- Application (`FederatedScheduler`, `RemoteTask`) + Contract (`RemoteSchedulerPortInterface`) + Adapter (gRPC/AMQP bridges).
- Reuses RunIt's `ExecutionEngineInterface` — a federated scheduler is itself an engine.
- Vocabulary: `@see https://w3id.org/itpack/runit#Execution`, `@see https://w3id.org/itpack/asyncit-taxonomy#runtime-adapter-kind-federated`.

**Trade-offs & risks.** Distributed cancellation is fundamentally unreliable under network partitions — must degrade gracefully (local timeout + dead-letter). Partial failures are inherent; document the consistency model explicitly (at-least-once, best-effort cancellation).

**Priority.** LOW. Distributed systems feature; defer until persistence (H.9) exists.

---

### H.11 Scheduler Middleware Pipeline (A.5)

**What should be built.** `SchedulerMiddlewareInterface` (Contract): `process(TaskInterface, callable $next): mixed`. `MiddlewareScheduler` (Application) decorator wrapping `FiberTaskScheduler` with an ordered pipeline. Reference middleware: `TraceContextInjector`, `ResourceQuotaChecker`, `BackpressureGate`, `ContextAttacher`. Ordering declared explicitly via `PipelineBuilder`.

**Why it matters.** Enterprises need to inject cross-cutting concerns (auth context injection, quota enforcement, tracing) without modifying core scheduling logic. A middleware pipeline makes this composable and testable per concern.

**How it fits.**
- Application (decorator pattern) + Contract (`SchedulerMiddlewareInterface`).
- AGENTS.md: "Decorators for non-intrusive instrumentation." Apply only at task spawn and terminal transition — not on the hot per-tick path.
- Vocabulary: `@see https://w3id.org/itpack/asyncit#SchedulerMiddleware`.

**Trade-offs & risks.** Middleware ordering is a common bug source — provide a `PipelineBuilder` with validated ordering. Avoid per-tick middleware; apply at spawn/terminal only.

**Priority.** LOW. Architectural enhancement; defer until there is a concrete consumer.

---

### H.12 Telemetry Schema Registry (A.6)

**What should be built.** `TelemetrySchemaRegistry` (Domain): versioned JSON-Schema descriptors for each occurrence/measurement/observation type, each with `@see` to its ontology IRI. `ArtifactNormalizer` (Application): produces version-tagged, schema-conformant arrays. Consumers validate against the registry. Backward compatibility: new fields optional; removed fields deprecated for N versions.

**Why it matters.** As telemetry schemas evolve (new occurrence types, new measurement dimensions), consumers must handle schema evolution without breaking. Currently `SchedulerFailureArtifact::normalize()` produces an ad-hoc array shape with no versioning.

**How it fits.**
- Domain (`TelemetrySchema`, `SchemaVersion`) + Application (`ArtifactNormalizer`, `TelemetrySchemaRegistry`).
- Vocabulary: `@see https://w3id.org/itpack/asyncit#TelemetrySchema`.

**Trade-offs & risks.** Schema evolution adds complexity. Start with a single schema version; add the registry only when a second version is needed (AGENTS.md: no speculative extension points without a concrete consumer).

**Priority.** LOW. Defer.

---

### H.13 Hot Configuration Reload & Canary Deployments (A.7)

**What should be built.** `SchedulerConfiguration::forCanary(float $weight)` routes a weighted fraction of tasks to a canary config. `CanaryScheduler` (Application) wraps a base scheduler; compares latency/error metrics between canary and baseline via `TelemetryBridgeInterface`; auto-rolls-back on threshold breach. `withChanges()` produces an immutable config snapshot swapped at a safe point (between reactor ticks).

**Why it matters.** Rolling updates must not restart the scheduler. Canary deployment lets teams validate new scheduling policies against baseline metrics before full rollout.

**How it fits.**
- Application (`CanaryScheduler`, `SchedulerConfiguration`) + reuse `TelemetryBridgeInterface` for comparison metrics.
- Vocabulary: `@see https://w3id.org/itpack/asyncit#SchedulerConfiguration`.

**Trade-offs & risks.** Canary logic must not introduce hot-path latency. Requires H.4 (metrics) to be mature first.

**Priority.** LOW. Defer until H.4 + H.7 exist.

---

## 3. Architectural Features — Platform-Level

### A.1 Dynamic Capability Evolution (A.2) — *mostly complete*

**Status:** `CapabilityDescriptor` gains `version: int`; `CapabilityObserverInterface` + `swapCapabilities()` implemented; deprecation policy documented. Remaining: harden the atomic swap path with conformance tests proving no mid-flight backend switch corrupts in-flight tasks.

**Priority.** MEDIUM. Forward-compatibility; the current static descriptor is correct for PHP 8.4/8.5/8.6.

---

## 4. Security & Compliance Themes

| Theme                  | Feature(s)    | AGENTS.md Requirement                                          | Status                   |
|------------------------|---------------|----------------------------------------------------------------|--------------------------|
| Context isolation      | V.1–V.10, H.9 | "Detach at yield point, re-attach on resume; zero bleed"       | ✅ Assertion A            |
| Handle leak prevention | V.9, H.9      | "Release StreamPollHandle on terminal transition; Assertion C" | ✅ Assertion C            |
| Secret redaction       | H.1, H.2      | "Classification delegated to RunIt; deny-by-default"           | ✅ RunIt-owned            |
| Tenant isolation       | H.5 (planned) | "Tenant isolation (persistent-worker request leakage)"         | ◻ Not started            |
| Resource bounds        | V.6, H.6      | "No unbounded queues/channels/payloads"                        | ✅ BackpressurePolicy     |
| Metric cardinality     | H.4           | "Do not use user-controlled values as metric names"            | ✅ Closed taxonomy labels |
| No global state        | Core          | "No global/static request-specific scheduling metadata"        | ✅ Core avoids it         |

**Gap:** Multi-tenant isolation (H.5 from the original backlog) has not been implemented. `TenantId` value object + `IsolationBoundary` scheduler wrapper + per-tenant handle/timer registry + extended Assertion A with multi-tenant interleaving. Priority: MEDIUM-LOW — only relevant for multi-tenant SaaS deployments.

---

## 5. Priority Matrix & Implementation Sequencing

| Phase             | Feature(s)                                       | Dependencies                  | Rationale                                                          |
|-------------------|--------------------------------------------------|-------------------------------|--------------------------------------------------------------------|
| **1 (Immediate)** | V.9 (`awaitReadable`/`awaitWritable`)            | M11–M12 (timers, spawn/await) | Closes the biggest API gap vs. README; enables all IO workflows    |
| **1 (Immediate)** | V.10 (combinators: `race`, `all`, `any`, `join`) | V.9, V.1                      | Reduces ad-hoc structured-concurrency bugs; handle-leak prevention |
| **2**             | V.11 (`TaskResult` type)                         | M11–M12                       | Typed outcomes; enables functional composition                     |
| **2**             | V.12 (async generator streams)                   | V.9, V.10, V.6                | Streaming workloads; niche but idiomatic PHP                       |
| **3**             | H.9 (persistent task queue)                      | M11–M12                       | Crash recovery for long-running workflows                          |
| **3**             | H.5 (multi-tenant isolation)                     | M11–M12                       | SaaS safety; only if multi-tenant deployment exists                |
| **4**             | H.10 (federation)                                | H.9                           | Cross-process delegation                                           |
| **4**             | H.11 (middleware pipeline)                       | —                             | Composable cross-cutting concerns                                  |
| **4**             | H.12 (schema registry)                           | H.1/H.2/H.4                   | Telemetry schema evolution                                         |
| **5**             | H.13 (canary + hot reload)                       | H.4, H.7                      | Production rollout safety                                          |

Each new feature adds conformance assertions to `AsyncRuntimeVerifier` (`src/Conformance/Verifier/`) in the phase that introduces the behavior. Proposed new assertions:

| Assertion                       | Verifier                         | Feature(s)                               |
|---------------------------------|----------------------------------|------------------------------------------|
| **D — IO Await Cleanup**        | `IOAwaitCleanupVerifier`         | V.9 (handles released on timeout/cancel) |
| **E — Combinator Cancellation** | `CombinatorCancellationVerifier` | V.10 (losers cancelled, no leaks)        |
| **G — Tenant Isolation**        | `TenantIsolationVerifier`        | H.5 (cross-tenant context/handle bleed)  |

---

## 6. Risk Register

| Risk                                                         | Mitigation                                                                                 | Affected Feature(s) |
|--------------------------------------------------------------|--------------------------------------------------------------------------------------------|---------------------|
| Interface method additions break third-party schedulers      | Use focused extension interfaces (`AwaitableIOInterface`, `ResultAwareSchedulerInterface`) | V.9, V.11           |
| Handle leaks in IO await primitives                          | Extend Assertion C; per-task handle registry; release on terminal/timeout/cancel           | V.9                 |
| Combinator deadlock (cancel loser while it holds a resource) | Document "don't block-and-wait on same scheduler"; default to cooperative cancellation     | V.10                |
| Generic `TaskResult` PHPDoc-only (no runtime enforcement)    | Document clearly; consider `mixed` fallback for runtime                                    | V.11                |
| Persistence backend lock-in                                  | `PersistentTaskQueueInterface` is a port; Redis is one adapter                             | H.9                 |
| Distributed cancellation unreliability                       | Local timeout + dead-letter; document consistency model                                    | H.10                |

---

## 7. Definition of Done (package-level)

- `composer test`, `composer analyse`, `composer architecture`, `composer coding-style`, `composer mutation` green on PHP 8.4+.
- `AsyncRuntimeVerifier` green across reference runtime **and** every adapter (A/B/C + new assertions per phase).
- `AsyncRuntime` passes RunIt's `ExecutionEngineVerifier`.
- Coverage ≥95% line/method, ≥90% class; Infection clean.
- Every concrete class `final`, interface-backed, one type per file; no abstract classes; no `else`/Yoda/magic literals; vocabularies in interfaces; factory/builder construction via DI with the documented exemptions, including PHP internal classes.
- `examples/conformance/third-party-adapter.php` runs unmodified for each shipped adapter.
- Docs (`README.md`, `AGENTS.md`, this backlog, roadmap) reflect the built reality; public contracts carry `@see` references to `async-it#*` / `async-it-taxonomy#*` (AsyncIt-owned) and `run-it#*` / `run-it-taxonomy#*` (consumed).
