# The Invisible Leak: Mastering Immutable Context in Async PHP

In the world of traditional web development, PHP has long been celebrated for its "Shared-Nothing" architecture. Every time a user visits a page, the PHP engine starts fresh, executes the code, and then completely wipes the memory clean. This reset made it almost impossible for one user's data to "leak" into another user's session.

However, as we move toward high-performance, asynchronous PHP, this safety net has disappeared. We are entering the era of **Persistent Workers**.

## 1. The Paradigm Shift: From Transient to Persistent Workers

The move from traditional PHP-FPM to persistent environments like Swoole, Swow, or Revolt is a fundamental shift in how applications live in memory. In a persistent worker environment, the PHP process does not die after the request ends; it stays alive to handle the next one immediately. 

While this makes applications incredibly fast, it introduces a dangerous new threat: state persistence.

| Feature | Traditional Request-Response (PHP-FPM) | Persistent Workers (Swoole, Swow, Revolt) |
| :--- | :--- | :--- |
| **Process Lifecycle** | Process starts and terminates for every single request. | Process stays alive for thousands of requests. |
| **Global/Static Variables** | Automatically cleared; state is "transient." | **State survives** across requests; variables persist. |
| **Memory Management** | Automatic "garbage collection" via process exit. | Manual or architectural discipline required to prevent bloat. |
| **Data Leakage Risk** | Virtually zero; each request is a clean slate. | **High;** data corruption and leakage are "inevitable" without a new model. |

Because the environment no longer resets automatically, we can no longer rely on the operating system to clean up after us. We must find a new way to "carry" data safely from the start of a task to its end without it ever bleeding into another user's workspace.

## 2. The Golden Rule: Why Global State is Prohibited

In the **ItPack** architecture, there is a "Deny-by-Default" stance on global and static variables. Relying on `static $user_id` might work in a traditional script, but in a concurrent asynchronous environment, it is a liability.

### The Three Fatal Risks of Global State

1.  **Cross-Request Leakage**: Since the same process handles multiple users, a static variable set for "User A" might still be there when "User B" arrives. This could allow User B to see User A's private Tenant IDs or Security Classifications.
2.  **Race Conditions**: When multiple tasks are running at once (concurrency), they may all try to write to the same global variable at the same time. This leads to unpredictable "corrupted" data that is nearly impossible to debug.
3.  **Memory Bloat**: Global variables are never cleaned up by the engine. Over time, these variables can grow, leading to memory leaks that eventually crash the server.

To solve this, the ItPack ecosystem introduces **RunIt**—an architectural layer that replaces dangerous global state with a controlled, immutable model known as an Execution Context.

## 3. The Anatomy of an Immutable Execution Context

In the ItPack philosophy, the **Execution is the root aggregate**. Everything that happens—logs, metrics, and traces—are simply different semantic views of the same execution. The `ExecutionContext` is the "backpack" that follows a specific execution as it moves through the system.

The core mechanics of the RunIt context model include:

*   **Copy-on-Write Pattern**: You never "edit" a context. If you need to add data, you use methods like `with()` or `without()`. These return a brand-new copy, leaving the original instance untouched.
*   **Hard Boundaries (Security by Constraint)**: To ensure **fast serialization** and **process safety** across boundaries, the context has strict limits:
    *   **MAX_ENTRIES**: A single context can hold no more than **64 entries**.
    *   **Payload Limit**: Individual values are strictly limited to **4,096 bytes** by a recursive validator.
*   **Object Rejection at Any Depth**: The context strictly rejects raw PHP objects and system resources (like database sockets) **at any depth**. This ensures the context remains purely serializable and prevents memory leaks from attached resources.

Once data is securely stored in this immutable context, we must manage its visibility during export or propagation.

## 4. Security Classifications: Protecting the 'Secret'

Not all data is equal. A "Trace ID" used for debugging is less sensitive than a "User Password." ItPack uses five levels of data classification to manage how data is handled during external export.

| Classification Level | Definition | Handling Rule |
| :--- | :--- | :--- |
| **Public** | General information. | Can be exported, serialized, and propagated freely. |
| **Internal** | Logic-specific data. | Used for internal system decisions. |
| **Confidential** | Sensitive but necessary. | Handled with standard encryption/protection. |
| **Restricted** | High-sensitivity data. | **Masked or redacted** (e.g., `****`) during any external export. |
| **Secret** | Maximum sensitivity. | **Deny-by-Default.** Never exported or serialized; it is dropped entirely. |

## 5. The Mechanics of Detach and Re-attach

While security classifications are static rules, the mechanics of context management are dynamic. As tasks pause and resume—usually via **Fibers**—the system must prevent "context bleeding." This is handled by the **ContextStorageInterface**.

The interaction between **RunIt** (The Context Owner) and **AsyncIt** (The Scheduler) follows this sequence:

1.  **Attach**: When a task starts, the RunIt Context Adapter binds the `ExecutionContext` to the current scope. In synchronous models, this uses a stack-based **ContextToken**; in Fiber models, it uses a dedicated storage slot.
2.  **Suspend & Detach**: When the Fiber yields (e.g., waiting on `Io\Poll`), the context is immediately **detached**. This ensures the process is "clean" while the task is asleep, preventing other active tasks from reading the suspended state.
3.  **Wait**: The system waits. For coroutine models like Swoole, the context is strictly cleared or restored in a **finally** block to guarantee safety even if an error occurs.
4.  **Resume & Re-attach**: When the polling backend signals readiness, the scheduler resumes the Fiber. The adapter **re-attaches** the exact context, allowing the code to continue seamlessly.

This "Context Switching" is what makes the system handle massive concurrency while maintaining the isolation of a single-threaded request.

## 6. The Semantic Stack: Separating 'What' from 'How'

To keep these systems organized, ItPack uses a "Semantic Stack." Each layer answers a specific question about the work being done.

*   **Spindle**: *What is the intent?* (The high-level business goal).
*   **AExO**: *Why and in what order?* (Orchestration and workflow logic).
*   **RunIt**: *What happened?* (Execution identity, context, and semantic telemetry).
*   **AsyncIt**: *How does work progress?* (Scheduling, timers, and I/O multiplexing).

> **Pro-Tip: The Poll API (Io\Poll)**
> Modern PHP (8.6+) includes a native **Io\Poll** API. This is a **low-level primitive**—an I/O multiplexer that replaces legacy `stream_select()` and its 1,024 file-descriptor ceiling. It is *not* a scheduler or an execution model; AsyncIt consumes the Poll API to drive progression while RunIt ensures the data remains isolated.

## 7. Conclusion: Principles of Secure Asynchronous Design

Mastering asynchronous PHP requires a fundamental shift in how we handle state. To ensure your applications are both fast and secure, adhere to these three commandments:

- [x] **Never use `static` or `global`** for request-specific metadata like User IDs or Security Classifications.
- [x] **Always use immutable, copy-on-write context** propagation to move metadata safely between different parts of the application.
- [x] **Rely on the scheduler (AsyncIt)** to handle context isolation (Detach/Re-attach) during I/O wait states to prevent data leakage.