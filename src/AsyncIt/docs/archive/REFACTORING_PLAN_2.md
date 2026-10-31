# AsyncIt Refactoring Plan

> **Status:** Proposed, review-ready.
> **Source:** `docs/REVIEW.md` (2026-10-29 findings)
> **Goal:** Step-by-step plan to remediate all findings from the in-depth review, organized into phases with clear tasks, verification commands, and exit criteria. Each phase has a single deliverable and a green quality gate.
> **Aligned with:** `docs/ROADMAP.md` quality gates (M0–M10), `docs/TODOS.md` (R0–R6 baseline)

---

## Review-findings index

Each task references its source finding from `docs/REVIEW.md`:

| Finding ID | Summary | Review § |
|---|---|---|
| **F1** | Poller `await()` + handle tracking duplicated 7×, with magic numbers | §2.1.1, §8.5 |
| **F2** | Context storage duplicated 3× (Swoole/Swow/Revolt) | §2.1.2 |
| **F3** | Context tokens duplicated 3× (Swoole/Swow/Revolt) + `MemoryContextToken` inconsistency | §2.1.3, §8.13 |
| **F4** | `RuntimeAdapterKind` enum uses magic strings (no vocabulary interface) | §8.5, §8.6 |
| **F5** | `ViolationSeverity` enum uses magic strings (no vocabulary interface) | §8.5, §8.6 |
| **F6** | `SchedulerFailureArtifact::normalize()` magic string `'asyncit.scheduler.failure'` | §8.5 |
| **F7** | Declaration order: `AsyncRuntime::discover()` (static) before non-static methods | §8.10 |
| **F8** | Declaration order: `AsyncRuntimeFactory::create()` (static) before non-static methods | §8.10 |
| **F9** | Multi-class test files: `NativePollerTest` + `FakeNativePoll`, `ReactPhpPollerBridgeTest` + `FakeReactLoop` | §8.11 |
| **F10** | Test coverage 52.5% (lines) / 32.8% (classes) — target ≥95% | §9.1 |
| **F11** | `FiberTaskScheduler::run()` is O(n²) — iterates all records every tick | §3.1 |
| **F12** | `EmulatedChannel::receive()` uses O(n) `array_shift()` | §3.2 |
| **F13** | `MemoryContextStorage::detach()` uses O(n) `array_values()` reindex | §3.3 |
| **F14** | `RuntimeAdapterAssembler` efferent coupling = 23 | §2.2, §11.2 |
| **F15** | `EngineCapability` hardcoded `false` for coroutines/concurrent | §10.3 |
| **F16** | README shows `spawn()`/`awaitReadable()` not yet implemented | §5.2 |

---

## Phase R7 — Extract Shared Poller Infrastructure (addresses F1, F6)

**Goal:** Eliminate the 7× duplication of the poller `await()` method and handle-tracking bookkeeping; remove magic numbers.

### Tasks

- [x] **R7.1** — Added `NANOSECONDS_PER_SECOND = 1_000_000_000` and `NANOSECONDS_PER_MICROSECOND = 1_000` constants to the existing `Contract\Port\PollerVocabulary` interface. Added `@see` to the AsyncIt Polling ontology.

- [x] **R7.2** — Created `src/Domain/Poller/StreamSelectPollerTrait.php` — a trait providing shared `probeReadiness()` using the vocabulary constants, replacing the duplicated `await()` body across 7 pollers. (Used a trait instead of an abstract class per AGENTS.md "no abstract classes" rule.)

- [x] **R7.3** — Refactored `SelectPoller` to use `StreamSelectPollerTrait` via `use StreamSelectPollerTrait;` in `await()`.

- [x] **R7.4** — Refactored `NativePoller` to use `PollerVocabulary::NANOSECONDS_PER_MILLISECOND` for timeout conversion; retains native `Io\Poll` registration logic.

- [x] **R7.5** — Refactored `RevoltPoller`, `SwoolePoller`, `SwowPoller`, `AmpPollerBridge`, `ReactPhpPollerBridge` to use `StreamSelectPollerTrait`. Each retains only its runtime-specific `registerWithReactor()` / `cancelOnLoop()` logic.

- [x] **R7.6** — Created `src/Application/Artifact/ArtifactVocabulary.php` with `KIND_SCHEDULER_FAILURE = 'asyncit.scheduler.failure'` and `SchedulerFailureArtifact::normalize()` references it.

### Verification

```bash
composer cs:check     # 0 violations
composer analyse      # 0 errors (bleeding edge)
composer architecture # 0 violations
composer test:unit    # all poller tests pass
vendor/bin/phpmetrics src --report-violations=var/violations.xml  # CCN ≤ 6 for all pollers
```

**Exit criteria:** All 7 poller classes have CCN ≤ 6 (down from 10–12); no `1_000_000_000` or bare `1000` literals in any poller; `composer quality` is green.

---

## Phase R8 — Extract Shared Context Storage (addresses F2, F3)

**Goal:** Eliminate the 3× duplication of coroutine/fiber context storage and the 3× duplication of context-token classes.

### Tasks

- [x] **R8.1** — Created `src/Domain/ContextToken.php` — a single `final readonly class` value object implementing `ContextTokenInterface`. Replaced `SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`, and `MemoryContextToken`.
- [x] **R8.2** — Created `src/Domain/CoroutineContextStorageTrait.php` — provides shared `current()`, `attach()`, `detach()`, `slot()` logic as a trait (avoids abstract class per AGENTS.md); adapters inject ID resolvers.
- [x] **R8.3** — Refactored `SwooleCoroutineContextStorage` to use `CoroutineContextStorageTrait`; `SwooleContextToken` eliminated.
- [x] **R8.4** — Refactored `SwowCoroutineContextStorage` to use `CoroutineContextStorageTrait`; `SwowContextToken` eliminated.
- [x] **R8.5** — Refactored `RevoltFiberContextStorage` to use `CoroutineContextStorageTrait`; `RevoltContextToken` eliminated.
- [x] **R8.6** — Refactored `MemoryContextStorage` to use shared `ContextToken`; `MemoryContextToken` eliminated; declaration ordering fixed.

### Verification

```bash
composer cs:check
composer analyse
composer architecture
composer test:unit
```

**Exit criteria:** Three adapter context-storage classes reduced to < 30 LOC each (from ~130); three context-token classes eliminated; no behavioral change in conformance tests.

---

## Phase R9 — Vocabulary Completion & Magic-String Elimination (addresses F4, F5, F6)

**Goal:** Bind all enum cases to vocabulary interface constants; eliminate remaining magic strings.

### Tasks

- [x] **R9.1** — Created `Contract\Port\RuntimeAdapterVocabulary` interface with `ADAPTER_REFERENCE`, `ADAPTER_REVOLT`, `ADAPTER_SWOOLE`, `ADAPTER_SWOW` constants. `RuntimeAdapterKind` enum cases bound to these constants.
- [x] **R9.2** — Created `Conformance\Result\ViolationSeverityVocabulary` interface. `ViolationSeverity` enum cases bound to its `SEVERITY_ERROR`/`SEVERITY_WARNING` constants.
- [x] **R9.3** — Audited remaining string-literal magic in `src/`. `SchedulerFailureArtifact::normalize()` uses `ArtifactVocabulary::KIND_SCHEDULER_FAILURE` and shared artifact keys. Test-only magic strings in `FakeRuntimeFixture` are acceptable (test-only).

- [ ] **R9.4** — Update `@see` PHPDoc on `RuntimeAdapterKind` and `ViolationSeverity` to reference the AsyncIt ontology/taxonomy IRIs for the new vocabulary interfaces.

### Verification

```bash
grep -rn "case .* = '" src/Contract/Port src/Conformance/Result  # no bare string literals in enum cases
composer analyse
composer cs:check
```

**Exit criteria:** Every `enum` case in `src/` references a vocabulary interface constant; `composer quality` green.

---

## Phase R10 — Test Isolation & Coverage (addresses F10)

**Goal:** Raise test coverage from 52.5% to ≥95% (lines) and eliminate test-file class-multiplicity violations (F9).

### Tasks

- [x] **R10.1** — Extracted `FakeNativePoll` from `NativePollerTest` into `tests/Unit/Application/Poller/FakeNativePoll.php`.
- [x] **R10.2** — Extracted `FakeReactLoop` from `ReactPhpPollerBridgeTest` into `tests/Unit/Adapter/Interop/ReactPhp/FakeReactLoop.php`.
- [x] **R10.3** — Added `TaskTest` covering all lifecycle transitions (CREATED→RUNNING→SUCCEEDED/FAILED/CANCELLED/TIMED_OUT; ConflictingCompletionException; InvalidLifecycleTransitionException; no-op on repeat).
- [x] **R10.4** — Added `TaskIdTest` covering empty-string validation.
- [x] **R10.5** — Added `StreamPollHandleTest` covering empty-string validation.
- [x] **R10.6** — Added `CancellationScopeTest` covering isCancelled, throwIfCancelled, onCancel, cascade.
- [x] **R10.7** — Added `EmulatedChannelTest` covering close/isClosed/ChannelClosedException.
- [x] **R10.8** — Added `CapabilityDescriptorTest` covering statusFor().
- [x] **R10.9** — Added `MemoryContextStorageTest` covering LIFO violation and type-mismatch.
- [x] **R10.10** — Added `TaskRecordTest` covering addHandle, clearHandles, handles, isDone, markDone.
- [x] **R10.11** — Added `SchedulerFailureArtifactTest` covering normalize().
- [x] **R10.12** — Added `PollerVocabularyTest` covering constant values.
- [x] **R10.13** — `@covers` annotations added to new test classes; no cross-test coverage leakage.

### Verification

```bash
vendor/bin/codecept run Unit --coverage-text
```

**Exit criteria:** Line coverage ≥95%; method coverage ≥95%; class coverage ≥90% (allowing for extension-gated adapters).

---

## Phase R11 — Declaration Ordering & Class Consistency (addresses F3, F7, F8)

**Goal:** Fix declaration-order violations and `readonly` inconsistency.

### Tasks

- [x] **R11.1** — Reordered `AsyncRuntime` (`src/SDK/AsyncRuntime.php`): `discover()` (static) now follows all non-static methods (`channel()`, `telemetry()`, `run()`, `capability()`).
- [x] **R11.2** — Reordered `AsyncRuntimeFactory` (`src/SDK/Factory/AsyncRuntimeFactory.php`): `create()` (static) now follows non-static methods (`negotiate()`, `buildTelemetry()`).
- [x] **R11.3** — Audited all `src/` files for declaration ordering (traits → constants → properties → constructor → factory methods → non-static → static). All comply.

### Verification

```bash
composer cs:check     # `ordered_class_elements` rule
composer analyse
```

**Exit criteria:** All classes follow the AGENTS.md declaration ordering; `composer quality` green.

---

## Phase R12 — Performance Optimizations (addresses F11, F12, F13)

**Goal:** Eliminate O(n) and O(n²) hot-path operations in the scheduler and channels.

### Tasks

- [x] **R12.1** — Optimized `FiberTaskScheduler::run()`: replaced O(n) `foreach` over all records with a FIFO ready queue (`$this->ready`). Only ready records are stepped; done records are removed immediately; non-done records are re-queued after polling. (Full I/O-selective re-queue requires Phase 6 handle-readiness integration.)
- [x] **R12.2** — Optimized `EmulatedChannel::receive()`: replaced `array_shift()` (O(n)) with a head-index counter + periodic compaction for O(1) dequeue.
- [x] **R12.3** — Optimized `MemoryContextStorage::detach()`: removed `array_values()` reindex; uses `array_pop()` with LIFO stack semantics.
- [x] **R12.4** — Added `phpbench.json` benchmarks for `FiberTaskScheduler::run()` throughput and `EmulatedChannel::receive()` latency.

### Verification

```bash
vendor/bin/phpbench run
```

**Exit criteria:** `run()` complexity reduced from O(n²) to O(ready + suspended) per tick; `receive()` from O(n) to O(1); benchmark results documented in `docs/performance-baseline.md`.

---

## Phase R13 — Assembler Decomposition (addresses F14)

**Goal:** Reduce `RuntimeAdapterAssembler`'s external coupling from 23 to ≤8 per assembler.

### Tasks

- [x] **R13.1** — Created `RuntimeAssemblyFactoryInterface` with a single `create(CapabilityDescriptor): RuntimeAssembly` method.
- [x] **R13.2** — Created per-runtime assemblers: `SwooleAssemblyFactory` (6 externals), `SwowAssemblyFactory` (6 externals), `RevoltAssemblyFactory` (9 externals — Revolt-specific deps), `ReferenceAssemblyFactory` (3 externals).
- [x] **R13.3** — Refactored `RuntimeAdapterAssembler` to delegate to per-runtime assemblers via a `match` expression; reduced from 23 externals to 3 imports.
- [x] **R13.4** — `AsyncRuntimeFactory` uses the refactored `RuntimeAdapterAssembler` (no interface change).

### Verification

```bash
composer architecture
composer analyse
composer test:unit
vendor/bin/phpmetrics src --report-json=var/phpmetrics.json  # externals ≤ 8 per assembler
```

**Exit criteria:** `RuntimeAdapterAssembler` externals ≤ 8; each per-runtime assembler ≤ 6 externals; `composer quality` green.

---

## Phase R14 — Capability-Driven Engine Construction (addresses F15, F16)

**Goal:** Derive `EngineCapability` fields from the `CapabilityDescriptor` instead of hardcoding `false`.

### Tasks

- [x] **R14.1** — In `AsyncRuntimeFactory::create()`, replaced hardcoded `false` values for `supportsCoroutines` and `concurrent` with derivations from the `CapabilityDescriptor`:
   - `supportsCoroutines` ← `CapabilityKind::BlockingHook` status `AdapterOnly` or `Supported`
   - `concurrent` ← `CapabilityKind::FiberExecution` status `Supported`

- [x] **R14.2** — Updated README to document the current API (`discover()` → `run()`) and mark `spawn()`/`awaitReadable()` as planned for Phase 6 (M11–M12). See `docs/ROADMAP.md` Phase 6.

### Verification

```bash
composer test:unit   # existing engine verifier tests pass
```

**Exit criteria:** `EngineCapability` fields are fully derived from the negotiated `CapabilityDescriptor`; no hardcoded `false` literals remain.

---

## Phase R15 — README/API Alignment (addresses F16)

**Goal:** Align the README example with the actual public API.

### Tasks

- [x] **R15.1** — Updated `README.md` usage example to use the actual `AsyncRuntime::discover()` → `run()` API with `ReferenceExecutionScope`. The `spawn()`/`awaitReadable()` API is documented as planned for Phase 6.

- [x] **R15.2** — Added a "Public API Reference" section to the README listing the actual stable contracts: `AsyncRuntime`, `EngineSchedulerInterface`, `SchedulerInterface`, `TaskInterface`, `CancellationScopeInterface`, `PollerPortInterface`, `TaskChannelInterface`, `CapabilityDescriptor`, `RuntimeDiscoveryInterface`, `TelemetryBridgeInterface`.

### Verification

Manual review of README against `src/SDK/AsyncRuntime.php` and `src/Contract/`.

**Exit criteria:** README example code is runnable as-is against the published API; no mismatched method names.

---

## Cross-phase quality gates

Every phase must pass these gates before "Exit criteria" are considered met:

```bash
composer validate --strict
composer cs:check
composer analyse
composer architecture
composer lint:length
composer lint:types
composer test
composer security:audit
```

---

## Execution order & dependencies

```text
R7 (poller extraction) ──→ R8 (context storage extraction)
     │                          │
     ▼                          ▼
R9 (vocabulary)            R11 (declaration order)
     │                          │
     ▼                          ▼
R10 (test isolation &       R12/R13/R14 (performance &
     coverage)                   capability alignment)
                              │
                              ▼
                        R15 (README alignment)
```

- **R7 must precede R8** — both modify the adapter layer, but R7's shared poller base and R8's shared storage base reduce adapter file sizes, making R9's vocabulary binding cleaner.
- **R10 depends on R7 + R8** — extracting shared bases reduces the number of classes that need dedicated tests and makes the remaining adapter tests faster (fewer skip-when-extension-absent cases).
- **R12 depends on R7** — the shared poller refactor provides a natural insertion point for the `await()` optimization.
- **R15 is last** — only after the public API stops changing.
