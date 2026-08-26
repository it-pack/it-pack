# AsyncIt Implementation Plan

> **Status:** Proposed / pre-build.
> **Purpose:** Translate the reasoning in `docs/BRAINSTORM.md` into a buildable, phased implementation plan.
> **Authoritative inputs:** `docs/BRAINSTORM.md`, `README.md`, `AGENTS.md`, `docs/EXECUTION.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002).
> **RunIt reference:** `src/RunIt/docs/conformance.md`, `.../architecture/context-propagation.md`, `.../architecture/lifecycle.md`, `.../concepts/time-and-sequencing.md`, ADRs 005 / 013 / 014 / 023 / 024.

This is a *plan and reasoning* document, not a specification. It proposes contract shapes and sequencing; concrete signatures are finalized in code and recorded in ADRs as we build.

---

## 0. Guiding principles (carry-over from the brainstorm)

1. **Behavior, not a shared runtime, is the only portable thing** in PHP async. We standardize *behavior* through conformance, not a shared event loop.
2. **Mechanical split is sacred:** `Polling ≠ Scheduling ≠ Execution Semantics`. Domain depends only on Contracts/Ports.
3. **Reuse RunIt's ports and verifiers** instead of forking them. AsyncIt *implements* RunIt's execution-semantics ports (`MonotonicClockInterface`, `ContextStorageInterface`, `ArtifactPublisherInterface`, `SequenceAllocatorInterface`, `ExecutionEngineInterface`) and reuses RunIt's `Conformance` toolkit.
4. **Conformance-first, reference-runtime-backed.** Define behavior → prove it with a pure-PHP reference runtime → force adapters to conform.
5. **Contracts only with a concrete consumer/adapter** (AGENTS.md). M3 defines the minimal set; later milestones extend, never gold-plate.

---

## 1. Package layout

Mirror RunIt's hexagonal `src/Conformance/` toolkit and honor the existing `deptrac.yaml` layers.

```text
src/
├── Common/                 # shared value objects, enums, exceptions (no deps)
├── Contract/               # interfaces (Task, Scheduler, CancellationScope, ...)
│   └── Port/               # AsyncIt-native ports (PollerPort, CancellationScopeInterface)
├── Domain/                # Task aggregate, TaskStatus, CancellationScope, Awaitable
├── Application/           # FiberTaskScheduler, RuntimeDiscovery, coordinator
├── SDK/                   # ergonomic facade (AsyncRuntime::discover())
├── Adapter/
│   ├── Common/            # SelectPoller, MemoryContextStorage, NullArtifactPublisher, Fiber adapter
│   └── External/          # Revolt, Swoole, Swow, NativePoller  (capability-selected)
└── Conformance/           # mirrors RunIt: Contract/PortFixtureInterface, Fixture/, Verifier/, Result/
tests/
├── Unit/  Application/  Conformance/  (RunItPortConformanceTest sibling)
└── Support/Fixture/Conformance/
examples/conformance/      # plain-PHP runner (no framework), like RunIt's
```

**Deptrac adjustment (foundational task):** the current `deptrac.yaml` "External Adapter" layer still lists `OpenTelemetry|CloudEvents|Psr|Swoole` (copied from RunIt). For AsyncIt this should become the runtime/poller adapters, e.g. `Adapter/(Revolt|Swoole|Swow|Native|Select)/.*`, so architecture rules actually guard AsyncIt's boundaries. Do this in Phase 0.

---

## 2. Milestone map (condensed from the roadmap)

| Phase | Milestone(s) | Outcome |
|---|---|---|
| 0 | M0–M2 tail | Tooling, deptrac fix, vocab alignment, `src/Conformance/` skeleton |
| 1 | M3 | Minimal contracts + ports + verifier scaffold (failing) |
| 2 | M4 | Pure-PHP reference runtime → verifier suite **passes** |
| 3 | M5 | RunIt integration → passes `ExecutionEngineVerifier` end-to-end |
| 4 | M6–M8 | Revolt, Swoole, Swow adapters (each passes full suite) |
| 5 | M9–M10 | `NativePoller` (`Io\Poll`) + ReactPHP/Amp interop |

---

## 3. Phase 0 — Foundations

**Goal:** make the package buildable and the architecture enforced before any domain code.

- **Tooling:** confirm `composer.json` scripts (`analyse`, `architecture`, `test`, `coding-style`, `mutation`). Add RunIt as a *dev/contract* dependency once its interfaces are consumable; otherwise vendor the port *interface* copies under `Contract/Port/RunIt/` is **not** recommended — prefer a real `it-pack/run-it` dependency so we test against the same interfaces we verify.
- **Deptrac:** fix the adapter layer names (see §1).
- **Conformance skeleton:** create `src/Conformance/` mirroring RunIt:
  - `Contract/PortFixtureInterface.php` — the *only* contract a verifier depends on.
  - `Result/ConformanceResult.php`, `Result/ConformanceViolation.php`, `Result/ViolationSeverity.php` (enum `error|warning`).
  - `Verifier/` directory with a no-op placeholder so the layout and result model compile.
- **Vocabulary:** add a `@see run-it#...` reference for the terms AsyncIt maps (Execution, Context, Occurrence, Measurement, Observation) per AGENTS.md "Vocabulary Alignment".

**Exit gate:** `composer architecture` is clean; `src/Conformance/` compiles; no domain code yet.

---

## 4. Phase 1 — Minimal contracts & ports (M3)

**Goal:** freeze the contract surface and stand up the verifier scaffold as *failing* tests.

### 4.1 Reused RunIt ports (implement, don't redefine)
- `MonotonicClockInterface`, `ContextStorageInterface`, `ArtifactPublisherInterface`, `SequenceAllocatorInterface`, `IdentifierGeneratorInterface` — consumed from `it-pack/run-it`.
- `ExecutionEngineInterface` — **the integration seam**: `AsyncRuntime` will implement this.

### 4.2 AsyncIt-native contracts (`src/Contract`, `src/Contract/Port`)

- **`TaskStatus`** (enum, closed matrix) — mirror RunIt's `ExecutionStatusInterface::canTransitionTo()`:
  - States: `Created, Running, Succeeded, Failed, Cancelled, TimedOut`.
  - `canTransitionTo(TaskStatus): bool` encodes the closed matrix; `Created→Running`, `Created→Cancelled`, `Running→{terminal}` only.
- **`Task`** (domain entity, `final`) — wraps a `Fiber`, carries `TaskStatus`, task id, owning execution id, and a `CancellationScope` reference. `Task::complete(status)` enforces the matrix (rejects illegal transitions → `InvalidLifecycleTransitionException`; conflicting terminal → `ConflictingCompletionException`). *Idempotency* (same-status repeat = no-op) lives in the coordinator above the aggregate, exactly like RunIt's `ExecutionLifecycleCoordinator`.
- **`Scheduler`** (interface) — `spawn(callable): Task`, `run(): void`, `await*(...)` primitives, `delay(Duration)`, timer/suspend orchestration.
- **`PollerPort`** (interface) — `registerReadable(resource, Task): StreamPollHandle`, `registerWritable(...)`, `wait(?Duration): void`, `unwatch(StreamPollHandle): void`. The `StreamPollHandle` is returned to / held by the scheduler; the poller is a dumb notifier (handle→task map is scheduler state, not poller state).
- **`CancellationScopeInterface`** — `isCancelled(): bool`, `cancel(): void`, `derive(): CancellationScope` (child), `detach(): void`. Cascade by parent→children unless `detach()`.
- **`CapabilityDescriptor`** (value object) — per-capability `Supported|Emulated|AdapterOnly|Unavailable`.
- **`RuntimeDiscovery`** (interface) — `discover(): CapabilityDescriptor` at the process boundary.

### 4.3 Exceptions (`src/Common` / `Contract`)
- Marker interface `AsyncItExceptionInterface` (and `LifecycleExceptionInterface` mirroring RunIt).
- `ConflictingCompletionException`, `InvalidLifecycleTransitionException`, `StreamPollHandleLeakException` (suggested, for Assertion C).

### 4.4 Verifier scaffold (failing)
- Implement `AsyncRuntimeVerifier` suite that runs:
  - **Reused RunIt verifiers** against the RunIt ports (these pass once M4 provides real impls; in M3 they are wired but the impls are stubs → red).
  - **Assertion A** (`ContextIsolationVerifier`) and **Assertion C** (`DeterministicCleanupVerifier`) as AsyncIt-native verifiers returning `ConformanceResult`.
- Negative-test siblings (`*VerifierNegativeTest`) for A and C, asserting expected violation ids.

**Exit gate:** all contracts compile; deptrac clean; verifier suite runs and is *red* (no impls yet). No behavior promised.

---

## 5. Phase 2 — Reference runtime (M4)

**Goal:** make the verifier suite **green** on a pure-PHP, deterministic, `SelectPoller`-backed runtime. This is the semantic contract frozen in code.

### 5.1 Domain
- `Task` aggregate (§4.2) fully implemented; Fiber suspension via `Fiber::suspend()` / `Fiber::resume()`.
- `CancellationScope` with parent/child cascade + `detach()`.

### 5.2 Application
- **`FiberTaskScheduler`** (`final`, implements `Scheduler`):
  - runnable-queue + pending-await registry (handle→task).
  - reactor loop: drain ready tasks → fire expired timers (monotonic, via `MonotonicClockInterface`) → `poller->wait(nearestDeadline)` → repeat until idle.
  - on terminal transition: drain the task's registered `StreamPollHandle`s and timers (Assertion C).
  - around each `Fiber::suspend()`: call `ContextStorageInterface::detach()` before, and `reattach(token)` after resume inside a `finally` (Assertion A).
  - on throw: record via `ArtifactPublisherInterface` (recursion-guarded), release handles/timers, rethrow the **exact** original `Throwable` (RunIt exception-preservation precedent).

### 5.3 Common adapters (reference)
- **`SelectPoller`** implementing `PollerPort` via `stream_select()` within the legacy descriptor ceiling; explicitly **not** pretending to be `Io\Poll`.
- **`MemoryContextStorage`** implementing RunIt's `ContextStorageInterface` (LIFO `ContextToken` stack; per-Fiber slot via `WeakMap<Fiber, Context>` for the suspend/resume timing).
- **`NullArtifactPublisher`** implementing RunIt's `ArtifactPublisherInterface` (no-op, fail-open) for M4.
- **`DeterministicMonotonicClock`** (fixture) so durations need no sleeping — mirror RunIt's `Tests\Support\Fixture\DeterministicMonotonicClock`.

### 5.4 Conformance
- Wire the real impls into `AsyncRuntimeVerifier`. Target: **green** for Assertion A, Assertion C, and the reused RunIt verifiers that the reference impls satisfy (`MonotonicClockVerifier`, `ContextStorageVerifier`, `ArtifactPublisherVerifier`). `ExecutionEngineVerifier` stays red until M5 (RunIt impl swap).

**Exit gate:** `composer test` + `composer architecture` + `composer analyse` green; verifier suite green for A/C + reachable RunIt verifiers; `examples/conformance/` plain-PHP runner exits 0.

---

## 6. Phase 3 — RunIt integration (M5)

**Goal:** the formal AsyncIt↔RunIt gate — `AsyncRuntime` passes `ExecutionEngineVerifier`.

- Swap `MemoryContextStorage`/`NullArtifactPublisher` for RunIt's first-party adapters (the real `ContextStorageInterface`/`ArtifactPublisherInterface` implementations, with `FailSafeArtifactPublisher`, `onPublicationFailure` health callback, recursion guard — ADR-014).
- Replace `DeterministicMonotonicClock` with RunIt's `SystemMonotonicClock`.
- Validate detach/reattach under the real fiber/coroutine context storage (LIFO token + per-fiber slot).
- Run `ExecutionEngineVerifier` end-to-end: lifecycle, exception preservation, ordering.
- Add the `AsyncRuntime` SDK class implementing `ExecutionEngineInterface`; `AsyncRuntime::discover()` returns it with the negotiated `CapabilityDescriptor`.

**Exit gate:** `ExecutionEngineVerifier` green; all RunIt port verifiers green through real RunIt adapters.

---

## 7. Phase 4 — Core runtime adapters (M6–M8)

Each adapter is *only* a translation of suspension/resumption and polling into the runtime's model; scheduling semantics are unchanged.

- **Revolt** — Fiber-based; `PollerPort` over Revolt's event loop; `CancellationScope` maps to Revolt's task groups where possible; channels emulated (native `Unavailable`).
- **Swoole** — coroutine model; `RuntimeContextDetectorInterface` per-coroutine context storage, cleared/restored in `finally` (ADR-013); `PollerPort` over Swoole's watchers; native channels `Supported` → back `TaskChannel` with Swoole channels.
- **Swow** — libuv-based; analogous to Swoole; native channels `Supported`.

Adapter assembly is capability-selected by `RuntimeDiscovery` from the `CapabilityDescriptor` — adapters never branch on `extension_loaded(...)`.

**Exit gate (per adapter):** the *same* `AsyncRuntimeVerifier` suite passes, including Assertion A (per-fiber/per-coroutine isolation) and Assertion C (no leaked watchers). A single leaked descriptor or one context-bleed is a failure.

---

## 8. Phase 5 — Native poller & interop (M9–M10)

- **`NativePoller`** implementing `PollerPort` over PHP 8.6 `Io\Poll` (edge-triggered, beyond `stream_select` ceiling). Drops into the *same* `PollerPort` slot; scheduler and semantics untouched (proves the mechanical split).
- **ReactPHP/Amp interop layer** — `PollerPort` bridge so userland loops can host AsyncIt tasks; documented as interop, not "unification".

**Exit gate:** `NativePoller` passes the poller contract + Assertion C; interop layer passes A/C under a ReactPHP event loop.

---

## 9. Conformance test plan (mirrors RunIt discipline)

For **every** shipped verifier (`ContextStorageVerifier`, `MonotonicClockVerifier`, `ArtifactPublisherVerifier`, `ExecutionEngineVerifier`, Assertion A, Assertion C):

1. A happy-path case in `tests/Conformance/AsyncItRuntimeConformanceTest.php` asserting **zero** violations.
2. A sibling `*VerifierNegativeTest` that breaks one contract at a time (non-LIFO context storage, decreasing clock, double-publish without guard, `created→succeeded` jump, leaked `StreamPollHandle`, context bleed across two interleaved suspends) and asserts the expected violation id + `error` severity.
3. ≥95% coverage of verifier internals; impure fixtures live under `tests/Support/Fixture/Conformance/`.
4. A plain-PHP runner `examples/conformance/third-party-adapter.php` so third-party runtime authors verify without a framework — same pattern RunIt ships.

---

## 10. Build-order dependency graph

```text
Phase 0 (tooling/deptrac/skeleton)
   │
   ▼
Phase 1 contracts & ports  ──►  verifier scaffold (RED)
   │
   ▼
Phase 2 reference runtime  ──►  verifier suite GREEN (A/C + reachable RunIt verifiers)
   │
   ▼
Phase 3 RunIt adapters     ──►  ExecutionEngineVerifier GREEN  (the seam)
   │
   ├──► Phase 4 Revolt / Swoole / Swow  (each: full suite GREEN)
   │
   └──► Phase 5 NativePoller / interop  (poller contract + A/C GREEN)
```

No phase may start before its parent's exit gate is green, because each later phase is *validated by the same verifier suite* — keeping semantics honest.

---

## 11. Risks & validation gates

| Risk | Gate that catches it |
|---|---|
| Scheduler coupled to a runtime | Deptrac (Domain→Contracts/Ports only); Phase 0 gate |
| Context bleed in persistent workers | Assertion A + `ContextStorageVerifier`; Phase 2/4 gate |
| `StreamPollHandle`/timer leak | Assertion C; every adapter gate |
| Wall-clock skew | `MonotonicClockVerifier` (reused); Phase 2 gate |
| Fork drift from RunIt | Reused RunIt port verifiers + `ExecutionEngineVerifier`; Phase 3 gate |
| Over-abstraction | M3 minimal contract set; review against "concrete consumer" rule |
| `SelectPoller` masquerading as `Io\Poll` | Distinct named impl + documented weaker traits; negative test |

---

## 12. Definition of done (package-level)

- `composer test`, `composer analyse`, `composer architecture`, `composer coding-style` green on PHP 8.4+.
- `AsyncRuntimeVerifier` green across reference runtime **and** every runtime adapter, including A/C.
- `AsyncRuntime` passes RunIt's `ExecutionEngineVerifier` (the formal integration proof).
- Every public contract has a concrete consumer or adapter (no speculative extension points).
- `examples/conformance/third-party-adapter.php` runs unmodified for each shipped adapter.
- Docs (`README.md`, `AGENTS.md`, `BRAINSTORM.md`, this plan) reflect the built reality; vocabulary references `run-it#*` where applicable.

---

## 13. One-line summary

Build the contract surface first, prove it with a pure-PHP reference runtime against a RunIt-shaped `Conformance` toolkit, then let every runtime adapter earn its place by passing the *same* `AsyncRuntimeVerifier` suite — with `ExecutionEngineVerifier` as the hard proof that AsyncIt and RunIt are one stack.
