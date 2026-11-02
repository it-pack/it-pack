# AsyncIt In-Depth Review

**Package:** `it-pack/async-it`  
**Path:** `src/AsyncIt/`  
**PHP:** 8.5.12  
**Review date:** 2026-11-01  
**Reviewer:** Kilo

---

## Executive Summary

AsyncIt is a well-structured, capability-oriented scheduling engine with clean hexagonal architecture, strong test coverage of core domain logic, and good modern PHP feature usage. However, it falls short of the **95% coverage requirement** (~79% on the Unit suite), has **87 phparkitect violations** indicating architectural drift, contains **significant duplicate code** across the three reactor poller adapters (Revolt/Swoole/Swow), and violates the **"new class instances only in factories"** rule across many non-factory files. Security foundations are strong (context isolation, handle release), but magic literals and unverified performance claims need attention.

---

## Checklist (What Was Reviewed)

1. Features and overall architecture — hexagonal ports/adapters, layering, dependency direction
2. Maintenance and maintainability — documentation, ADRs, code organization, architectural drift
3. Performance — monotonic clocks, bounded buffers, hot-path considerations, benchmarks
4. Security — context isolation, handle leaks, deny-by-default, negative tests
5. Developer experience — documentation, code style, static analysis, test runner
6. SOLID and DRY principles — interfaces, composition, traits, duplication
7. Hexagonal architecture and ports/adapters — port contracts, adapter implementations, boundary enforcement
8. Factories and builders — factory usage, instantiation rules, dependency injection
9. Duplicate code — repeated logic, literals, abstractions
10. Modern PHP features — fibers, weak maps/refs, lazy objects, property hooks, traits, readonly

---

## 1. Features and Overall Architecture

### Strengths

- **Clear five-tier semantic stack** (Spindle → AExO → RunIt → AsyncIt → Engine/OS) documented in `README.md`.
- **Strict separation of concerns**: Polling ≠ Scheduling ≠ Execution Semantics, enforced in `AGENTS.md` and `docs/EXECUTION.md`.
- **Capability-first design**: `CapabilityDescriptor` negotiates runtime features at the process boundary instead of `extension_loaded()` checks.
- **Fiber-safe context propagation**: Detach-on-suspend / re-attach-on-resume via `finally` guard (conformance Assertion A).
- **Observed cooperative cancellation**: Closed task lifecycle (`CREATED → RUNNING → terminal`), `ConflictingCompletionException` on conflicting transitions, handle release on terminal transition (Assertion C).
- **Monotonic clock** (`hrtime()`) for all durations — no wall-clock skew.

### Architecture Layers (per `phparkitect.php`)

| Layer | May Depend On |
|-------|--------------|
| Common | — |
| Domain | Common, Port |
| Port | Common, Domain |
| Application | Common, Domain, Port, Adapter |
| SDK | Common, Domain, Port, Adapter, Application |
| Adapter | Common, Domain, Port |
| Conformance | Common, Domain, Port, Application, SDK, Adapter |

Contract (excl. Port) is enforced separately via a custom rule forbidding dependencies on Domain/Application/SDK/Adapter/Conformance.

### Assessment

The architecture is sound and well-documented. The phparkitect rules correctly encode the intended dependency direction. However, **87 violations** (see §3) indicate the codebase has drifted from its own architectural contract in several places.

---

## 2. Maintenance and Maintainability

### Strengths

- **Comprehensive documentation**: `AGENTS.md` (894 lines), `README.md`, `docs/EXECUTION.md`, `docs/FEATURE_BACKLOG.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/ROADMAP.md`, `docs/TODOS.md`, 10 background docs including 2 ADRs.
- **No abstract classes** — confirmed via scan. Only a comment reference to a removed `AbstractRuntimeFixture` in `RuntimeFixtureTrait.php`.
- **Traits used for deduplication**: `TaskBehavior`, `CombinatorTrait`, `ChannelBehaviorTrait`, `StreamSelectPollerTrait`, `CoroutineContextStorageTrait`.
- **Code style passes**: PHP-CS-Fixer reports 0 files needing fixes.
- **PHPStan passes**: 0 errors at configured level.
- **Clear file organization**: `src/Contract/`, `src/Contract/Port/`, `src/Domain/`, `src/Application/`, `src/SDK/`, `src/Adapter/`, `src/Conformance/`.

### Concerns

- **87 phparkitect violations** across 42 classes. The most significant:
  - `SDK\AsyncRuntime` — 12 violations (methods not backed by interfaces: `all`, `any`, `await`, `channel`, `health`, `join`, `race`, `select`, `spawn`, `stream`, `telemetry`, `awaitDelay`)
  - `Conformance\Result\ConformanceResult` — 5 violations
  - `Contract\TaskChannelInterface` — 5 violations
  - `Application\Scheduler\TaskRecord` — 7 violations
  - `Application\Federation\CanaryScheduler` — 4 violations
  - `Application\Scheduler\FiberTaskScheduler` — 4 violations
  - `SDK\Factory\RuntimeAssemblerInterface` — depends on class `AsyncRuntime` (interface depending on class)
  - `Domain\PersistentTask`, `Domain\RateLimiter`, `Domain\SchedulerConfiguration`, `Domain\TaskGroup` — methods not backed by interfaces

- **`FiberTaskScheduler.php` is 976 lines** — a clear complexity outlier. It handles scheduling, timing, IO await, cancellation, lifecycle publication, and health reporting. This violates the single-responsibility principle and makes the file hard to maintain.
- **Magic literals** scattered throughout (see §9).
- **No CHANGELOG or version pinning guidance** in `REVIEW.md` context.

### Maintenance Grade: B

Good documentation and clean style, but architectural drift (87 violations) and one oversized class create maintenance risk.

---

## 3. Performance

### Strengths

- **Monotonic clock** (`hrtime()`) via `MonotonicClockInterface` — no wall-clock skew for duration measurements.
- **Bounded task results buffer**: `MAX_TASK_RESULTS = 1000` with FIFO eviction in `FiberTaskScheduler::complete()`.
- **Bounded channel queue**: `EmulatedChannel` compacts when head exceeds half the queue length.
- **WeakMap usage** in `MemoryContextStorage` and `RevoltAssemblyFactory` for non-owning Fiber-to-context associations.
- **Lazy objects**: `LazyTelemetryBridge` defers recorder construction until first use.
- **Average CCN**: 4.71 (low complexity).
- **Average WMC**: 7.05 per class.

### Concerns

- **`FiberTaskScheduler::runUntil()`** is the reactor hot path (976 lines). It runs `array_shift()` on `$ready` (O(n) per shift) and rebuilds `$timers` via `array_filter()` on every terminal transition. No benchmarks exist to quantify impact.
- **No benchmarks run**: `phpbench.json` exists but no benchmark data was collected. The AGENTS.md requires benchmarks for hot-path changes (spawn, completion, suspension/resumption, poll-handle registration/teardown).
- **`computeNearestTimerDeadline()`** iterates all timers on each tick — O(n) where n = pending timers.
- **No performance regression testing** in CI.

### Performance Grade: B

Solid fundamentals (monotonic clock, bounded buffers, WeakMap), but missing benchmarks and hot-path optimization data. The reactor's O(n) operations on the hot path need measurement.

---

## 4. Security

### Strengths

- **Context isolation**: `contextStorage->attach()` / `detach()` around every Fiber resume/suspend in `step()` (Assertion A).
- **Handle release**: Every registered poll handle released on terminal transition in `complete()` → `releaseHandles()` + `clearTimers()` (Assertion C).
- **Deny-by-default**: Context classification and redaction delegated to RunIt context model.
- **No global mutable scheduling state**: Confirmed via scan — no `static` request-specific state in scheduling services.
- **Handle leak detection**: `StreamPollHandleLeakException` exists; conformance Assertion C tests for leaks.
- **No secrets exported**: AsyncIt respects RunIt's classification model.
- **Exceptions preserve original throwable**: `FiberTaskScheduler::resume()` rethrows the exact `Throwable`, not a wrapper.

### Concerns

- **`FiberTaskScheduler::publishLifecycle()`** publishes `SchedulerFailureArtifact($throwable->getMessage())` — only the message, not the full exception. Stack trace context is lost for failure artifacts.
- **Negative security tests**: AGENTS.md requires "context-bleed" and "handle-leak" negative tests. Conformance verifiers exist (`ContextIsolationVerifier`, `DeterministicCleanupVerifier`, `GracefulShutdownVerifier`) but only 7 conformance tests with 3 skipped — coverage is thin.
- **Magic strings used as metric names** (e.g., `asyncit_tasks_runnable`, `asyncit_poll_wait_seconds` in Prometheus bridge) — AGENTS.md warns against user-controlled values as metric names. These are hardcoded, not user-controlled, but still magic literals.
- **`awaitIo()`** stores handles in `$ioAwaiters` and `$ioTaskHandles` — these maps are cleaned up in `releaseHandles()` but the cleanup relies on `complete()` being called; if a task is abandoned without `complete()`, handles could leak.

### Security Grade: B+

Strong foundational security (context isolation, handle release, no global state), but thin negative-test coverage and potential handle leak edge cases.

---

## 5. Developer Experience (DX)

### Strengths

- **PHP-CS-Fixer**: 0 violations, clean and consistent style.
- **PHPStan**: 0 errors at max level.
- **Clear entry point**: `AsyncRuntime::discover()` factory method.
- **Good README**: Usage example, API reference, architectural pillars.
- **Well-organized tests**: 153 test files across Unit, Integration, Functional, Conformance, Concurrency suites.
- **Clear error messages**: Most exceptions include descriptive messages with context.

### Concerns

- **No `REVIEW.md` existed** before this review — the project lacks a consolidated review document.
- **Test coverage tooling is fragile**: Running `codecept run --coverage` fails on the Acceptance suite (serialized coverage file missing). Coverage must be collected per-suite.
- **Some adapters have very low test coverage** (OpenTelemetry bridge at 2%, Amp bridge at 47%, Revolt/Swoole/Swow pollers at 33-62%).
- **No contributor guide or `CONTRIBUTING.md`**.

### DX Grade: B+

Clean tooling and clear APIs, but fragmented coverage collection and low adapter test coverage hinder contributor confidence.

---

## 6. SOLID and DRY Principles

### SOLID Assessment

| Principle | Status | Evidence |
|-----------|--------|----------|
| Single Responsibility | Partial | `FiberTaskScheduler` (976 lines) does scheduling, timing, IO, cancellation, publication, health |
| Open/Closed | Good | Ports allow new adapters without modifying core |
| Liskov Substitution | Good | All adapters implement `PollerPortInterface`, `TaskChannelInterface`, etc. |
| Interface Segregation | Partial | 87 phparkitect violations show interfaces not fully backing implementations |
| Dependency Inversion | Good | Factories injected, not constructed directly in most services |

### DRY Assessment

**Good DRY**:
- `StreamSelectPollerTrait` shared by SelectPoller, NativePoller, RevoltPoller, SwoolePoller, SwowPoller for readiness probing.
- `CombinatorTrait` shared by RaceGroup, SelectGroup, AllGroup, AnyGroup for constructor/add/cancelRemaining/findChild.
- `ChannelBehaviorTrait` shared by SwooleChannel and SwowChannel for closed-state tracking.
- `TaskBehavior` used by Task for lifecycle state machine.
- `CoroutineContextStorageTrait` shared by RevoltFiberContextStorage and SwooleCoroutineContextStorage.

**Violations**:
- **"New class instances must only be created in factories or builders"** — violated extensively outside factories:
  - `FiberTaskScheduler.php`: `new LogicException` (11×), `new RuntimeException`, `new SchedulerFailureArtifact`
  - `MemoryContextStorage.php`: `new ContextToken`
  - `ReferenceExecutionScope.php`: `new BadMethodCallException` (7×)
  - Domain classes: `new InvalidArgumentException`, `new DomainException`, `new RateLimitExceededException`, etc.
  - Conformance verifiers: `new ConformanceResult`, `new ConformanceViolation` (many)
  - Federation classes: `new LogicException`, `new InvalidArgumentException`, `new BackpressureExceededException`
- **"Every concrete service class must be backed by an interface"** — 87 phparkitect violations show many final classes lack full interface backing.
- **No `else`/`elseif`**: Confirmed — zero occurrences. Good.
- **No Yoda-style conditions**: Confirmed — no `constant == variable` patterns.
- **No magic numbers as bare literals in most cases**: Constants used for `MAX_TASK_RESULTS`, `DEFAULT_FAILURE_THRESHOLD`, `NANOSECONDS_PER_SECOND`, etc.

### SOLID/DRY Grade: B-

Good use of traits and interfaces at boundaries, but the "new only in factories" rule is widely violated and `FiberTaskScheduler` violates SRP.

---

## 7. Hexagonal Architecture — Ports and Adapters

### Ports (defined in `src/Contract/Port/`)

| Port | Interface |
|------|-----------|
| Polling | `PollerPortInterface` |
| Context storage | `ContextStorageInterface` (RunIt) |
| Publication | `ArtifactPublisherInterface` (RunIt) |
| Clock | `MonotonicClockInterface` (RunIt) |
| Cancellation reason | `CancellationReason` (enum) |
| Task status | `TaskStatus` (enum) |
| Capability status/kind | `CapabilityStatus`, `CapabilityKind` (enums) |
| Scheduler event type | `SchedulerEventType` (enum) |
| Circuit state | `CircuitState` (enum) |
| Retry/jitter strategy | `RetryStrategy`, `JitterStrategy` (enums) |
| Backpressure | `BackpressurePolicy` (enum) |
| Scheduling policy | `SchedulingPolicy` (enum) |
| Stream poll handle | `StreamPollHandleInterface` |

### Adapters

| Adapter Type | Implementations |
|-------------|-----------------|
| Poller | `SelectPoller`, `NativePoller`, `RevoltPoller`, `SwoolePoller`, `SwowPoller` |
| Channel | `EmulatedChannel`, `SwooleChannel`, `SwowChannel`, `RevoltChannel` |
| Context storage | `MemoryContextStorage`, `RevoltFiberContextStorage`, `SwooleCoroutineContextStorage`, `SwowCoroutineContextStorage` |
| Telemetry | `OpenTelemetryTelemetryBridge`, `PrometheusTelemetryBridge`, `ArrayTelemetryRecorder`, `LazyTelemetryBridge` |
| Publisher | `NullArtifactPublisher`, `FailSafeArtifactPublisher`, `InMemoryArtifactPublisher` |
| Interop | `AmpPollerBridge`, `ReactPhpPollerBridge` |

### Assessment

The ports/adapters pattern is correctly implemented. Each adapter implements a port contract without leaking runtime-specific types into domain/application layers. The `RuntimeAdapterAssembler` cleanly dispatches to per-runtime factory classes.

**Concern**: `RuntimeAdapterAssembler::defaultFactory()` directly instantiates `SwooleAssemblyFactory`, `SwowAssemblyFactory`, `RevoltAssemblyFactory`, `ReferenceAssemblyFactory` — these are concrete classes instantiated outside a factory, violating the "new only in factories" rule.

### Hexagonal Architecture Grade: B+

Correct port/adapter separation with clean dependency direction, but the assembler factory violates the instantiation rule.

---

## 8. Factories and Builders

### Factories Present

| Factory | Interface | Purpose |
|---------|-----------|---------|
| `TaskFactory` | `TaskFactoryInterface` | Create tasks |
| `TaskRecordFactory` | `TaskRecordFactoryInterface` | Create task records |
| `TaskIdFactory` | `TaskIdFactoryInterface` | Create task IDs |
| `PollerFactory` | `PollerFactoryInterface` | Create pollers |
| `FiberFactory` | `FiberFactoryInterface` | Create fibers |
| `SchedulerFactory` | `SchedulerFactoryInterface` | Create schedulers |
| `TaskGroupFactory` | `TaskGroupFactoryInterface` | Create task groups |
| `TaskStreamFactory` | `TaskStreamFactoryInterface` | Create task streams |
| `CancellationScopeFactory` | `CancellationScopeFactoryInterface` | Create cancellation scopes |
| `CascadingCancellationScopeFactory` | — | Create cascading scopes |
| `CombinatorFactory` | `CombinatorFactoryInterface` | Create combinators (race/all/any/select) |
| `ExecutionScopeFactory` | `ExecutionScopeFactoryInterface` | Create execution scopes |
| `PerRunExecutionScopeFactory` | — | Per-run scope factory |
| `ReferenceExecutionScopeFactory` | — | Reference scope factory |
| `DeadLetterAwarePromiseFactory` | `DeadLetterAwarePromiseFactoryInterface` | Create DLQ promises |
| `SchedulerLifecycleArtifactFactory` | — | Create lifecycle artifacts |
| `ReferenceAssemblyFactory` | — | Reference adapter assembly |
| `RuntimeDiscoveryFactory` | `RuntimeDiscoveryFactoryInterface` | Runtime discovery |
| `RuntimeAdapterAssembler` | `RuntimeAdapterAssemblerInterface` | Adapter assembly facade |
| `FiberScopeSlotFactory` | `FiberScopeSlotFactoryInterface` | Create fiber scope slots |

### Assessment

The factory pattern is well-established. Most application services receive factories via constructor injection. However:

**Violations of "new only in factories"**:
- `RuntimeAdapterAssembler::defaultFactory()` instantiates concrete assembly factories directly.
- `AsyncRuntimeFactory` instantiates many concrete classes directly (this is the composition root, which is acceptable).
- Domain and application classes instantiate exceptions and value objects directly (`new LogicException`, `new InvalidArgumentException`, `new ContextToken`).

### Factories Grade: B

Strong factory infrastructure, but the rule is not consistently applied to exceptions/value objects and the assembler's default factory.

---

## 9. Duplicate Code

### Critical Duplication: Poller Adapters

The four reactor pollers (`RevoltPoller`, `SwoolePoller`, `SwowPoller`, `NativePoller`) plus `SelectPoller` share nearly identical `register()`/`release()` logic via `StreamSelectPollerTrait`, but each duplicates:

```php
// RevoltPoller::register()
$id = 'revolt-' . ++$this->counter;
$this->handles[$id] = $stream;
$this->registerWithReactor($id, $stream);
return new StreamPollHandle($id);

// SwoolePoller::register()
$id = 'swoole-' . ++$this->counter;
// ... identical except prefix

// SwowPoller::register()
$id = 'swow-' . ++$this->counter;
// ... identical except prefix

// NativePoller::register()
$id = 'native-' . ++$this->counter;
// ... identical except prefix
```

The `release()` methods are also near-identical, differing only in reactor-specific cleanup calls (`EventLoop::cancel`, `Event::del`, `method_exists($this->poll, 'unregister')`).

**Estimated duplication**: ~60 lines × 4 adapters = ~240 lines of near-identical code.

**Recommendation**: Extract a `ReactorPollerTrait` or base class that handles ID generation, handle registration, and release bookkeeping, leaving only the reactor-specific `registerWithReactor()`/`releaseFromReactor()` abstract methods. The `StreamSelectPollerTrait` already provides `await()`/`readyHandles()` — it should also provide `register()`/`release()` with abstract reactor hooks.

### Other Duplication

- **Channel adapters** (`SwooleChannel`, `SwowChannel`, `RevoltChannel`): Each repeats the `if ($this->closed) throw new ChannelClosedException();` guard in `send()`/`trySend()`. Partially mitigated by `ChannelBehaviorTrait` for Swoole/Swow, but `RevoltChannel` delegates to `EmulatedChannel` instead, so the guard is duplicated there.
- **Magic literals**: Many hardcoded strings (Redis key prefixes `asyncit:task:`, Prometheus metric names, error messages) repeated across files without constant extraction.
- **Factory methods in `AsyncRuntime`**: `race()`, `all()`, `any()`, `select()`, `join()` each repeat the pattern of creating a combinator, adding operations, and calling `await()`.

### Duplicate Code Grade: C

Significant duplication across poller adapters that should be deduplicated via a shared trait or abstract base.

---

## 10. Modern PHP Feature Usage

| Feature | Usage | Assessment |
|---------|-------|------------|
| Fibers | Used in `FiberTaskScheduler`, `PollerPortInterface`, `TaskStreamInterface`, `FiberFactory` | Good — via adapters, not domain |
| WeakMap | `MemoryContextStorage`, `RevoltAssemblyFactory` | Good — non-owning Fiber→context associations |
| WeakReference | None found | Not used where potentially useful (e.g., per-task metadata) |
| Lazy objects | `LazyTelemetryBridge` (defers recorder) | Good — but only one usage |
| Property hooks | `TaskId::$value`, `TaskBehavior::$status` | Good — validation and computed properties |
| Virtual properties | None found | Not used |
| Readonly classes | `CapabilityDescriptor`, `TaskRecord`, `AsyncRuntime`, most factories | Good — extensive use |
| Readonly properties | `TaskBehavior::$id`, `$executionId`, `$cancellationScope` | Good |
| Enums | `TaskStatus`, `CapabilityStatus`, `CapabilityKind`, `AwaitPoint`, `CancellationReason`, `CircuitState`, `SchedulerEventType`, `RuntimeAdapterKind`, `SchedulingPolicy`, `BackpressurePolicy`, `RetryStrategy`, `JitterStrategy`, `ViolationSeverity` | Good — closed sets modeled as enums |
| Match expressions | `TaskStatus::isTerminal()`, `RuntimeAdapterAssembler::defaultFactory()`, `CircuitBreaker` state checks | Good |
| Traits | `TaskBehavior`, `CombinatorTrait`, `ChannelBehaviorTrait`, `StreamSelectPollerTrait`, `CoroutineContextStorageTrait` | Good — used for deduplication |
| `never` return type | Not found | Not used |
| `readonly class` | Extensive use across Domain, Application, SDK | Good |

### Assessment

Modern PHP features are used appropriately where they add clarity:
- **Property hooks** on `TaskId` and `TaskBehavior` provide validation without boilerplate getters/setters.
- **Readonly classes** are the default for value objects and factories.
- **Enums** model all closed sets (task status, capability status, circuit state, etc.).
- **WeakMap** used correctly for non-owning Fiber context associations.

**Missing/underused**:
- **`WeakReference`**: Could be used for per-task metadata that shouldn't prevent GC.
- **`Lazy` objects**: Only `LazyTelemetryBridge` uses lazy initialization. Other expensive adapters could benefit.
- **Virtual properties**: Not used — could be useful for computed projections.
- **`never` return type**: Not used for methods that should never throw.

### Modern PHP Grade: B+

Good adoption of enums, readonly, property hooks, WeakMap, and Fibers. Underuse of WeakReference, Lazy objects, and virtual properties.

---

## 11. Testing and Coverage

### Test Results

| Suite | Tests | Assertions | Status |
|-------|-------|------------|--------|
| Unit | 492 | 1,067 | OK |
| Integration | 5 | 14 | OK |
| Functional | 72 | 152 | OK |
| Conformance | 7 | 4 | OK (3 skipped) |
| Concurrency | 5 | 10 | OK |
| **Total** | **581** | **1,247** | **All pass** |

### Coverage

| Metric | Value | Requirement |
|--------|-------|-------------|
| Unit suite lines | 78.93% (1862/2359) | 95% |
| Unit suite methods | 74.51% (386/518) | 95% |

**Coverage is below the 95% requirement.**

### Per-Class Coverage (Low Coverage Classes)

| Class | Lines | Methods | Issue |
|-------|-------|---------|-------|
| `OpenTelemetryTelemetryBridge` | 2% | 11% | Almost untested |
| `AmpPollerBridge` | 47.6% | 25% | Low coverage |
| `RevoltPoller` | 62.5% | 33% | Low coverage |
| `SwoolePoller` | 62.5% | 33% | Low coverage |
| `SwowPoller` | 62.5% | 33% | Low coverage |
| `AsyncRuntimeFactory` | 69.9% | 66.7% | Factory not fully covered |
| `PollerFactory` | 57.1% | 50% | Factory not fully covered |

### Coverage Isolation

Codeception config (`codeception.yml`) includes `src/*` and excludes `tests/*` and `vendor/*`. Coverage is collected per-suite, so tests do not leak coverage into other suites' code. However, the Acceptance suite fails to produce coverage data (missing serialized file).

### Testing Grade: C+

All 581 tests pass, but coverage is ~79% — well below the 95% requirement. Key adapters and factories are under-tested.

---

## Summary Table

| Area | Grade | Key Issues |
|------|-------|-----------|
| Features & Architecture | B | Sound architecture, 87 phparkitect violations |
| Maintenance & Maintainability | B | Good docs, but `FiberTaskScheduler` at 976 lines |
| Performance | B | No benchmarks, O(n) ops on hot path |
| Security | B+ | Strong context isolation, thin negative tests |
| DX | B+ | Clean tooling, fragmented coverage collection |
| SOLID/DRY | B- | "new only in factories" violated, SRP violation in scheduler |
| Hexagonal/Ports-Adapters | B+ | Correct pattern, assembler violates instantiation rule |
| Factories/Builders | B | Strong infra, inconsistent rule application |
| Duplicate Code | C | Poller adapters duplicated (~240 lines) |
| Modern PHP | B+ | Good enum/readonly/hooks/WeakMap, missing WeakReference/Lazy |
| Testing & Coverage | C+ | 79% coverage vs 95% required, adapters under-tested |

---

## Recommendations (Priority Order)

### High Priority

1. **Raise coverage to 95%**: Add tests for `OpenTelemetryTelemetryBridge`, `RevoltPoller`, `SwoolePoller`, `SwowPoller`, `AsyncRuntimeFactory`, `PollerFactory`. Fix the Acceptance suite coverage collection issue.

2. **Deduplicate poller adapters**: Extract `register()`/`release()` from `StreamSelectPollerTrait` into a shared base/trait with abstract reactor hooks. Eliminates ~240 lines of duplication across Revolt/Swoole/Swow/Native/Select pollers.

3. **Fix phparkitect violations (87)**: Either back methods with interfaces, move interfaces to depend only on interfaces, or update the architecture rules. `SDK\AsyncRuntime` (12 violations) and `Application\Scheduler\TaskRecord` (7 violations) are the worst offenders.

4. **Split `FiberTaskScheduler` (976 lines)**: Extract timer management, IO await handling, and lifecycle publication into separate services to restore SRP.

5. **Enforce "new only in factories"**: Move `new LogicException`, `new InvalidArgumentException`, `new ContextToken`, etc. out of non-factory classes, or explicitly document exceptions/value objects as exempt.

### Medium Priority

6. **Add benchmarks**: Run `phpbench` for task spawn, completion, suspension/resumption, and poll-handle registration/teardown.

7. **Extract magic literals**: Replace hardcoded strings (Redis prefixes, Prometheus metric names, error messages) with constants/enums in interfaces per the AGENTS.md rule ("vocabularies live in interfaces").

8. **Add `WeakReference` and `Lazy` usage**: Where object-lifetime independence or deferred initialization would help.

9. **Add negative security tests**: Context-bleed and handle-leak tests per AGENTS.md requirements.

### Low Priority

10. Add `CONTRIBUTING.md` and `CHANGELOG.md`.
11. Consider `never` return type for methods that should never throw.

---

## Assumptions

- The phparkitect rules in `phparkitect.php` accurately reflect the intended architecture (they were authored by the project maintainers).
- The 95% coverage requirement applies to the Unit suite (the only suite with meaningful coverage data).
- Exceptions and value objects (`LogicException`, `InvalidArgumentException`, `ContextToken`) are intended to be exempt from the "new only in factories" rule, but this is not explicitly documented.
- Coverage was measured using pcov; the Codeception coverage XML had structural issues with the Acceptance suite, so per-suite coverage was used instead.

---

## Unverified Assumptions / Risks

- **Performance claims** about the reactor hot path are unverified — no benchmarks exist.
- **Security negative tests** (context-bleed, handle-leak) are thin — only 7 conformance tests with 3 skipped.
- **The "new only in factories" rule's scope** regarding exceptions/value objects is not explicitly defined in AGENTS.md.
- **phparkitect violations** may be intentional architectural decisions rather than drift — this requires maintainer confirmation.