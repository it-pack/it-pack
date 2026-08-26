# BRAINSTORM.md — AsyncIt Initial Reasoning

> **Status:** Brainstorm / pre-implementation reasoning.
> **Scope:** Broad architectural and implementation reasoning for `it-pack/async-it`.
> **Audience:** Architects and AI agents bootstrapping the package.
> **Companion docs:** `README.md`, `AGENTS.md`, `docs/EXECUTION.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002).

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

I reason the **ports** we need are:

- **`ClockPort`** — monotonic time only (`hrtime()`). No wall-clock in scheduling.
- **`ContextStoragePort`** — fiber/coroutine-aware attach/detach of the immutable RunIt context. AsyncIt never owns context; it only coordinates its isolation.
- **`PollerPort`** — readiness notification. Implementations: `NativePoller` (`Io\Poll`), `SelectPoller` (fallback).
- **`PublicationPort`** — push scheduler events (Occurrences/Measurements/Observations) into the RunIt artifact stream, recursion-safe.
- **`IdentityPort`** — generate task/execution correlation ids (or consume RunIt's).

**Reasoning on RunIt coupling:** RunIt is WiP (pre-1.0). We must **not** take a hard dependency on its implementation. Therefore `ContextStoragePort` and `PublicationPort` are AsyncIt-owned contracts with *optional* RunIt adapters. The reference runtime (M4) can ship a `Null`/`Memory` context storage and a no-op publisher so we can validate semantics before RunIt lands. This keeps M4 unblocked.

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

**Reasoning:** This must be coordinated through `ContextStoragePort`, but the *trigger* is the scheduler's suspend/resume calls, not the runtime. So the seam is: the scheduler calls `contextStorage->detach()` before `Fiber::suspend()` and `contextStorage->reattach(token)` after `Fiber::resume()`. Adapters implement how the token is stored per fiber/coroutine (Swoole: coroutine-local; Revolt: fiber slot; reference: `WeakMap<Fiber, Context>`).

**Open decision:** Copy-on-write context with hard bounds (≤64 entries, ≤4096 bytes/value, no raw objects/resources) is owned by RunIt, not AsyncIt. AsyncIt only *transports* the context token. We must resist the temptation to re-implement context here.

---

## 7. Cancellation & lifecycle reasoning

Lifecycle: `CREATED → RUNNING → {SUCCEEDED|FAILED|CANCELLED|TIMED_OUT}`.

**Observed, never preemptive** (ADR-AI-001). Reasoning: preemptive kill bypasses `finally`, leaks `StreamPollHandle`s, dangles DB transactions. So cancellation is a state change on a **`CancellationScope`** that tasks observe at cooperation points (await boundaries, loop tops).

**Reasoning on cleanup:** When a task unwinds after observing cancellation, it **must** release every `StreamPollHandle` and timer it registered. The poller registry (handle→task) must be drained. This is what Assertion C (Deterministic Cleanup) enforces — a single leaked descriptor is a conformance failure.

**Reasoning on structured concurrency:** A parent `CancellationScope` cascades to children unless a child is explicitly `detached`. This is a relationship on cancellation scopes, not on tasks directly — tasks reference a scope. This keeps the cascade policy in one place.

**Open decision:** `ConflictingCompletionException` — which layer owns it? I reason the Task state transition is guarded in the domain (a task is a state machine); the scheduler calls `task->complete(...)` and the domain rejects illegal transitions. Adapters never drive transitions directly.

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

- **Timers:** monotonic `ClockPort`; scheduler keeps a timer queue keyed by absolute monotonic deadline; `delay()` suspends a task and registers a timer; nearest deadline drives `poller->wait` timeout. Assertion B (Temporal Precision) verifies no wall-clock skew.
- **Channels:** `TaskChannel` over a bounded queue. If `CapabilityDescriptor` says native channels `Supported`, the adapter may back it with Swoole/Swow channels; otherwise AsyncIt provides an emulated channel using suspend/resume + the scheduler registry. The **public interface is identical** — capability only chooses the backing.

**Reasoning:** Channels and timers are *derivable* from await + cancellation, so they do not need to be in the M3 minimal kernel. They can arrive in M4/M5 once the await primitive is proven.

---

## 10. Conformance strategy

The pivot is the **`AsyncRuntimeVerifier`** (in `src/Conformance`). Reasoning: PSR-style interface matching cannot prove runtime invariants, so we prove *behavior*:

- **Assertion A — Context Isolation:** interleave tasks, assert zero bleed across suspend/resume.
- **Assertion B — Temporal Precision:** monotonic-clock integration; no wall-clock skew in Measurements.
- **Assertion C — Deterministic Cleanup:** no dangling `StreamPollHandle`/timers after timeout/cancel.

A **pure-PHP deterministic reference runtime** (M4) is the semantic baseline. All runtime adapters (Revolt/Swoole/Swow/NativePoller) must match it 100%. This inverts the usual order: define behavior → prove with reference → adapters must conform.

**Reasoning:** This de-risks vendor adapters enormously. We can develop and validate all of AsyncIt's semantics on PHP 8.4/8.5 with `SelectPoller` + Fibers, long before PHP 8.6 `Io\Poll` is required.

---

## 11. Implementation roadmap reasoning (what to build first)

I reason the safest first slice is **contracts + reference runtime + verifier**, not a vendor adapter:

1. **M0–M2:** Freeze landscape, boundaries (done-ish via ADRs/docs). Define vocabulary.
2. **M3 — Minimal Contracts:** `Task`, `Scheduler` (interface), `PollerPort`, `CancellationScope`, `ClockPort`, `ContextStoragePort`, `PublicationPort`, `CapabilityDescriptor`, `RuntimeDiscovery` (interface), exceptions (`ConflictingCompletionException`, marker interfaces). Plus the `AsyncRuntimeVerifier` skeleton with assertions A/B/C as *failing* tests.
3. **M4 — Reference Runtime:** a pure-PHP `FiberTaskScheduler` + `SelectPoller` + `MemoryContextStorage` + `NullPublisher`. Make the verifier **pass** here. This is the semantic contract frozen in code.
4. **M5 — RunIt integration:** swap `MemoryContextStorage`/`NullPublisher` for RunIt-backed ports; validate detach/reattach.
5. **M6–M8 — Core adapters:** Revolt, Swoole, Swow. Each must pass the *same* verifier.
6. **M9–M10 — Native & interop:** `NativePoller` (`Io\Poll`), ReactPHP/Amp interop layer.

**Reasoning:** Building adapters first would let runtime quirks leak into the contract. Building the verifier first (even failing) keeps the target explicit. The reference runtime is what makes "semantic portability" real rather than aspirational.

---

## 12. Open questions / decisions needed

1. **Task identity source** — does AsyncIt mint task ids, or consume RunIt correlation ids? Likely AsyncIt mints, RunIt correlates.
2. **Suspension trigger granularity** — explicit `await*` calls only, or also support runtime blocking-hook rewriting (Swoole `SWOOLE_HOOK_ALL`)? Reasoning: support explicit model as canonical; treat hook model as an adapter convenience, never required.
3. **Structured concurrency primitives** — how explicit? Full task-tree (structured) vs lightweight scopes. Recommend scopes first, tree later.
4. **Scheduler queue policy** — FIFO minimal for M4; priority/weighted later, selected by capability.
5. **`StreamPollHandle` ownership** — is it returned to the caller or held by the scheduler registry? Recommend scheduler-held, auto-released on terminal transition, with manual release API for edge cases.
6. **Serialization** — tasks/Fibers are non-serializable by design; we do **not** persist suspended tasks. Confirm this is acceptable (it is, per background docs). Capability descriptors and task *state* (enum) are serializable.
7. **Error model** — original business exception must survive; adapter/runtime exceptions must not replace it. Need a clear "wrap, don't swallow" rule in the await boundary.

---

## 13. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Coupling scheduler to a runtime | Strict DepTrac: Domain depends only on Contracts/Ports; adapters isolated. |
| Context bleed in persistent workers | Detach/reattach via `ContextStoragePort`, proven by Assertion A. |
| `StreamPollHandle` / timer leaks | Registry drained on every terminal transition; Assertion C. |
| Wall-clock skew in telemetry | Monotonic `ClockPort` only; Assertion B. |
| RunIt not yet 1.0 | Ports with `Memory`/`Null` reference impls; no hard RunIt dependency. |
| Over-abstraction before need | Contracts added only with a concrete consumer/adapter (per AGENTS.md). |
| Pretending `SelectPoller` is `Io\Poll` | Distinct named impl with documented weaker characteristics. |

---

## 14. One-line summary

AsyncIt's bet is that **behavior, not a shared runtime, is the only portable thing in PHP async** — so we split polling/scheduling/semantics, define the behavior with a deterministic reference runtime, and force every adapter to *conform* rather than merely *implement*.
