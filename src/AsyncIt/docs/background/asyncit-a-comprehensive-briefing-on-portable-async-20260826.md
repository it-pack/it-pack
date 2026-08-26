# AsyncIt: A Comprehensive Briefing on Portable Asynchronous Execution

## Executive Summary

The ItPack ecosystem introduces **AsyncIt** as a portable, capability-oriented asynchronous execution model for PHP. Unlike traditional asynchronous libraries that wrap specific vendor engines, AsyncIt is built on a semantic foundation that separates execution semantics from runtime scheduling. This briefing document outlines the architectural shift from vendor-oriented wrappers to behavior-standardized systems, emphasizing the integration of AsyncIt with the mature **RunIt** semantic backbone.

The critical insight governing this architecture is the mechanical split: **Polling is not scheduling, and scheduling is not execution semantics.** By isolating these concerns, AsyncIt ensures that high-level applications remain future-proof against shifts in the PHP platform, such as the introduction of native polling APIs in PHP 8.6.

---

## The ItPack Semantic Stack

The ItPack ecosystem is organized into a five-tier stack where every package owns exactly one conceptual concern. This "Consumption Without Responsibility" model allows higher layers to leverage asynchronous capabilities without managing concurrency mechanics.

| Layer | Package | Core Question | Primary Responsibility |
| :--- | :--- | :--- | :--- |
| **Intent** | **Spindle** | *What* needs to be done? | Expresses pure business intent; oblivious to execution mechanics. |
| **Orchestration** | **AExO** | *Why* and in what order? | Manages macro-level workflows, agents, and MCP servers. |
| **Execution** | **RunIt** | *What* is an execution? | Identity, immutable context, activities, and telemetry artifacts. |
| **Progress** | **AsyncIt** | *How* does it progress? | Micro-scheduling: tasks, timers, awaiting, and fiber control. |
| **Description** | **ConfigureIt** | *How* is it described? | Declarative configuration resolution and secret validation. |

---

## Architectural Philosophy: Capability-First Design

AsyncIt rejects the historical "vendor-oriented" approach (e.g., creating a hardcoded Swoole adapter). Instead, it utilizes a **Capability-First Architecture** characterized by:

*   **Behavioral Standardization:** AsyncIt standardizes operational behaviors (task lifecycles, cooperative cancellation, timeout propagation) rather than passive objects.
*   **Negotiable Capabilities:** The system queries the runtime at the process boundary (e.g., "Can you run Fibers?" "Do you support edge-triggered polling?") rather than checking for specific extension names.
*   **Semantic Portability:** The framework acknowledges that extension-based runtimes (Swoole/Swow) and pure-PHP loops (ReactPHP/Revolt) are mutually incompatible. AsyncIt focuses on making *concurrency concepts* portable rather than forcing incompatible loops to host one another.
*   **Hexagonal Isolation:** Using a Ports-and-Adapters layout, the core domain depends only on basic contracts. Implementation details (Swoole, Revolt, Io\Poll) are relegated to thin, swappable adapters.

---

## Async PHP Landscape and Interoperability

The current PHP ecosystem is fragmented into three architectural families. AsyncIt acts as the semantic layer that unifies these diverse implementations.

### 1. Asynchronous C Extensions
*   **Swoole / OpenSwoole:** Embeds a C++ event reactor and coroutine runtime. It utilizes runtime hooks (`SWOOLE_HOOK_ALL`) to convert blocking calls into non-blocking coroutines.
*   **Swow:** A newer Apache-2.0 extension supporting Windows/Linux/macOS via libuv. It exposes native CSP-style channels and fiber-based coroutines.

### 2. Pure-PHP Userland Libraries
*   **ReactPHP:** A mature callback and Promise-based Reactor pattern. It is highly portable and supports PHP 5.3+.
*   **Amp v3 / Revolt:** Built strictly around PHP 8.1+ Fibers. Revolt acts as the low-level scheduler and event loop driver, unifying Amp and ReactPHP libraries through adapters.

### 3. Engine-Level Integrations
*   **Native PHP 8.6 Polling API (Io\Poll):** A cross-platform I/O multiplexing primitive integrated into the PHP core. It replaces legacy `stream_select()` limitations but does not handle task scheduling.
*   **"True Async" Extension:** An experimental engine-level framework using libuv to provide native async/await syntax and structured concurrency.

### Interoperability Matrix
| Source | Swoole | Revolt | Native 8.6 | ReactPHP |
| :--- | :---: | :---: | :---: | :---: |
| **AsyncIt** | Adapter | Adapter | Adapter | Interop Layer |
| **Swoole** | — | Incompatible | N/A | Incompatible |
| **Revolt** | Incompatible | — | Adapter Target | Compatible (via adapter) |

---

## Execution Context and Fiber Safety

AsyncIt consumes the context decisions made by **RunIt** to ensure memory safety in persistent-worker environments.

### Immutable Context Propagation
Context describes the immutable state (e.g., Trace IDs, Tenant IDs) surrounding an execution.
*   **Immutability:** Modifications always return a new copy (Copy-on-Write).
*   **Safety Boundaries:** Context rejects raw PHP objects/resources and enforces a 64-entry limit to prevent memory bloat.
*   **Classification:** Entries are marked (Public, Confidential, Secret, etc.) to dictate redaction behavior during serialization.

### Fiber-Safe Storage Mechanics
To prevent "request leakage" (where data from one request bleeds into another), AsyncIt coordinates with RunIt's storage port during Fiber scheduling:
1.  **Suspension:** When a Fiber yields (awaiting I/O), the Context Adapter **detaches** the active context from the ambient scope.
2.  **Resumption:** When the scheduler wakes the Fiber, the adapter **re-attaches** the matching context.
3.  **Isolation:** This ensures that no fiber can ever read or mutate another's execution context, regardless of whether the runtime is Swoole, Swow, or Revolt.

---

## Cooperative Cancellation and Lifecycle

AsyncIt adopts RunIt's state machine for all asynchronous tasks.

*   **Lifecycle States:** `created` → `running` → [`succeeded` | `failed` | `cancelled` | `timed_out`].
*   **Observed State Changes:** Cancellation and timeouts are treated as observed state changes rather than preemptive interruptions. A running Fiber must cooperatively check its cancellation status and exit gracefully.
*   **Resource Cleanup:** Cooperative exit allows tasks to release OS-level file watchers (e.g., `StreamPollHandle`) and associated handles, preventing catastrophic file descriptor leaks.
*   **Structured Concurrency:** Parent-child relationships govern how signals cascade. If a parent task is cancelled, the signal propagates to all children unless they are explicitly "detached."

---

## Implementation Strategy and Roadmap

AsyncIt follows a conformance-led development path, prioritizing behavioral verification over immediate library wrapping.

### Developmental Milestones
| Milestone | Focus | Deliverable |
| :--- | :--- | :--- |
| **M0–M1** | **Landscape & Boundaries** | Architecture Decision Records (ADRs) and "Backend Truth Table." |
| **M2–M3** | **Capability & Contracts** | Minimal interface set and behavioral conformance verifiers. |
| **M4–M5** | **Reference & Integration** | Pure PHP deterministic test runtime and RunIt context validation. |
| **M6–M8** | **Core Adapters** | First-party adapters for Revolt, Swoole, and Swow. |
| **M9–M10** | **Interop & Native** | ReactPHP/Amp interoperability and PHP 8.6 Io\Poll integration. |

### The AsyncRuntimeVerifier
The pivot point of the implementation is the **AsyncRuntimeVerifier**. This tool programmatically asserts that any concrete runtime adapter strictly complies with:
*   Fiber-safe context propagation.
*   Cooperative cancellation propagation.
*   Task completion vs. cancellation race safety.
*   Cleanup of OS handles upon terminal state transition.

### Development Environment
For testing against future primitives like the **PHP 8.6 Poll API**, the strategy utilizes an **OCI (containerized) development build**. This provides a specialized "runtime laboratory" to test future features without polluting the host development environment.