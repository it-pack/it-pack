# AsyncIt: A Capability-Oriented Asynchronous Execution Model for the ItPack Ecosystem

## 1. The Evolutionary Context: From Fragments to Foundations

The emergence of the ItPack ecosystem signals a decisive strategic pivot from "vendor-wrapping" to "semantic standardization." For too long, the PHP landscape has been cluttered with abstractions that serve as mere facades for specific libraries, tethering architectural integrity to transient implementation details. AsyncIt transcends this by defining the ontological substrate of asynchronous execution independently of any specific engine, ensuring that business intent remains resilient against the shifting sands of platform evolution.

The current state of PHP asynchrony is defined by profound fragmentation. On one flank, **Asynchronous C Extensions** (Swoole, OpenSwoole, Swow) fundamentally alter the PHP memory model, embedding high-performance reactors (utilizing epoll, kqueue, or io_uring) and coroutine runtimes directly into the engine. On the other flank, **Pure-PHP Userland Libraries** (ReactPHP, Amp, Revolt) achieve non-blocking I/O through cooperative event loops and Fiber-based schedulers. These paradigms are often mutually incompatible, creating silos where libraries cannot natively coexist.

AsyncIt’s "Emergent Design" was not a top-down imposition but a discovery born of a year of adversarial stress-testing. Originally conceived within the high-stakes environment of game engine development, we realized that the mechanics of a game engine—progression, state transitions, and high-frequency orchestration—are identical to those of a robust business platform. Concepts such as "immutable context" and "execution-as-a-first-class-citizen" survived this evolutionary crucible because they describe universal laws of progression rather than fashionable API preferences.

### The Philosophy of Survival
The following primitives represent the "surviving" mechanics of the ItPack model:

*   **Semantic Objects over Ephemeral Strings:** We prioritize rich, versioned entities—Measurements, Observations, and Occurrences—over the lossy and fragmented nature of log strings.
*   **Immutable Contextual Integrity:** State is strictly bounded and copy-on-write to prevent the catastrophic "request leakage" inherent in persistent-worker environments.
*   **Execution as the Root Aggregate:** All runtime phenomena are children of a singular Execution; telemetry and configuration are merely different semantic views of this operational truth.
*   **Capability-Driven Negotiation:** The system negotiates capabilities (e.g., "Can you run Fibers?") rather than performing brittle vendor-specific checks.

This departure from historical fragmentation necessitates a unified semantic stack, applying continuous tension—much like a Spindle—to weave loose async fibers into a coherent, high-stakes execution thread.

---

## 2. The Five-Tier Semantic Stack: Defining the "Why" and "How"

The ItPack ecosystem is governed by the principle of **"Consumption Without Responsibility."** This architectural invariant allows high-level components to utilize sophisticated asynchronous capabilities without being burdened by the mechanical complexity of Fiber suspension, I/O multiplexing, or scheduler internalities. By maintaining a strict hierarchy of concerns, the purity of business intent is protected from the noise of infrastructure.

### The Five-Tier Semantic Stack

| Layer | Component | The Single Question Answered |
| :--- | :--- | :--- |
| **Tier 1** | **Spindle** | What is the business intent? |
| **Tier 2** | **AExO** | Why is this happening and in what order? |
| **Tier 3** | **RunIt** | What happened? (The Semantic Backbone) |
| **Tier 4** | **AsyncIt** | How does the work progress? (The Progress Engine) |
| **Tier 5** | **Platform** | How does the OS multiplex the I/O? (Engine Primitives) |

In this stack, **RunIt** is the absolute center of gravity, defining the "What" (identity, lifecycle, and context). **AsyncIt** serves as the canonical implementation of RunIt’s `ExecutionEngineInterface`, defining the "How" (task mechanics and scheduling). AsyncIt does not invent its own telemetry model; it consumes RunIt’s artifacts, publishing scheduler events as Occurrences and task durations as Measurements. This ensures that Tier 1 (Spindle) remains entirely oblivious to whether a task is propelled by a Swoole coroutine or a native PHP Fiber.

---

## 3. Capability-Oriented Domain Decomposition

Future-proofing requires standardizing **behaviors** rather than **objects**. This avoids the structural limitations seen in historical standards like PSR-7, which focused on passive in-memory objects but failed to capture the operational mechanics of the runtimes consuming them.

### Backend Capability Truth Table
AsyncIt treats underlying runtimes as sets of negotiable capabilities. This "Capability-First" approach allows the system to query the runtime environment at the process boundary.

| Capability | Swoole | Swow | Revolt | Native PHP (8.6+) |
| :--- | :--- | :--- | :--- | :--- |
| **Fiber Execution** | Native/Optional | Mandatory | Mandatory | Native Primitives |
| **Edge-Triggered Polling** | Yes (Default) | Yes (libuv) | Yes (via ext-ev) | Yes (`Io\Poll`) |
| **Native Channels** | Yes | Yes | Emulated | Planned RFC |
| **Structured Concurrency** | Partial | Partial | Yes (Task Groups) | Planned RFC |
| **Blocking Hook Model** | Yes | Yes | No | Experimental |

### The Minimal Semantic Kernel
The AsyncIt kernel decomposes the async domain into five core behavioral invariants:
1.  **Task Model:** The definition of an asynchronous unit of work, distinct from the engine's internal Fiber.
2.  **Scheduling:** The deterministic management of task queues and priority-driven execution.
3.  **Awaiting:** The mechanics of yielding control while awaiting resources without blocking the reactor.
4.  **Suspension & Resumption:** The safe pausing and restarting of Fiber state, inclusive of context isolation.
5.  **Synchronization:** The coordination of state between concurrent tasks (e.g., CSP-style Channels).

AsyncIt targets **"Semantic Portability"** rather than "False Interoperability." Because extension-based runtimes fundamentally alter the PHP memory model, making them natively host pure-PHP loops is a physical impossibility. AsyncIt accepts this asymmetric reality, ensuring code behaves identically on any engine without forcing incompatible engines to interoperate.

---

## 4. The Infrastructure Boundary: Decoupling Polling from Progress

The introduction of the PHP 8.6 `Io\Poll` API represents an ontological shift in the engine. By providing native, cross-platform I/O multiplexing, the engine finally solves the "1024-descriptor ceiling" of legacy `stream_select` by leveraging OS-level primitives like **epoll**, **kqueue**, and **io_uring**.

However, we maintain a strict mechanical split: **Polling is not Scheduling; Scheduling is not Execution Semantics.**
*   **`Io\Poll`** is responsible strictly for I/O multiplexing—notifying the system when a descriptor is ready for work.
*   **AsyncIt** is responsible for Fiber control and task management.

### Pre-PHP 8.6 Strategy
To maintain progress before the native API matures, we utilize three distinct strategies:
1.  **OCI/Containerized Laboratory:** Utilizing specialized PHP 8.6-dev builds in isolated containers to test native polling mechanics.
2.  **`SelectPoller` Fallback:** A reference implementation utilizing `stream_select` for legacy environments, accepting the descriptor ceiling as a transient constraint.
3.  **Adapter Slotting:** The native Poll API slides into a "poll provider" slot beneath the AsyncIt scheduler, ensuring the higher-level AExO workflows remain entirely untouched by platform evolution.

---

## 5. Operational Invariants: Context, Cancellation, and Lifecycle

In persistent-worker environments (Swoole, RoadRunner), the risk of **"request leakage"** is a critical security and integrity concern. AsyncIt prevents this through an ontological rigor applied to context propagation.

### Immutable Contextual Constraints
The `ExecutionContext` is governed by strict engineering limits to prevent memory bloat and data exfiltration:
*   **Payload Bounds:** A maximum of **64 entries**, with individual values limited to **4,096 bytes**.
*   **Type Safety:** Raw PHP objects and resources are strictly rejected to ensure serializability.
*   **Tiered Classification:** Entries are bound to a five-tier system: **Public, Internal, Confidential, Restricted, and Secret**. "Secret" values are never propagated or exported, ensuring a "deny-by-default" security posture.

### The Context Orchestration Sequence
AsyncIt coordinates with the `ContextStorageInterface` (and specifically its Fiber-aware implementation) during task switching:
1.  **Attach:** Upon Fiber resumption, the adapter attaches the specific immutable context to the Fiber’s local scope.
2.  **Detach on Yield:** When the Fiber yields (e.g., waiting for an `Io\Poll` event), the context is explicitly detached from the ambient scope.
3.  **Re-attach:** Once the poller signals readiness, the exact context is re-attached before execution continues. This prevents concurrent tasks from "bleeding" into one another.

### Cooperative Cancellation and Lifecycle
In our model, cancellation is an **observed state change**, not a preemptive interruption. Tasks follow a strict state machine:
**Created $\rightarrow$ Running $\rightarrow$ [Succeeded | Failed | Cancelled | Timed_out]**

Violent interruptions lead to resource leaks; therefore, tasks must cooperatively check for cancellation and manually release handles (like the `StreamPollHandle`). Conflicting state transitions (e.g., attempting to mark a "Timed_out" task as "Succeeded") trigger a `ConflictingCompletionException`.

---

## 6. The Conformance Roadmap: From ADR to Adapter

AsyncIt utilizes a **"Contracts $\rightarrow$ Conformance $\rightarrow$ Adapters"** methodology. We do not write code for a specific vendor first; we define the behavioral contract and the programmatic means to prove compliance via the **`AsyncRuntimeVerifier`**.

### The 11-Milestone Roadmap

**Phase 1: Foundation (M0–M2)**
*   **M0–M2:** Landscape freeze, terminology glossary, and the definition of semantic boundaries between RunIt, AsyncIt, and AExO via Architecture Decision Records (ADRs).

**Phase 2: Kernel (M3–M5)**
*   **M3–M5:** Development of minimal contracts and a **deterministic pure-PHP reference runtime**. Implementation of the `AsyncRuntimeVerifier` to assert context isolation, structured concurrency, and cancellation propagation.

**Phase 3: Adapters (M6–M10)**
*   **M6–M10:** Construction of adapters for Revolt, Swoole, and Swow, culminating in the native PHP 8.6 `Io\Poll` integration. An adapter is only valid if it passes 100% of the `AsyncRuntimeVerifier` suite.

The "Code Generation Ready" gate is reached when the semantic model provides immutable answers to the questions of task lifecycles, context isolation, and failure propagation. As architects, we apply the tension of the Spindle to weave these disparate threads into a singular, robust execution platform.