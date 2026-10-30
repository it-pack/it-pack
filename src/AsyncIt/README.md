# AsyncIt

> **Portable, Capability-Oriented Asynchronous Execution and Scheduling for the ItPack Semantic Stack.**

AsyncIt is a core component of the **ItPack ecosystem**. Rather than acting as a vendor-specific wrapper for existing asynchronous PHP engines, AsyncIt establishes an **implementation-neutral, capability-driven execution platform**. It defines the mechanical progression of asynchronous work strictly decoupled from underlying operating system multiplexers, polling engines, or telemetry frameworks.

---

## 1. The Five-Tier Semantic Stack

The ItPack architecture enforces the principle of **"Consumption Without Responsibility"**. Higher-level packages consume complex asynchronous capabilities without inheriting the responsibility of managing low-level scheduling mechanics, Fiber suspension, or system polling:

| Tier | Layer | Package | The Core Question Answered | Focus |
|---|---|---|---|---|
| **Tier 1** | **Intent** | **Spindle** | *What* is the business intent? | Pure business intent; oblivious to execution transport or timing. |
| **Tier 2** | **Orchestration** | **AExO** | *Why* is it happening, and in what order? | Macro-coordination of agents, workflows, and retry/compensation logic. |
| **Tier 3** | **Execution** | **RunIt** | *What* is an execution? (The Backbone) | Standardized identity, immutable context, occurrences, and measurements. |
| **Tier 4** | **Scheduling** | **AsyncIt** | *How* does the work progress? | Schedulers, Fiber queue control, cooperative task execution, and timers. |
| **Tier 5** | **Platform** | **Engine/OS** | *How* is I/O multiplexed? | Hardware triggers (e.g., native PHP 8.6 `Io\Poll`, kernel `epoll`/`kqueue`). |

AsyncIt is the canonical implementation of RunIt's `ExecutionEngineInterface`: it consumes RunIt's execution model (identity, immutable context, artifacts) and never redefines it. Higher tiers remain durable across engine evolution.

---

## 2. Core Architectural Pillars

### A. Capability-First Architecture

AsyncIt avoids the "vendor-wrapping" trap by querying the runtime at the process boundary. The environment is analyzed once, and a queryable **Capability Descriptor** is generated:

- Instead of brittle checks like `extension_loaded('swoole')`, AsyncIt asks functional questions: *"Can you run Fibers natively? Do you support edge-triggered polling? Do you have native channel synchronization?"*
- It respects the asymmetry of the PHP async ecosystem—accepting that extension-based runtimes (Swoole, Swow) and pure-PHP loops (ReactPHP, Revolt) rely on incompatible process models. AsyncIt focuses on **semantic portability** instead of forcing incompatible loops to host one another.
- Each capability is categorized as `Supported`, `Emulated`, `Adapter-Only`, or `Unavailable`. The descriptor is the single source of truth for selecting scheduling policy.

### B. Fiber-Safe Context Propagation (The Sequence of Suspension)

Persistent-worker environments (Swoole, Swow, RoadRunner) carry severe risks of **request leakage**. AsyncIt enforces a strict architectural invariant when a Fiber yields control:

1. **Suspension**: A Fiber hits an I/O wait boundary (e.g., awaiting stream readiness) and yields.
2. **Detachment**: The RunIt Context Adapter immediately **detaches the active context** from the ambient Fiber scope. The context is locked away while the task is idle.
3. **Wait**: The process safely blocks on low-level polling (e.g., `Io\Poll\Context::wait()`) with no context bound to the scope, preventing interleaved tasks from bleeding into one another.
4. **Resumption & Re-attachment**: Once signaled, AsyncIt resumes the Fiber, and the adapter **safely re-attaches the matching execution context** to the resumed Fiber scope.

### C. Observed Cooperative Cancellation

AsyncIt rejects preemptive, violent task interruption (which can lead to file corruption and resource exhaustion). Cancellation is modeled as an **observed state change**:

- Schedulers transition tasks through a closed-state lifecycle: `created -> running -> terminal`.
- Running tasks cooperatively check their cancellation status. Upon observing a cancel signal, they perform a structured unwind and **explicitly release OS-level file watchers** (e.g., `StreamPollHandle` objects) back to the polling context to prevent descriptor leaks.
- Conflicting terminal transitions (e.g., succeeding an already `timed_out` task) raise a `ConflictingCompletionException`.
- In structured concurrency, cancellation cascades from parent to children unless explicitly detached.

---

## 3. The Concurrency Split: Polling vs. Scheduling

To future-proof the platform, AsyncIt enforces a strict separation of concerns:

$$\text{Polling (I/O Multiplexing)} \ \neq \ \text{Scheduling (Task Control)} \ \neq \ \text{Execution Semantics (Context \& Telemetry)}$$

- **Polling (Engine Layer)**: Managed natively in PHP 8.6 by the **Polling API (`Io\Poll`)**. It unifies low-level OS notifications (epoll, kqueue, WSAPoll) but does *not* manage fibers, task queues, or timers. A `SelectPoller` reference fallback provides the same behavioral contract within the legacy `stream_select` descriptor ceiling.
- **Scheduling (AsyncIt Layer)**: Decides *how* work progresses. Schedulers coordinate task queues, cooperative cancellation cascades, and Fiber suspension/resumption.
- **Execution Semantics (RunIt Layer)**: Defines *what* the execution is. It maps scheduler events directly to RunIt telemetry: task spawns are recorded as *Occurrences*, task durations (using monotonic clocks) as *Measurements*, and anomalies as *Observations*.

---

## 4. Conceptual Usage Example

```php
declare(strict_types=1);

use ItPack\AsyncIt\AsyncRuntime;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;

// Initialize the portable AsyncIt runtime; capabilities are negotiated automatically.
$runtime = AsyncRuntime::discover();

// Build an immutable execution scope from RunIt.
$scope = new ReferenceExecutionScope('example');

// Run a single operation inside the reference reactor.
$result = $runtime->run($scope, static function (): string {
    // The operation runs in a Fiber. Context is attached on resume
    // and detached under finally (conformance Assertion A).
    return 'Hello from AsyncIt';
});

echo $result; // Hello from AsyncIt
```

> The `spawn()` and `awaitReadable()` primitives shown in earlier drafts are part of the **next-generation roadmap** (Phase 6+ in `docs/ROADMAP.md`) and are not yet implemented. They will be added with conformance-gated semantics once the multi-task model lands. See `docs/FEATURE_BACKLOG.md` for details.

---

## 5. Public API Reference

### SDK Entry Point
- **`ItPack\AsyncIt\SDK\AsyncRuntime`** — The reference RunIt execution engine; discovered via `AsyncRuntime::discover()`, then `run(ExecutionScopeInterface, callable): mixed`. Also exposes `channel()`, `telemetry()`, `capability()`.

### Core Contracts
- **`ItPack\AsyncIt\Contract\EngineSchedulerInterface`** — Scheduling port used by the RunIt `ExecutionEngineInterface`; extends `SchedulerInterface` with `dispatch()`.
- **`ItPack\AsyncIt\Contract\SchedulerInterface`** — Core scheduling contract.
- **`ItPack\AsyncIt\Contract\TaskInterface`** / **`TaskId`** — Task identity within an execution.
- **`ItPack\AsyncIt\Contract\PollerPortInterface`** — Port for readiness probing (`register()`, `release()`, `await()`, `activeHandleCount()`).
- **`ItPack\AsyncIt\Contract\TaskChannelInterface`** — Channel abstraction (native or emulated) for task coordination.
- **`ItPack\AsyncIt\Contract\RuntimeDiscoveryInterface`** — Runtime adapter discovery and capability negotiation.
- **`ItPack\AsyncIt\Contract\CapabilityDescriptor`** / **`CapabilityStatus`** / **`CapabilityKind`** — Negotiated capability model.
- **`ItPack\AsyncIt\Contract\CancellationScopeInterface`** — Observed cooperative cancellation boundary.
- **`ItPack\AsyncIt\Contract\TelemetryBridgeInterface`** — Telemetry integration port.

> The `spawn()` and `awaitReadable()` primitives are planned for Phase 6 (M11–M12). See `docs/ROADMAP.md` for sequencing.

---

## 6. Development and Conformance Roadmap

To ensure total behavioral compliance across vastly different runtime adapters, AsyncIt relies on a **conformance-led QA pipeline**. Adapters are continuously verified against the **`AsyncRuntimeVerifier`** test harness:

```
Contracts ──> Conformance (Verifiers) ──> Adapters
```

1. **M0 — Landscape Freeze**: Finalize async landscape truths and `Io\Poll` specs. No code.
2. **M1 — Semantic Boundary**: Formalize boundaries (RunIt ↔ AsyncIt ↔ AExO) via Architecture Decision Records (ADRs).
3. **M2 — Capability Model**: Taxonomize negotiable capability descriptors.
4. **M3 — Minimal Contracts**: Establish the smallest contract set with negative tests and conformance verifiers.
5. **M4 — Reference Implementation**: Build a pure-PHP deterministic test runtime to establish semantic baselines.
6. **M5 — RunIt Integration**: Validate context isolation and detachment during suspension.
7. **M6 to M8 — Core Adapters**: Develop Revolt (M6), Swoole (M7), and Swow (M8) drivers.
8. **M9 to M10 — Interoperability & Native**: Implement userland interop layers and slide the native PHP 8.6 `Io\Poll` API into its dedicated adapter slot.

### AsyncRuntimeVerifier

An adapter is valid only when it passes the `AsyncRuntimeVerifier` suite. Mandatory assertions:

- **Assertion A — Context Isolation**: Interleave concurrent tasks and prove zero context bleed across suspension/resumption cycles.
- **Assertion B — Temporal Precision**: Verify monotonic-clock integration; durations must be free of wall-clock skew.
- **Assertion C — Deterministic Cleanup**: Prove no dangling watchers; every `StreamPollHandle` and timer is released immediately after timeout or cancellation.

A single leaked descriptor or one context-bleed is a conformance failure.

---

## 6. Relationship to RunIt

AsyncIt does not own an execution model—it consumes RunIt's. RunIt owns identity, immutable context, and telemetry artifacts; AsyncIt owns the mechanics that move work forward. Publication into RunIt's artifact stream uses a port with a recursion-safe failure policy, and context propagation relies exclusively on RunIt's `ContextStorageInterface`. AsyncIt must never invent a parallel context system.

---

## 7. PSR posture

PSR packages (psr/clock, psr/log, psr/event-dispatcher, psr/http-*) are **not**
runtime dependencies of AsyncIt. They appear only in `require-dev` for adapter
test suites and in `suggest` where PSR-compatible bridges are optional. The core
scheduling and runtime-discovery contracts depend solely on RunIt's own port
interfaces.

---

## License

AsyncIt is distributed under the **MIT License**.
All core conceptual definitions align with the canonical ItPack OWL ontologies.
