# ADR-ITP-002: Formalizing the Execution-Scheduling Boundary (AsyncIt as Infrastructure Port)

## 1. Status and Contextual Landscape

The ItPack ecosystem has reached a critical juncture where **RunIt** provides a mature semantic substrate for execution identity and telemetry. However, the mechanism for asynchronous progression remains a fragmented frontier. The PHP landscape is currently a patchwork of mutually incompatible execution models, ranging from aggressive C-extension runtimes that hijack the engine's lifecycle to pure-PHP userland loops that lack native engine integration. To achieve "Museum-Grade" stability and true vendor neutrality, we must formalize the boundary between the *Identity* of an execution (Semantics) and the *Progression* of its work (Scheduling). This ADR establishes **AsyncIt** as the definitive infrastructure layer for scheduling mechanics, occupying the port reserved under the RunIt semantic backbone.

### Current Ecosystem Fragmentation

| Runtime Category | Architectural Conflict |
| :--- | :--- |
| **C-Extensions** (Swoole, Swow) | Own the process memory and loop; replace the PHP runtime model; exhibit asymmetric interoperability where they cannot natively host foreign loops. |
| **Pure-PHP Loops** (ReactPHP, Revolt) | Single-threaded cooperative loops; rely on manual non-blocking I/O; risk of loop-freezing via legacy blocking calls; disparate cancellation models. |
| **Engine Primitives** (Fibers, `Io\Poll`) | Low-level mechanics (8.1+ Fibers; 8.6+ Polling) that provide readiness notifications but lack task scheduling, structured concurrency, or lifecycle semantics. |

> **Problem Statement: The PHP asynchronous ecosystem suffers from asymmetric interoperability and semantic drift. Persistent-worker environments face catastrophic risks of request leakage and state corruption because runtimes fail to enforce invariant boundaries between concurrent execution contexts.**

The strategic necessity of this decoupling is clear: we must treat platform-level shifts as implementation details, ensuring the core domain remains untouched as the PHP engine evolves.

## 2. The Semantic Kernel: Decoupling "What" from "How"

Strategic decoupling requires a rigorous **Semantic Decomposition**. We must distinguish between *Execution Identity*—the logical definition of a unit of work—and *Scheduling Mechanics*—the physical progression of that work over time. By separating these concerns, we ensure that business logic remains focused on intent while the platform handles the complexity of asynchronous suspension.

### Semantic Responsibility Matrix

*   **RunIt (The "What" - Semantic Backbone):**
    *   **Identity:** Absolute ownership of execution and activity aggregate roots.
    *   **Lifecycle State:** Enforcement of closed-state transitions (Created → Running → Terminal).
    *   **Immutable Context:** Copy-on-Write (COW) metadata propagation with strict classification/redaction (Public to Secret).
    *   **Telemetry (Artifacts):** Root owner of Activities, Measurements, Observations, and Occurrences.
*   **AsyncIt (The "How" - Scheduling Mechanics):**
    *   **Tasks:** The micro-unit of scheduled work within an execution.
    *   **Scheduling:** Management of Fiber suspension/resumption and task-queue priority.
    *   **Awaiting:** Interacting with low-level polling providers for resource readiness.
    *   **Timers & Synchronization:** Managing delays and native/emulated channel mechanics.
    *   **Cancellation Mechanics:** The physical propagation of observed "stop" signals to underlying resource handles.

This decomposition enables **"Consumption Without Responsibility."** For example, **ConfigureIt** can perform complex asynchronous network calls (to Vault or Consul) via AsyncIt without being "aware" of the underlying event loop. ConfigureIt decisides *why* the configuration is resolved, while AsyncIt manages the *how* of the network wait.

## 3. Decision: AsyncIt as an Infrastructure Port

We formally designate **AsyncIt** as the canonical implementation of RunIt’s `ExecutionEngineInterface`. In our hexagonal architecture, AsyncIt occupies the infrastructure port reserved for asynchronous progression, ensuring that the domain logic remains isolated from the specific mechanics of the PHP runtime.

### The Hexagonal Flow

1.  **Spindle (Intent):** Expresses the pure semantic intent (e.g., "Audit Tenant Profile"), agnostic of execution mechanics.
2.  **AExO (Orchestration):** Decides the macro-orchestration, workflow order, and retry/compensation logic.
3.  **RunIt (Semantics):** Initializes the execution identity, generates a `ContextToken`, and prepares the artifact recorders.
4.  **AsyncIt (Scheduling):** Receives the work closure, wraps it in a Fiber, and manages its micro-scheduling via the active runtime adapter.

### Boundary Isolation & Invariant Enforcement

To prevent request leakage, AsyncIt is forbidden from inventing a parallel context system. It must strictly consume RunIt’s `ContextStorageInterface`. We insist on the following mechanical invariant: during Fiber suspension (yielding control to the poller), AsyncIt must coordinate with the storage adapter to **detach** the active context from the Fiber scope. Upon resumption, the matching context must be **re-attached** via a `finally` block to ensure no state bleeds across concurrent request boundaries.

## 4. Capability-First Architecture vs. Vendor Wrapping

Traditional "SwooleAdapters" are legacy anti-patterns that offer "False Interoperability." We adopt a **Capability-Oriented** model where the system queries the runtime for native features at the process boundary.

### Backend Capability Truth Table

| Capability | AsyncIt Semantic | Swoole | Swow | Revolt | Native PHP 8.6 |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Fiber Execution** | Core Requirement | Native (8.1+) | Native (libuv) | Mandatory | Native Primitives |
| **Edge-Triggered Polling** | Supported | Yes | Yes (libuv) | Yes (via ext-ev) | Yes (`Io\Poll`) |
| **Native Channels** | Supported | Yes | Yes | Emulated | Planned RFC |
| **Context Propagation** | Core Consumer | Port Adapter | Port Adapter | Port Adapter | Native (Planned) |
| **Cooperative Cancellation**| Observed State | Limited | Yes | Yes | Yes (RFC) |

**Semantic Portability** is our objective. Because extensions (Swoole) and loops (Revolt) utilize mutually incompatible process models, we do not promise that they can host one another. Instead, we guarantee that code written for the AsyncIt semantic layer will behave identically across any runtime that advertises the necessary capabilities.

## 5. The Polling Axis: Future-Proofing via Io\Poll

The introduction of the PHP 8.6 `Io\Poll` API validates our mechanical split: **Polling $
eq$ Scheduling $
eq$ Semantics**.

`Io\Poll` is a low-level platform multiplexer (epoll/kqueue) and is **not** a complete event loop. AsyncIt uses `Io\Poll` strictly as a "poll provider" to notify the scheduler of resource readiness. The responsibility for the Fiber stack management and the Task Scheduler remains entirely within AsyncIt. This ensures that platform-level shifts in PHP core are "zero-day" compatible.

### Fallback Strategy

In pre-8.6 environments (e.g., OCI development labs or legacy deployments), AsyncIt utilizes a `SelectPoller` fallback. This fallback honors the same behavioral contract but operates within the 1024 file-descriptor limits of legacy `stream_select`, ensuring the application remains stable across the entire deployment lifecycle.

## 6. Behavioral Conformance: The AsyncRuntimeVerifier

We move from interface-based standards to behavioral-conformance standards. Any adapter claiming to support AsyncIt must pass the **AsyncRuntimeVerifier**. This shifts our focus from "standardizing objects" to "standardizing operational mechanics."

### AsyncRuntimeVerifier Requirements

*   **Context Isolation:** Programmatically prove that context is detached *at the specific yield point* of the `Io\Poll` wait and re-attached on resumption. Interleave concurrent tasks to prove zero state bleed.
*   **Observed Cancellation:** Prove that cancellation is a cooperative state change, not a preemptive kill. Verify that tasks can finish critical sections and drain resources before exiting.
*   **Handle Cleanup:** Assert that OS-level file descriptors and `StreamPollHandle` objects are released upon terminal transitions. A single leaked descriptor is a failure of conformance.

We prioritize **Reference Implementations** (pure-PHP/deterministic) as the definitive behavior for all adapters, ensuring "Museum-Grade" quality regardless of the vendor.

## 7. Consequences and Long-Term Ecosystem Impact

This ADR establishes the governance for the ItPack execution platform through its roadmap (M0 to M10), moving the ecosystem toward a future of verified, stable progression.

### Positive Consequences
*   **Vendor Neutrality:** Eliminates lock-in to specific async engines; applications remain portable.
*   **Leak Prevention:** Strict context isolation via `ContextStorageInterface` prevents request-state contamination.
*   **OTel-Ready Telemetry:** Native mapping of scheduler events (task spawn/resume) to RunIt artifacts (Occurrences/Measurements).
*   **Zero-Day Compatibility:** Ready for native engine changes like `Io\Poll` and the "True Async" initiative.

### Negative Consequences and Risks
*   **Abstraction Overhead:** Minimal performance cost associated with semantic layering and context-switching invariants.
*   **Cooperative Discipline:** Requires developer adherence to structured concurrency and cooperative cancellation patterns.

This Architecture Decision Record stands as the **Strategic Artifact** for the ItPack execution platform, providing the definitive governance required to weave disparate asynchronous fibers into a coherent, high-performance thread of execution.