# AsyncIt

> **Portable, Capability-Oriented Asynchronous Execution and Scheduling for the ItPack Semantic Stack.**

AsyncIt is a core component of the **ItPack ecosystem** [229]. Rather than acting as a vendor-specific wrapper for existing asynchronous PHP engines, AsyncIt establishes an **implementation-neutral, capability-driven execution platform** [209]. It defines the mechanical progression of asynchronous work strictly decoupled from underlying operating system multiplexers, polling engines, or telemetry frameworks [213, 230].

---

## 1. The Five-Tier Semantic Stack

The ItPack architecture enforces the principle of **"Consumption Without Responsibility"** [175, 180, 210]. Higher-level packages consume complex asynchronous capabilities without inheriting the responsibility of managing low-level scheduling mechanics, Fiber suspension, or system polling [175, 210, 213]:

| Tier | Layer | Package | The Core Question Answered | Focus |
|---|---|---|---|---|
| **Tier 1** | **Intent** | **Spindle** | *What* is the business intent? [210, 230] | Pure business intent; oblivious to execution transport or timing [174, 210]. |
| **Tier 2** | **Orchestration** | **AExO** | *Why* is it happening, and in what order? [179, 210] | Macro-coordination of agents, workflows, and retry/compensation logic [174, 210, 238]. |
| **Tier 3** | **Execution** | **RunIt** | *What* is an execution? (The Backbone) [195, 210, 236] | Standardized identity, immutable context, occurrences, and measurements [174, 210, 236]. |
| **Tier 4** | **Scheduling** | **AsyncIt** | *How* does the work progress? [195, 210, 230] | Schedulers, Fiber queue control, cooperative task execution, and timers [156, 186, 210]. |
| **Tier 5** | **Platform** | **Engine/OS** | *How* is I/O multiplexed? [210, 230] | Hardware triggers (e.g., native PHP 8.6 `Io\Poll`, kernel `epoll`/`kqueue`) [210, 223, 232]. |

---

## 2. Core Architectural Pillars

### A. Capability-First Architecture
AsyncIt avoids the "vendor-wrapping" trap by querying the runtime at the process boundary [127, 211, 231]. The environment is analyzed once, and a queryable **Capability Descriptor** is generated [205, 234]:
* Instead of brittle checks like `extension_loaded('swoole')`, AsyncIt asks functional questions: *"Can you run Fibers natively? Do you support edge-triggered polling? Do you have native channel synchronization?"* [127, 211, 212].
* It respects the asymmetry of the PHP async ecosystem—accepting that extension-based runtimes (Swoole, Swow) and pure-PHP loops (ReactPHP, Revolt) rely on incompatible process models [203, 224]. AsyncIt focuses on **semantic portability** instead of forcing incompatible loops to host one another [127, 204].

### B. Fiber-Safe Context Propagation (The Sequence of Suspension)
Persistent-worker environments (Swoole, Swow, RoadRunner) carry severe risks of **request leakage** [115, 246, 276]. AsyncIt enforces a strict architectural invariant when a Fiber yields control [121, 188]:
1. **Suspension**: A Fiber hits an I/O wait boundary (e.g., awaiting stream readiness) and yields [121, 188].
2. **Detachment**: The RunIt Context Adapter immediately **detaches the active context** from the ambient Fiber scope [121, 188]. The context is locked away while the task is idle [121].
3. **Wait**: The process safely blocks on low-level polling (e.g., `Io\Poll\Context::wait()`) with no context bound to the scope, preventing interleaved tasks from bleeding into one another [121, 188].
4. **Resumption & Re-attachment**: Once signaled, AsyncIt resumes the Fiber, and the adapter **safely re-attaches the matching execution context** to the resumed Fiber scope [121, 188].

### C. Observed Cooperative Cancellation
AsyncIt rejects preemptive, violent task interruption (which can lead to file corruption and resource exhaustion) [159, 189]. Cancellation is modeled as an **observed state change** [159, 189]:
* Schedulers transition tasks through a closed-state lifecycle: `created -> running -> terminal` [158, 234].
* Running tasks cooperatively check their cancellation status [160, 189]. Upon observing a cancel signal, they perform a structured unwind and **explicitly release OS-level file watchers** (e.g., `StreamPollHandle` objects) back to the polling context to prevent descriptor leaks [136, 160].

---

## 3. The Concurrency Split: Polling vs. Scheduling

To future-proof the platform, AsyncIt enforces a strict separation of concerns [128, 153]:
$$\text{Polling (I/O Multiplexing)} \ \neq \ \text{Scheduling (Task Control)} \ \neq \ \text{Execution Semantics (Context \& Telemetry)}$$

* **Polling (Engine Layer)**: Managed natively in PHP 8.6 by the **Polling API (`Io\Poll`)** [202, 223]. It unifies low-level OS notifications (epoll, kqueue, WSAPoll) but does *not* manage fibers, task queues, or timers [202, 213, 232].
* **Scheduling (AsyncIt Layer)**: Decides *how* work progresses. Schedulers coordinate task queues, cooperative cancellation cascades, and Fiber suspension/resumption [156, 186, 213].
* **Execution Semantics (RunIt Layer)**: Defines *what* the execution is. It maps scheduler events directly to RunIt telemetry: task spawns are recorded as *Occurrences*, task durations (using monotonic clocks) as *Measurements*, and anomalies as *Observations* [187, 215].

---

## 4. Conceptual Usage Example

```php
declare(strict_types=1);

use ItPack\AsyncIt\AsyncRuntime;
use ItPack\RunIt\SDK\CurrentExecution;

// Initialize the portable AsyncIt runtime
$runtime = AsyncRuntime::discover(); // Negotiates capabilities automatically

$runtime->spawn(static function () use ($runtime) {
    // Spawns a fiber, attaching RunIt's ExecutionContext to the scope
    $stream = stream_socket_client('tcp://127.0.0.1:8080');
    stream_set_blocking($stream, false);

    try {
        // Fiber suspends, detaching context, and sleeps in the OS poller (Io\Poll)
        $data = $runtime->awaitReadable($stream);
        
        echo "Received: " . $data;
    } finally {
        fclose($stream); // Clean up resource handles on unwind
    }
});

// Drive the event loop until all tasks complete
$runtime->run();
```

---

## 5. Development and Conformance Roadmap

To ensure total behavioral compliance across vastly different runtime adapters, AsyncIt relies on a **conformance-led QA pipeline** [216, 234]. Adapters are continuously verified against the **`AsyncRuntimeVerifier`** test harness [133, 197]:

```
Contracts ──> Conformance (Verifiers) ──> Adapters
```

1. **M0 — Landscape Freeze**: Finalize async landscape truths and `Io\Poll` specs. **No code** [194, 234].
2. **M1 — Semantic Boundary**: Formalize boundaries (RunIt $\leftrightarrow$ AsyncIt $\leftrightarrow$ AExO) via Architecture Decision Records (ADRs) [194, 234].
3. **M2 — Capability Model**: Taxonomize negotiable capability descriptors [194, 234].
4. **M3 — Minimal Contracts**: Establish the smallest contract set with negative tests and conformance verifiers [194, 234].
5. **M4 — Reference Implementation**: Build a pure-PHP deterministic test runtime to establish semantic baselines [194, 234].
6. **M5 — RunIt Integration**: Validate context isolation and detachment during suspension [194, 234].
7. **M6 to M8 — Core Adapters**: Develop Revolt (M6), Swoole (M7), and Swow (M8) drivers [207, 234].
8. **M9 to M10 — Interoperability & Native**: Implement userland interop layers and slide the native PHP 8.6 `Io\Poll` API into its dedicated adapter slot [207, 234].

---

## License

AsyncIt is distributed under the **MIT License**.
All core conceptual definitions align with the canonical ItPack OWL ontologies.
