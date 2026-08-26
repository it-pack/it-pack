# AsyncIt: Capability Negotiation Specification

## 1. Strategic Context and Architectural Vision

The PHP asynchronous landscape has long suffered from "library-specific lock-in," where developers are forced to choose between mutually exclusive ecosystems like Swoole, Revolt, or Swow. This fragmentation creates a high cost of switching and prevents the emergence of a unified library ecosystem. This specification introduces **AsyncIt**, a protocol that shifts the industry toward **semantic portability**. By moving away from vendor-specific implementations and toward a capability-first approach, AsyncIt allows application logic to remain engine-agnostic while scaling to utilize the highest performance primitives available in the runtime.

The primary goal of this specification is to provide a standard protocol for **runtime discovery**. Rather than relying on passive environment checks like `extension_loaded('swoole')`, AsyncIt implements a queryable interface to determine functional capabilities at the process boundary. This enables the platform to interact with features rather than vendor names.

AsyncIt serves as the **Progress Engine** within the broader ItPack stack. Operating under the foundational philosophy that **"everything happens within an execution,"** AsyncIt provides the mechanical progression for work defined by **RunIt**. While RunIt owns the semantic backbone—identity, context, and telemetry—AsyncIt is responsible for the actual advancement of state. It does not own its own telemetry; instead, it publishes directly to RunIt’s artifact stream, ensuring that every scheduler tick and task resumption is recorded as a first-class execution occurrence. This layering allows higher-level orchestrators like AExO to coordinate complex workflows without managing the raw mechanics of polling, scheduling, or execution.

---

## 2. The Semantic Stack: Decomposing Concurrency

Treating "event loops" as monolithic blocks is a legacy anti-pattern that obscures the distinct responsibilities of I/O multiplexing, task scheduling, and execution semantics. To future-proof the PHP 8.6+ landscape, AsyncIt enforces a three-tier mechanical split. This decomposition ensures that the introduction of native primitives, such as the PHP 8.6 `Io\Poll` API, requires only a thin adapter update rather than a structural rewrite.

### The Three-Tier Mechanical Split

| Layer | Responsibility | Component | Implementation Detail |
| :--- | :--- | :--- | :--- |
| **Layer 3: Execution** | Identity, Context, and Telemetry | **RunIt** | The semantic backbone. Manages trace IDs and immutable context. Defines *what* an execution is. |
| **Layer 2: Scheduling** | Task Control and Progression | **AsyncIt** | The "Progress Engine." Manages Fiber control, task queues, and timers. Defines *how* work progresses. |
| **Layer 1: Polling** | Native I/O Multiplexing | **Engine/OS** | Low-level notifications via `Io\Poll`. This is a primitive multiplexer, not a complete event loop. |

### Consumption Without Responsibility
This hierarchy enables **"Consumption Without Responsibility."** Higher-level engines, such as the AExO orchestration layer, coordinate agent behaviors and workflows by interacting solely with the `Task` abstraction. AExO remains blissfully ignorant of the underlying Fiber mechanics or whether the I/O is being multiplexed by a C-extension reactor or the native PHP 8.6 `Io\Poll` backend. This isolation ensures that business logic remains durable across the evolving PHP version landscape.

---

## 3. The Capability Negotiation Protocol

AsyncIt replaces passive interface checking with **active capability discovery**. At the process boundary, the system performs a functional audit to generate a **Capability Descriptor**. This is a schema-enforced Value Object returned by a `RuntimeDiscovery` service that acts as the absolute source of truth for the active environment.

### Backend Truth Table

The discovery logic evaluates the environment against the following capability matrix:

| Capability | Revolt | Swoole | Swow | Native PHP 8.6 |
| :--- | :--- | :--- | :--- | :--- |
| **Fiber Execution** | Supported | Supported | Supported | Supported |
| **Edge-Triggered Polling** | Supported (via ext-ev) | Supported | Supported | Supported (Io\Poll) |
| **Native Channels** | Emulated | Supported | Supported | Unavailable |
| **Blocking Hook Support** | Unavailable | Supported | Supported | Unavailable |

### Capability Status Definitions
The discovery service categorizes features into one of four immutable statuses:
1.  **Supported:** The runtime provides a native, high-performance implementation (e.g., Swoole's CSP channels).
2.  **Emulated:** The feature is provided via a userspace fallback (e.g., a pure-PHP channel implementation for Revolt).
3.  **Adapter-Only:** The feature is accessible only through a vendor-specific bridge.
4.  **Unavailable:** The capability is not present and cannot be safely emulated (e.g., runtime-level blocking hooks in a pure-PHP loop).

This negotiation phase ensures that the system never "guesses" at performance characteristics, allowing it to select the most efficient scheduling policy for the detected environment.

---

## 4. Fiber-Safe Context Propagation & Storage Mechanics

Persistent-worker environments (Swoole, Swow, RoadRunner) face the critical risk of **"request leakage,"** where metadata or sensitive security context from one execution bleeds into another. AsyncIt mitigates this risk by consuming **RunIt’s immutable context model**, utilizing copy-on-write mechanics to ensure that execution state is isolated and cryptographically secure.

### The Sequence of Suspension: An Architectural Invariant
To guarantee context safety, AsyncIt enforces a strict "Sequence of Suspension" whenever a Fiber yields control to the `Io\Poll` backend. This sequence is a hard guardrail against data exfiltration:

1.  **Suspension:** AsyncIt yields control when a task encounters an I/O boundary (e.g., awaiting a socket).
2.  **Detachment:** The **RunIt Context Adapter** immediately detaches the ambient execution context from the Fiber scope. In multi-tenant Swoole environments, where global state persists, this step is what makes the system "Fiber-Safe" by ensuring the context is unreachable while the task is idle.
3.  **Polling:** The system blocks on `Io\Poll\Context::wait()`. While in this wait state, the suspended task's context is not "ambient" and cannot be accessed by other interleaved Fibers.
4.  **Resumption:** Once the OS signals readiness, the Context Adapter safely re-attaches the specific execution context to the matching Fiber scope before AsyncIt resumes the task.

By decoupling context definition (RunIt) from the mechanics of the wait cycle (AsyncIt), the platform guarantees that execution metadata remains strictly isolated, even in extensions that normally persist process-level context.

---

## 5. Observed Cooperative Cancellation & Lifecycle Control

AsyncIt rejects preemptive interruption—which can lead to inconsistent system states and memory corruption—in favor of **Observed State Changes.** Cancellation is treated as a signal that a task must observe and handle according to its own internal invariants.

### The Lifecycle State Machine
All tasks follow a deterministic, closed-state machine:
`created` -> `running` -> `terminal (succeeded | failed | cancelled | timed_out)`

Transitions are governed by strict completion policies. Any attempt to violate this state machine (e.g., attempting to succeed a task that has already timed out) will trigger a **ConflictingCompletionException**.

### Cleanup Guarantees
To prevent resource exhaustion, AsyncIt enforces rigorous cleanup requirements. Upon observing a cancellation signal, a task must:
1.  Gracefully exit its execution logic.
2.  **Explicitly release OS-level file watchers:** All `StreamPollHandle` objects must be released back to the `Io\Poll\Context`. Failure to do so leads to the descriptor leaks that historically plagued the 1024-handle limit in legacy PHP loops.
3.  Close any associated timers or local synchronization primitives.

In a structured concurrency model, cancellation must cascade. When a parent orchestration is cancelled, AsyncIt propagates the signal down the task boundary, ensuring that all descendant activities perform their cleanup routines in a deterministic sequence.

---

## 6. Behavioral Conformance & The AsyncRuntimeVerifier

Standardizing interfaces (PSR-style) is insufficient for asynchronous PHP because it cannot enforce runtime invariants. Compliance is instead proven through the **AsyncRuntimeVerifier**, a suite of programmatic assertions that verify an adapter’s actual behavior under stress.

### Mandatory Assertions
Every adapter (Revolt, Swoole, Swow) must pass three core assertions:
*   **Assertion A (Context Isolation):** Active proof that zero context-bleed occurs across interleaved Fibers during suspension/resumption cycles.
*   **Assertion B (Temporal Precision):** Verification of the **Monotonic Clock** integration. Adapters must use `hrtime()` to prevent wall-clock skew in recorded `Measurements`, ensuring that telemetry remains accurate regardless of system clock adjustments.
*   **Assertion C (Deterministic Cleanup):** Confirmation that no **Dangling Watchers** exist. All OS resources and `StreamPollHandle` objects must be proven released immediately post-timeout or cancellation.

### The Strategic Baseline
AsyncIt utilizes a **Pure PHP Reference Runtime** as its semantic baseline. By testing all extension-based adapters against this deterministic reference, the specification ensures that the ItPack ecosystem remains **semantic-only**. This enables top-level layers like **Spindle** to express business intent while the underlying engine transparently scales to take advantage of native PHP 8.6+ primitives without compromising architectural integrity.