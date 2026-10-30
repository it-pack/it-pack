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

- [ ] **R7.1** — Create `ItPack\AsyncIt\Domain\Channel\PollerVocabulary` extension: add `NANOSECONDS_PER_SECOND = 1_000_000_000` and `NANOSECONDS_PER_MICROSECOND = 1000` constants to the existing `Contract\Port\PollerVocabulary` interface. Add a `@see` to the AsyncIt Polling ontology.

- [ ] **R7.2** — Create `src/Application/Poller/AbstractStreamSelectPoller.php` — a `final class` (not abstract — per AGENTS.md no abstract classes) implementing `PollerPortInterface` that provides:
  - Shared `$handles` array, `$counter`, `$idPrefix`
  - `register()` with handle tracking and `StreamPollHandle` creation using the injected prefix
  - `await()` using `PollerVocabulary::NANOSECONDS_PER_SECOND` and `PollerVocabulary::NANOSECONDS_PER_MICROSECOND` constants
  - `activeHandleCount()` returning `count($this->handles)`
  - `release()` using handle tracking via `StreamPollHandle`

- [ ] **R7.3** — Refactor `SelectPoller` to extend the shared base (or delegate). If using composition, inject the shared poller helper.

- [ ] **R7.4** — Refactor `NativePoller` to use shared `await()` logic + vocabulary constants.

- [ ] **R7.5** — Refactor `RevoltPoller`, `SwoolePoller`, `SwowPoller`, `AmpPollerBridge`, `ReactPhpPollerBridge` to use shared `await()` logic + vocabulary constants. Each retains only its runtime-specific `registerWithReactor()` / `cancelOnLoop()` logic.

- [ ] **R7.6** — Replace magic string `'asyncit.scheduler.failure'` in `SchedulerFailureArtifact::normalize()` with a `SchedulerFailureArtifactVocabulary::KIND_SCHEDULER_FAILURE` constant in a new `Application\Artifact` vocabulary interface.

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

- [ ] **R8.1** — Create `ItPack\AsyncIt\Domain\ContextToken` — a single `final readonly class` value object implementing `ContextTokenInterface` and `ValueObjectInterface`, replacing `SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`, and `MemoryContextToken`. Update all `use` statements in `SwooleCoroutineContextStorage`, `SwowCoroutineContextStorage`, `RevoltFiberContextStorage`, and `MemoryContextStorage`.

- [ ] **R8.2** — Create `src/Application/Context/CoroutineContextStorage.php` — a `final class` implementing `ContextStorageInterface` providing the shared `current()`, `attach()`, `detach()`, `slot()`, `fallback()`, `blank()` logic. Accept a `FiberScopeSlotFactoryInterface` and a closure/callable for coroutine/fiber ID resolution (injected, not hard-coded to a runtime). This makes the storage runtime-agnostic.

- [ ] **R8.3** — Refactor `SwooleCoroutineContextStorage` to either use the shared `CoroutineContextStorage` (injecting `\Swoole\Coroutine::getCid()` as the ID resolver) or delegate entirely. Remove `SwooleContextToken`.

- [ ] **R8.4** — Refactor `SwowCoroutineContextStorage` similarly. Remove `SwowContextToken`.

- [ ] **R8.5** — Refactor `RevoltFiberContextStorage` similarly. Remove `RevoltContextToken`.

- [ ] **R8.6** — Refactor `MemoryContextStorage` — remove `MemoryContextToken`, use the shared `ContextToken`. Fix declaration ordering (properties → constructor → methods).

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

- [ ] **R9.1** — Create `Contract\Port\RuntimeAdapterVocabulary` interface with `ADAPTER_REFERENCE`, `ADAPTER_REVOLT`, `ADAPTER_SWOOLE`, `ADAPTER_SWOW` constants. Bind `RuntimeAdapterKind` enum cases to these constants.

- [ ] **R9.2** — Create `Conformance\Result\ViolationSeverityVocabulary` interface (in `Conformance/Result/` — the appropriate namespace for conformance-level vocabulary). Bind `ViolationSeverity` enum cases to its constants.

- [ ] **R9.3** — Audit all remaining string-literal magic in `src/` and bind to vocabulary constants:
  - `SchedulerFailureArtifact::normalize()` — `'kind'` key and `'message'` key → use a shared artifact vocabulary interface
  - `AsyncRuntimeFixtureInterface::portName()` return values used as magic strings in tests → verify these are test-only, not production

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

- [ ] **R10.1** — Extract `FakeNativePoll` from `tests/Unit/Application/Poller/NativePollerTest.php` into its own file `tests/Unit/Application/Poller/FakeNativePoll.php`.

- [ ] **R10.2** — Extract `FakeReactLoop` from `tests/Unit/Adapter/Interop/ReactPhp/ReactPhpPollerBridgeTest.php` into its own file `tests/Unit/Adapter/Interop/ReactPhp/FakeReactLoop.php`.

- [ ] **R10.3** — Add `TaskTest` covering all lifecycle transitions:
  - `CREATED → RUNNING` (valid)
  - `RUNNING → SUCCEEDED` (valid terminal)
  - `RUNNING → FAILED`, `→ CANCELLED`, `→ TIMED_OUT` (valid terminal)
  - `SUCCEEDED → FAILED` raises `ConflictingCompletionException`
  - `CREATED → SUCCEEDED` raises `InvalidLifecycleTransitionException`
  - Repeat of current status is a no-op

- [ ] **R10.4** — Add `TaskIdTest` covering empty-string validation (the constructor `InvalidArgumentException`).

- [ ] **R10.5** — Add `StreamPollHandleTest` covering empty-string validation.

- [ ] **R10.6** — Add `CancellationScopeTest` covering: `isCancelled()`, `throwIfCancelled()` after cancel, `onCancel()` callback execution (immediate + deferred), cascade.

- [ ] **R10.7** — Add `EmulatedChannelTest` covering `close()` + `isClosed()` + `ChannelClosedException` on send-after-close.

- [ ] **R10.8** — Add `CapabilityDescriptorTest` covering `statusFor()` with known and unknown capability kinds.

- [ ] **R10.9** — Add `MemoryContextStorageTest` covering LIFO violation (`detach` out-of-order) and type-mismatch (`tokenId` with foreign token).

- [ ] **R10.10** — Add `TaskRecordTest` covering `addHandle()`, `clearHandles()`, `handles()`, `isDone()`, `markDone()`.

- [ ] **R10.11** — Add `SchedulerFailureArtifactTest` covering `normalize()` structure.

- [ ] **R10.12** — Add `PollerVocabularyTest` covering constant values.

- [ ] **R10.13** — Add `@covers` annotations to all new test classes; verify no cross-test coverage leakage.

### Verification

```bash
vendor/bin/codecept run Unit --coverage-text
```

**Exit criteria:** Line coverage ≥95%; method coverage ≥95%; class coverage ≥90% (allowing for extension-gated adapters).

---

## Phase R11 — Declaration Ordering & Class Consistency (addresses F3, F7, F8)

**Goal:** Fix declaration-order violations and `readonly` inconsistency.

### Tasks

- [ ] **R11.1** — Reorder `AsyncRuntime` (`src/SDK/AsyncRuntime.php`): move `discover()` (static) after all non-static methods (`channel()`, `telemetry()`, `run()`, `capability()`).

- [ ] **R11.2** — Reorder `AsyncRuntimeFactory` (`src/SDK/Factory/AsyncRuntimeFactory.php`): move `create()` (static) after all non-static methods (`negotiate()`, `buildTelemetry()`).

- [ ] **R11.3** — Audit all `src/` files for declaration ordering (traits → constants → properties → constructor → factory methods → non-static methods → static methods). Fix any remaining violations.

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

- [ ] **R12.1** — Optimize `FiberTaskScheduler::run()` (`src/Application/Scheduler/FiberTaskScheduler.php:66-83`):
  - Replace full-iteration `foreach` + `array_filter` with a **ready queue** (`SplObjectStorage` or a dedicated queue of ready `TaskRecord`s) so only unblocked tasks are stepped.
  - Maintain a separate set of suspended (I/O-pending) records.
  - On each tick: step all ready records → poll → move newly-ready records to the ready queue → repeat until both queues are empty.

- [ ] **R12.2** — Optimize `EmulatedChannel::receive()` (`src/Domain/Channel/EmulatedChannel.php:58`):
  - Replace `array_shift()` (O(n)) with a `Ds\Queue` or a head-index + tail-index circular buffer for O(1) dequeue.
  - Alternatively: use a simple head-index counter into the array and periodically compact.

- [ ] **R12.3** — Optimize `MemoryContextStorage::detach()` (`src/Application/Context/MemoryContextStorage.php:77`):
  - Remove the `array_values()` reindex. Since the storage already uses `array_key_last()` semantics elsewhere, track the stack as a LIFO without requiring contiguous keys.

- [ ] **R12.4** — Add or update `phpbench.json` benchmarks for `FiberTaskScheduler::run()` throughput and `EmulatedChannel::receive()` latency. Run before/after to measure improvement.

### Verification

```bash
vendor/bin/phpbench run
```

**Exit criteria:** `run()` complexity reduced from O(n²) to O(ready + suspended) per tick; `receive()` from O(n) to O(1); benchmark results documented in `docs/performance-baseline.md`.

---

## Phase R13 — Assembler Decomposition (addresses F14)

**Goal:** Reduce `RuntimeAdapterAssembler`'s external coupling from 23 to ≤8 per assembler.

### Tasks

- [ ] **R13.1** — Create `RuntimeAdapterFactoryInterface` with a single `assemble(CapabilityDescriptor): RuntimeAssembly` method.

- [ ] **R13.2** — Create per-runtime assemblers:
  - `SwooleRuntimeAssembler` (depends on Swoole adapter classes only)
  - `SwowRuntimeAssembler` (depends on Swow adapter classes only)
  - `RevoltRuntimeAssembler` (depends on Revolt adapter classes only)
  - `ReferenceRuntimeAssembler` (depends on reference poller/storage only)

- [ ] **R13.3** — Refactor `RuntimeAdapterAssembler` to delegate to the per-runtime assemblers via a `match` expression, reducing its import surface.

- [ ] **R13.4** — Update `AsyncRuntimeFactory` to use the refactored `RuntimeAdapterAssembler` (no interface change — the assembler interface stays the same).

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

- [ ] **R14.2** — Update or revise `docs/REVIEW.md` README note (F16) — either implement the `spawn()`/`awaitReadable()` API surface or add an explicit "planned API" section to the README with `@todo` annotations.

### Verification

```bash
composer test:unit   # existing engine verifier tests pass
```

**Exit criteria:** `EngineCapability` fields are fully derived from the negotiated `CapabilityDescriptor`; no hardcoded `false` literals remain.

---

## Phase R15 — README/API Alignment (addresses F16)

**Goal:** Align the README example with the actual public API.

### Tasks

- [ ] **R15.1** — Update `README.md` example to use the actual `AsyncRuntime::discover()` → `run()` API, or mark the `spawn()`/`awaitReadable()` API as "planned, not yet implemented" with a clear `@since` version note.

- [ ] **R15.2** — Add a "Public API Reference" section to the README listing the actual stable contracts: `AsyncRuntime`, `SchedulerInterface`, `EngineSchedulerInterface`, `TaskInterface`, `CancellationScopeInterface`, `PollerPortInterface`, `TaskChannelInterface`, `CapabilityDescriptor`, `RuntimeDiscoveryInterface`, `TelemetryBridgeInterface`.

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
