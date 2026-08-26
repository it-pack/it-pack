# BRAINSTORM.md — AsyncIt Initial Reasoning

> **Status:** Brainstorm / pre-implementation reasoning.
> **Scope:** Broad architectural and implementation reasoning for `it-pack/async-it`.
> **Audience:** Architects and AI agents bootstrapping the package.
> **Companion docs:** `README.md`, `AGENTS.md`, `docs/EXECUTION.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002).
> **RunIt reference (critical):** `src/RunIt/docs/conformance.md` (port conformance toolkit), `src/RunIt/docs/architecture/context-propagation.md`, `src/RunIt/docs/architecture/lifecycle.md`, `src/RunIt/docs/concepts/time-and-sequencing.md`, and ADRs 005/013/014/023/024. AsyncIt must integrate with RunIt, so its contracts and verifiers should mirror RunIt's established patterns rather than reinvent them.

This document is deliberately exploratory. It reasons about *what* AsyncIt should be, *why* the central decisions hold, and *how* to build it without over-committing to a concrete API. Concrete contracts belong in code and in ADRs, not here.

---

## 0. Goal of this brainstorm

AsyncIt starts with tooling and docs but **no source yet**. Before writing a single class we should agree on the load-bearing ideas so the first contracts (M3) are stable. The risk we are guarding against is the classic PHP-async failure mode: **coupling scheduling to one runtime**, which then forces every upgrade or migration to be a rewrite.

The two prior artifacts already encode the *conclusion* (AGENTS.md, README.md). This document works the *reasoning* backwards: why each pillar is load-bearing, where the seams are, and what is still genuinely open.

---

## 1. Problem space & motivation

The PHP async landscape is fragmented into three mutually incompatible families:

1. **C-extension runtimes** (Swoole, OpenSwoole, Swow) — own the process and memory model, expose coroutines/channels, use hook models to rewrite blocking calls.
2. **Pure-PHP cooperative loops** (ReactPHP, Amp v3, Revolt) — built on PHP 8.1+ Fibers, single-threaded, no engine integration.
3. **Engine primitives** (Fibers 8.1+, `Io\Poll` 8.6+) — low-level mechanics without scheduling or lifecycle semantics.

These families **cannot host one another** at the process-model level. Swoole coroutines and Revolt Fibers do not share a memory/scheduling substrate. Any "unified event loop" that pretends otherwise is *false interoperability* (ADR-ITP-002).

**Reasoning:** Therefore the only portable thing we can standardize is **behavior**, not a shared runtime. That is the single most important conclusion and it dictates the whole shape of the package.

---

## 2. Core thesis: the mechanical split

$$\text{Polling} \neq \text{Scheduling} \neq \text{Execution Semantics}$$

- **Polling** answers *"is this descriptor ready?"* — a hardware/OS concern. `Io\Poll` (8.6) and `stream_select` (fallback) live here.
- **Scheduling** answers *"which task runs next, and when does this fiber resume?"* — AsyncIt's domain.
- **Semantics** answers *"what is an execution, who is it, what happened?"* — RunIt's domain.

**Why this split is load-bearing:**

- PHP 8.6 `Io\Poll` is an ontological shift: the engine now provides multiplexing. If scheduling were tangled with the poller, every engine upgrade would ripple into the scheduler. Clean separation means a new poll provider is a *swap-in adapter*, not a rewrite (poll-api-brainstorm.md confirms this is exactly the boundary we want).
- Higher tiers (Spindle, AExO) can stay durable. They consume the *Task* abstraction and never learn whether I/O is multiplexed by a C reactor or by `Io\Poll`.
- It lets us **test scheduling semantics independently of any runtime**, using a deterministic reference runtime.

**Consequence:** The domain must depend only on contracts and ports. Any code that names Swoole, Revolt, `Io\Poll`, or RunIt internals belongs in an adapter or behind a port.

---

## 3. Domain decomposition — the Minimal Semantic Kernel

From the briefings, the kernel is five invariants. I reason about where each lives:

| Kernel piece | Nature | Layer |
|---|---|---|
| **Task** | Micro-unit of scheduled work; wraps a Fiber; belongs to one execution | Domain |
| **Scheduling** | Task queues, priority, fiber suspend/resume, timer firing | Domain + Application |
| **Awaiting** | Yield control at a cooperation point without blocking the reactor | Domain (contract) + Adapter (mechanics) |
| **Suspension / Resumption** | Pause/resume fiber state *with* context isolation | Domain contract + Adapter + RunIt port |
| **Synchronization** | Channels, coordination between tasks (native or emulated) | Domain contract; emulation in Application/Common-Adapter |

**Reasoning on granularity:** We should not over-model. The smallest coherent unit is the **Task** + a **Scheduler** that drives a queue of tasks and a set of **Awaitables** (readable/writable/timeout). Everything else (channels, structured concurrency) can be expressed as combinators on top of await + a cancellation scope. This keeps M3 tiny and conformance-focused.

**Open decision:** Is "Task" a domain entity or just a Fiber + metadata bundle held by the scheduler? I reason it should be a lightweight domain value/entity that *carries* state (lifecycle enum, identity, owning execution id, cancellation scope ref) and *references* a Fiber, rather than subclassing Fiber. Fibers are not serializable and not domain-owned; tasks are.

---

## 4. Architecture — hexagonal & ports

Deptrac already defines the layers we must honor:

```text
SDK → Application → Domain → Port → Contract → Common
Adapters (Common/External) → {Contract, Port, Domain}
Conformance (Test Support)   → everything
```

I reason the **ports** we need are a mix of AsyncIt-native contracts and *reused RunIt ports* (RunIt already defines the execution-semantics ports; AsyncIt implements them per ADR-ITP-002):

**Reused RunIt ports (do not reinvent):**
- **`MonotonicClockInterface`** (RunIt) — `nowSeconds()` (wall) and `monotonicNanoseconds()` are distinct methods; `measure()` returns a `Duration` in integer nanoseconds. AsyncIt uses this for all scheduling durations so wall-clock skew is impossible. Maps to Assertion B.
- **`ContextStorageInterface`** (RunIt) — attach/detach of the immutable `ExecutionContext` via a `ContextToken`; LIFO stack semantics. AsyncIt never owns context; it only triggers detach/reattach around suspension.
- **`ArtifactPublisherInterface`** (RunIt) — push scheduler events into the RunIt artifact stream; carries the recursion guard and fail-open default (ADR-014, ADR-007).
- **`SequenceAllocatorInterface`** (RunIt) — per-execution monotonic artifact sequencing.
- **`ExecutionEngineInterface`** (RunIt) — **this is the seam**. AsyncIt's `AsyncRuntime` *is* the canonical `ExecutionEngineInterface` implementation; it must therefore pass RunIt's `ExecutionEngineVerifier`.

**AsyncIt-native ports:**
- **`PollerPort`** — readiness notification. Implementations: `NativePoller` (`Io\Poll`), `SelectPoller` (fallback). AsyncIt-owned.
- **`CancellationScopeInterface`** — observed cancellation state; native to the scheduling domain.
- **`CapabilityDescriptor` / `RuntimeDiscovery`** — AsyncIt-owned; selects adapters and emulation.

**Reasoning on RunIt coupling:** RunIt is WiP (pre-1.0) but its port *contracts* are already stable and shipped with a conformance toolkit. We should implement those interfaces (not fork them) and validate against RunIt's own verifiers. For M4 we still need a `Memory`/`Null` stand-in only where RunIt's *implementation* is not yet consumable — but the *interfaces* and *verifiers* can be depended on immediately. This keeps M4 unblocked without forking RunIt's vocabulary.

**Reasoning on adapter boundaries:** Runtime adapters (Revolt, Swoole, Swow) translate *suspension/resumption* and *polling* into the runtime's model but must **not** change scheduling semantics. The scheduler logic stays in the Application/Domain; the adapter only provides the "how do I actually suspend a coroutine / register a watcher here" primitive.

---

## 5. Capability negotiation reasoning

`RuntimeDiscovery` produces a `CapabilityDescriptor` once at the process boundary. Capabilities are functional, not vendor-named:

- Fiber execution (native / mandatory / primitive)
- Edge-triggered polling (yes / via ext-ev / via `Io\Poll`)
- Native channels (supported / emulated / unavailable)
- Blocking-hook model (supported / unavailable)
- Cooperative cancellation support level

Status vocabulary: `Supported`, `Emulated`, `Adapter-Only`, `Unavailable`.

**Reasoning:** The descriptor should *select* adapters and *enable* emulation strategies, **not** alter the contract set. Contracts are fixed; the descriptor tells us which implementation path is cheapest. Example: if native channels are `Unavailable` (pure-PHP), AsyncIt's `TaskChannel` falls back to the emulated implementation under the same public interface. The consumer code does not change.

**Open decision:** Where does the descriptor live in the dependency graph? I reason it is produced by `Application` (RuntimeDiscovery) and injected into the scheduler/adapter assembly — it is *configuration of the runtime*, not domain state, so it should not be referenced from `Domain`. Domain code asks a port "can I do X?" only through an injected capability view, never by re-inspecting the environment.

---

## 6. Fiber-safe context reasoning

Persistent workers (Swoole/Swow/RoadRunner) break PHP's shared-nothing guarantee. The invariant (from ADR-ITP-002 and the "invisible leak" brief):

1. **Suspend** → context is **detached** from the ambient fiber scope.
2. While suspended, the context is non-ambient and unreachable by interleaved tasks.
3. **Resume** → exact context is **re-attached** (guarded by `finally`).

**Reasoning (aligned with RunIt's `ContextStorageInterface`):** RunIt's storage is a synchronous **LIFO stack** keyed by an immutable `ContextToken`. Attaching pushes a token; detaching pops it and restores the *previous nested scope*. The storage detects **invalid tokens and double-detachment** — this is exactly what RunIt's `ContextStorageVerifier` checks (`src/RunIt/docs/conformance.md`). For the Fiber/coroutine models the *adapter* swaps the stack for a per-Fiber context slot (Revolt) or per-coroutine storage + `RuntimeContextDetectorInterface` (Swoole), clearing or restoring in `finally` (ADR-005, ADR-013). So the seam is: the scheduler calls `contextStorage->detach()` before `Fiber::suspend()` and `contextStorage->reattach(token)` after `Fiber::resume()`. AsyncIt supplies the *trigger timing*; the adapter supplies the *per-fiber slot*. `CurrentExecution` (the ambient accessor) returns `null` when no execution is active and never reads global mutable state.

**Assertion A (AsyncIt-native, stricter than the LIFO stack contract):** RunIt's `ContextStorageVerifier` proves the LIFO stack contract, but a cooperative scheduler adds a *timing* requirement — context must be detached **at the exact yield point** of the `Io\Poll` wait and re-attached **before** the resumed task executes any business code. Assertion A interleaves concurrent tasks and proves zero bleed *across the suspend/resume boundary*, which the plain stack verifier does not exercise.

**Open decision:** Copy-on-write context with hard bounds (≤64 entries, ≤4096 bytes/value, no raw objects/resources), classification (`public`/`internal`/`confidential`/`restricted`/`secret`), and merge policy (`ContextCollisionPolicy`) are owned by RunIt, not AsyncIt. AsyncIt only *transports* the context token and must respect `secret` deny-by-default. We must resist the temptation to re-implement context here.

---

## 7. Cancellation & lifecycle reasoning

Lifecycle: `CREATED → RUNNING → {SUCCEEDED|FAILED|CANCELLED|TIMED_OUT}`.

**Observed, never preemptive** (ADR-AI-001). Reasoning: preemptive kill bypasses `finally`, leaks `StreamPollHandle`s, dangles DB transactions. So cancellation is a state change on a **`CancellationScope`** that tasks observe at cooperation points (await boundaries, loop tops).

**Reasoning on cleanup:** When a task unwinds after observing cancellation, it **must** release every `StreamPollHandle` and timer it registered. The poller registry (handle→task) must be drained. This is what Assertion C (Deterministic Cleanup) enforces — a single leaked descriptor is a conformance failure.

**Reasoning on structured concurrency:** A parent `CancellationScope` cascades to children unless a child is explicitly `detached`. This is a relationship on cancellation scopes, not on tasks directly — tasks reference a scope. This keeps the cascade policy in one place.

**Reasoning on the lifecycle matrix (mirroring RunIt ADR-023/024):** RunIt deliberately moved the closed transition matrix *into the aggregate* (`ExecutionStatus::canTransitionTo()`, enforced in `Execution::complete()`) rather than leaving it only in the coordinator. AsyncIt should do the same: the task state machine is owned by the **Task aggregate** via `TaskStatus::canTransitionTo()`, and `Task::complete()` structurally rejects illegal transitions. The **scheduler/coordinator** sits above the aggregate and adds *idempotency*: repeating the **same** terminal completion is a no-op, while a **different** terminal status raises `ConflictingCompletionException`. This directly answers the open decision below — the exception is owned by the domain, invoked by the coordinator, never by adapters.

**Consequence for `created → cancelled`:** like RunIt, a task may be cancelled before it runs (`created → cancelled` is legal); only `running → terminal` and `created → cancelled` are valid, and any other transition raises `InvalidLifecycleTransitionException`.

---

## 8. Poller & awaiting reasoning

`awaitReadable($stream)` (et al.) must:

1. Register a readiness watcher with the `PollerPort`, associated with the current task.
2. **Suspend** the fiber (returning control to the scheduler).
3. When the poller reports readiness, the scheduler **resumes** the task and the await call returns the result.

**Reasoning on ownership:** The mapping `poll handle → task` is **scheduler state**, not poller state. The poller only knows "descriptor 7 is ready"; the scheduler knows "descriptor 7 belongs to task T". So the registry lives in the Application/Scheduler layer. The poller stays a dumb notifier.

**Reasoning on the reactor loop:** The scheduler's core loop is roughly:
- drain ready tasks (resume them),
- fire expired timers (resume them),
- call `poller->wait(timeout)` where timeout = nearest timer,
- repeat until no runnable tasks and no pending awaits.

This is a standard cooperative reactor. The novelty is not the loop; it is the *isolation and conformance* around it.

**Open decision:** `SelectPoller` fallback must never pretend to be `Io\Poll`. It is an AsyncIt poller with weaker characteristics (descriptor ceiling, level-triggered only). Assertions must hold for both.

---

## 9. Timers & synchronization (channels)

- **Timers:** reuse RunIt's `MonotonicClockInterface`. Scheduler keeps a timer queue keyed by absolute monotonic deadline; `delay()` suspends a task and registers a timer; nearest deadline drives `poller->wait` timeout. Durations are `Duration` value objects in **integer nanoseconds** (exact sub-second, no float rounding). Assertion B (Temporal Precision) verifies no wall-clock skew — this is essentially RunIt's `MonotonicClockVerifier` applied to AsyncIt's scheduling use. Per-execution artifact ordering (if AsyncIt emits occurrences) uses RunIt's `SequenceAllocatorInterface`.
- **Channels:** `TaskChannel` over a bounded queue. If `CapabilityDescriptor` says native channels `Supported`, the adapter may back it with Swoole/Swow channels; otherwise AsyncIt provides an emulated channel using suspend/resume + the scheduler registry. The **public interface is identical** — capability only chooses the backing.

**Reasoning:** Channels and timers are *derivable* from await + cancellation, so they do not need to be in the M3 minimal kernel. They can arrive in M4/M5 once the await primitive is proven.

---

## 10. Conformance strategy

The pivot is the **`AsyncRuntimeVerifier`** suite (in `src/Conformance`). But the key insight from RunIt is that **AsyncIt does not invent its own conformance shape** — it should reuse RunIt's framework-agnostic toolkit and *extend* it with scheduling-specific assertions.

**RunIt's conformance toolkit (`src/RunIt/docs/conformance.md`)** is the template we mirror:
- Lives in `src/Conformance/` with `Contract/PortFixtureInterface.php` (the *only* contract a verifier depends on), `Fixture/`, `Verifier/` (one per port contract), and `Result/` (`ConformanceResult`, `ConformanceViolation`, `ViolationSeverity`).
- Each verifier exposes `verify(port, fixture): ConformanceResult`. A result carries `isConformant(): bool` and `list<ConformanceViolation>`; breaches are `error`, advisories `warning`. **Verifiers never throw on a behavioral mismatch.**
- **Framework-agnostic:** no verifier extends a test-framework base class. The same checks run unchanged as a plain PHP script, or inside PHPUnit/Pest/Codeception via the returned result object.
- **Negative tests:** every verifier has a sibling `*VerifierNegativeTest` that deliberately breaks one contract at a time and asserts the expected violation id + severity; shipped verifiers are covered ≥95%.

**AsyncIt's `AsyncRuntimeVerifier` = two layers:**
1. **Reuse RunIt's port verifiers** against the RunIt ports AsyncIt implements. Because AsyncIt *is* a RunIt `ExecutionEngineInterface` implementation, it must pass:
   - `ExecutionEngineVerifier` → `ExecutionEngineInterface` (lifecycle, exception preservation). This *is* the formal integration gate between AsyncIt and RunIt.
   - `ContextStorageVerifier` → `ContextStorageInterface` (LIFO attach/detach, current, invalid token, double-detach).
   - `MonotonicClockVerifier` → `MonotonicClockInterface` (monotonic ordering, wall/mono separation). This *is* Assertion B.
   - `ArtifactPublisherVerifier` → `ArtifactPublisherInterface` (publish, ordering, recursion guard — ADR-014).
2. **AsyncIt-native scheduling verifiers** (same `verify(port, fixture): ConformanceResult` shape, same `Result/` model), covering what RunIt's ports alone cannot:
   - **Assertion A — Context Isolation across suspend/resume:** interleave tasks, prove zero bleed *at the exact yield point* and on re-attach (stricter than the LIFO stack contract).
   - **Assertion C — Deterministic Cleanup:** no dangling `StreamPollHandle`/timers after timeout/cancel.

**Mapping summary:**

| AsyncIt concern | RunIt verifier reused | AsyncIt-native verifier |
|---|---|---|
| Execution engine integration | `ExecutionEngineVerifier` | — |
| Context LIFO contract | `ContextStorageVerifier` | Assertion A (timing) |
| Monotonic time | `MonotonicClockVerifier` | — (Assertion B) |
| Publication recursion guard | `ArtifactPublisherVerifier` | — |
| Handle/timer cleanup | — | Assertion C |

A **pure-PHP deterministic reference runtime** (M4) is the semantic baseline. All runtime adapters (Revolt/Swoole/Swow/NativePoller) must match it 100% via the *same* verifier suite. This inverts the usual order: define behavior → prove with reference → adapters must conform.

**Reasoning:** This de-risks vendor adapters enormously and avoids fork risk — we reuse RunIt's verifiers rather than re-prove RunIt's own contracts. We can develop and validate all of AsyncIt's semantics on PHP 8.4/8.5 with `SelectPoller` + Fibers, long before PHP 8.6 `Io\Poll` is required. The framework-agnostic `ConformanceResult` model also means third-party AsyncIt adapter authors get the same "drop in your adapter, get a result object" experience RunIt already offers.

---

## 11. Implementation roadmap reasoning (what to build first)

I reason the safest first slice is **contracts + reference runtime + verifier**, not a vendor adapter:

1. **M0–M2:** Freeze landscape, boundaries (done-ish via ADRs/docs). Define vocabulary. Lock the `src/Conformance/` layout to mirror RunIt's (`Contract/PortFixtureInterface`, `Fixture`, `Verifier`, `Result`).
2. **M3 — Minimal Contracts:** `Task`, `TaskStatus` (with `canTransitionTo()`), `Scheduler` (interface), `PollerPort`, `CancellationScopeInterface`, `CapabilityDescriptor`, `RuntimeDiscovery` (interface), exceptions (`ConflictingCompletionException`, `InvalidLifecycleTransitionException`, marker interfaces). Plus `AsyncRuntimeVerifier` skeleton reusing RunIt's port verifiers + failing Assertion A/C.
3. **M4 — Reference Runtime:** a pure-PHP `FiberTaskScheduler` + `SelectPoller` + a `MemoryContextStorage` implementing RunIt's `ContextStorageInterface` + a `NullArtifactPublisher` implementing RunIt's `ArtifactPublisherInterface` + a `SystemMonotonicClock` (RunIt's). Make the verifier suite **pass** here. This is the semantic contract frozen in code.
4. **M5 — RunIt integration:** swap `MemoryContextStorage`/`NullArtifactPublisher` for RunIt's first-party adapters; validate detach/reattach and pass `ExecutionEngineVerifier` end-to-end (the formal AsyncIt↔RunIt gate).
5. **M6–M8 — Core adapters:** Revolt, Swoole, Swow. Each must pass the *same* verifier suite, including Assertion A (per-fiber/per-coroutine context slots) and Assertion C.
6. **M9–M10 — Native & interop:** `NativePoller` (`Io\Poll`), ReactPHP/Amp interop layer.

**Reasoning:** Building adapters first would let runtime quirks leak into the contract. Building the verifier first (even failing) keeps the target explicit. The reference runtime is what makes "semantic portability" real rather than aspirational.

---

## 12. Open questions / decisions needed

1. **Task identity source** — does AsyncIt mint task ids, or consume RunIt correlation ids? Likely AsyncIt mints, RunIt correlates via `IdentifierGeneratorInterface` (RunIt port, verifiable by `IdentifierGeneratorVerifier`).
2. **Suspension trigger granularity** — explicit `await*` calls only, or also support runtime blocking-hook rewriting (Swoole `SWOOLE_HOOK_ALL`)? Reasoning: support explicit model as canonical; treat hook model as an adapter convenience, never required.
3. **Structured concurrency primitives** — how explicit? Full task-tree (structured) vs lightweight scopes. Recommend scopes first, tree later. Mirror RunIt's `NestedExecutionPolicyInterface` modes (`child_execution`, `activity_only`, `detached_execution`, `reject_nested`) for the "detached vs child" decision.
4. **Scheduler queue policy** — FIFO minimal for M4; priority/weighted later, selected by capability.
5. **`StreamPollHandle` ownership** — is it returned to the caller or held by the scheduler registry? Recommend scheduler-held, auto-released on terminal transition, with manual release API for edge cases. This is Assertion C's enforcement hook.
6. **Serialization** — tasks/Fibers are non-serializable by design; we do **not** persist suspended tasks. Confirmed acceptable (background docs + RunIt's artifact model). Capability descriptors and task *state* (the `TaskStatus` enum) are serializable.
7. **Error model** — original business exception must survive; adapter/runtime exceptions must not replace it. **Resolved pattern (RunIt precedent):** the runner/scheduler rethrows the *exact* original `Throwable`; a mapped, bounded description is carried on the failed artifact, never in place of the business exception. AsyncIt mirrors this at the await boundary ("wrap, don't swallow").

**Decisions already informed by RunIt (no longer open):**
- **Clock** → reuse RunIt's `MonotonicClockInterface` + `Duration` (integer ns), not a custom `ClockPort`. (§4, §9)
- **Context storage** → implement RunIt's `ContextStorageInterface` (LIFO stack + `ContextToken`, per-fiber/coroutine slot in adapters); AsyncIt triggers detach/reattach. (§6)
- **Conflicting completion ownership** → owned by the Task aggregate (`TaskStatus::canTransitionTo()` + `Task::complete()`); coordinator adds idempotency. Same-status repeat = no-op, different = `ConflictingCompletionException`. (§7)
- **Publication** → implement RunIt's `ArtifactPublisherInterface` behind `FailSafeArtifactPublisher` (fail-open default, `onPublicationFailure` health callback, recursion guard per ADR-014). (§4, §10)
- **The integration seam** → AsyncIt's `AsyncRuntime` *is* RunIt's `ExecutionEngineInterface`; the formal gate is passing RunIt's `ExecutionEngineVerifier`. (§4, §10)

---

## 13. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Coupling scheduler to a runtime | Strict DepTrac: Domain depends only on Contracts/Ports; adapters isolated. |
| Context bleed in persistent workers | Detach/reattach via RunIt `ContextStorageInterface`, proven by Assertion A (and `ContextStorageVerifier`). |
| `StreamPollHandle` / timer leaks | Registry drained on every terminal transition; Assertion C. |
| Wall-clock skew in telemetry | Reuse RunIt `MonotonicClockInterface` (`Duration` ns); `MonotonicClockVerifier`. |
| RunIt not yet 1.0 | Depend on RunIt *port contracts + verifiers* (stable, shipped), not its *implementation*; `Memory`/`Null` stand-ins only for unavailable impls. |
| Over-abstraction before need | Contracts added only with a concrete consumer/adapter (per AGENTS.md). |
| Pretending `SelectPoller` is `Io\Poll` | Distinct named impl with documented weaker characteristics. |

---

## 14. One-line summary

AsyncIt's bet is that **behavior, not a shared runtime, is the only portable thing in PHP async** — so we split polling/scheduling/semantics, define the behavior with a deterministic reference runtime, and force every adapter to *conform* rather than merely *implement*.
