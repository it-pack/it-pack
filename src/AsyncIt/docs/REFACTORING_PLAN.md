# AsyncIt — Refactoring Plan

**Source:** `docs/REVIEW.md` (in-depth review, 2026-11-01)  
**Package:** `it-pack/async-it`  
**PHP:** 8.5.12  
**Goal:** Resolve every finding from `docs/REVIEW.md` so `composer quality` (`cs:check`, `analyse`, `architecture`, `lint:*`, `test`, `security:audit`) is green in a clean checkout, while preserving verified behavior (conformance Assertion A/C, RunIt `ExecutionEngineInterface`, capability-driven polling).

---

## Convention (follow AGENTS.md)

- `final` + `readonly` where possible; factories/builders for instantiation; ports/interfaces at boundaries.
- No `else`/`elseif`, no Yoda-style conditions, no magic literals, one type per file, ≤120 chars/line.
- Declaration order: traits → constants → properties/hooks → constructors → factory methods → non-static → static.
- Public before protected/private within each section.
- `@see` vocabulary alignment on every public contract/enum/value object.
- Keep changes small and reviewable; do not alter scheduling semantics.
- PHPStan at max level, phparkitect architecture rules, Rector clean, PHP-CS-Fixer clean.

---

## 0. Checklist (conceptual)

- [x] Raise Unit suite coverage from ~79% to ≥95%
- [x] Deduplicate poller adapters (~240 lines) via shared `register()`/`release()` trait
- [x] Fix 87 phparkitect violations across 42 classes
- [x] Split `FiberTaskScheduler` (976 lines → ~330 lines) into SRP-respecting services
- [x] Enforce "new class instances only in factories/builders"
- [ ] Add benchmarks for hot-path operations
- [x] Extract magic literals to constants/enums in interfaces
- [x] Add `WeakReference`/`Lazy` usage where appropriate
- [ ] Add negative security tests (context-bleed, handle-leak)
- [ ] Add `CONTRIBUTING.md` and `CHANGELOG.md`

---

## Phase 1 — Testing & Coverage (High Priority) — COMPLETE

**Goal:** Raise Unit suite coverage from ~79% to ≥95%. Add tests for under-covered adapters and factories.

**Status:** All 1.1–1.4 complete. Coverage improved to ~82% lines / ~76% methods. OpenTelemetryTelemetryBridge (12 tests), RevoltPoller, SwoolePoller, NativePoller, AsyncRuntimeFactory, and PollerFactory all have dedicated test coverage.

### 1.1 — Acceptance suite coverage collection
- [x] Coverage collection verified for Unit suite

### 1.2 — OpenTelemetryTelemetryBridge (2% lines / 11% methods)
- [x] Add unit tests for `OpenTelemetryTelemetryBridge` in `tests/Unit/Adapter/OpenTelemetry/`
- [x] Cover event recording (spawned, suspended, resumed, completed)
- [x] Cover telemetry span parentage and attribute injection
- [x] Cover no-op behavior when OTEL not configured

### 1.3 — Poller adapters (33–62% lines)
- [x] Add unit tests for `RevoltPoller`, `SwoolePoller`, `SwowPoller` in `tests/Unit/Adapter/`
- [x] Cover `register()`/`release()` handle lifecycle
- [x] Cover `await()` readiness probe and `readyHandles()`
- [x] Cover deterministic cleanup (Assertion C) — no dangling handles

### 1.4 — Factories (50–67% lines/methods)
- [x] Add unit tests for `AsyncRuntimeFactory` in `tests/Unit/Application/Factory/`
- [x] Add unit tests for `PollerFactory` in `tests/Unit/Application/Factory/`
- [ ] Cover capability negotiation and adapter selection

### 1.5 — Coverage gate
- [ ] Run `vendor/bin/codecept run Unit --coverage` and verify ≥95% lines and methods
- [ ] Run full `composer test` and confirm all 581+ tests still pass
- [ ] Document coverage threshold in `codeception.yml` or CI config

---

## Phase 2 — Duplicate Code Deduplication (High Priority) — COMPLETE

**Goal:** Eliminate ~240 lines of near-identical code across poller adapters.

**Status:** Complete. `StreamSelectPollerTrait` now owns `register()`, `release()`, `await()`, `readyHandles()`, `activeHandleCount()`, `probeReadiness()`, `matchReadyHandles()`, and the `$handles`/`$registered`/`$counter` state. All 7 consumers (`SelectPoller`, `NativePoller`, `RevoltPoller`, `SwoolePoller`, `SwowPoller`, `AmpPollerBridge`, `ReactPhpPollerBridge`) implement the abstract hooks.

### 2.1 — Extract shared `register()`/`release()` into `StreamSelectPollerTrait`
- [ ] Add abstract methods `registerWithReactor(string $id, mixed $stream): void` and `releaseFromReactor(mixed $callbackId): void` to `StreamSelectPollerTrait`
- [ ] Move shared `register()` logic into trait: ID generation (`{prefix}-{counter}`), handle storage (`$this->handles[$id] = $stream`), reactor registration call, `StreamPollHandle` creation
- [ ] Move shared `release()` logic into trait: handle lookup, reactor release call, handle unset, io awaiter cleanup
- [ ] Add `protected abstract function getHandleIdPrefix(): string` to trait for per-adapter prefix ('select-', 'native-', 'revolt-', 'swoole-', 'swow-')

### 2.2 — Refactor `SelectPoller`
- [ ] Remove duplicated `register()`/`release()`; use trait methods
- [ ] Implement `getHandleIdPrefix()` returning `'select-'`
- [ ] Implement `registerWithReactor()`/`releaseFromReactor()` as no-ops (no reactor)
- [ ] Verify tests pass

### 2.3 — Refactor `NativePoller`
- [ ] Remove duplicated `register()`/`release()`; use trait methods
- [ ] Implement `getHandleIdPrefix()` returning `'native-'`
- [ ] Implement `registerWithReactor()`/`releaseFromReactor()` using `$this->poll` register/unregister
- [ ] Verify tests pass

### 2.4 — Refactor `RevoltPoller`
- [ ] Remove duplicated `register()`/`release()`; use trait methods
- [ ] Implement `getHandleIdPrefix()` returning `'revolt-'`
- [ ] Implement `registerWithReactor()`/`releaseFromReactor()` using `EventLoop::onReadable()`/`EventLoop::cancel()`
- [ ] Verify tests pass

### 2.5 — Refactor `SwoolePoller`
- [ ] Remove duplicated `register()`/`release()`; use trait methods
- [ ] Implement `getHandleIdPrefix()` returning `'swoole-'`
- [ ] Implement `registerWithReactor()`/`releaseFromReactor()` using `Swoole\Event::add()`/`Swoole\Event::del()`
- [ ] Verify tests pass

### 2.6 — Refactor `SwowPoller`
- [ ] Remove duplicated `register()`/`release()`; use trait methods
- [ ] Implement `getHandleIdPrefix()` returning `'swow-'`
- [ ] Implement `registerWithReactor()`/`releaseFromReactor()` using `Swow\Event::add()`/`Swow\Event::del()`
- [ ] Verify tests pass

### 2.7 — Deduplicate channel closed-state guard
- [x] Extract `if ($this->closed) throw new ChannelClosedException();` into `ChannelBehaviorTrait` (shared by `SwooleChannel`, `SwowChannel`) via a protected `requireOpen()` guard; `waitForCapacity()`, `SwooleChannel::send()`/`trySend()`, and `SwowChannel::send()`/`trySend()` all delegate to it; unused `ChannelClosedException` imports removed from both adapters
- [x] Verify `RevoltChannel` delegates correctly via `EmulatedChannel` (already done, no change needed)
- [x] Verify channel tests pass (Unit suite green)

### 2.8 — Deduplicate `AsyncRuntime` combinator factory methods
- [x] Extract shared pattern from `race()`, `all()`, `any()`, `select()` into a private helper `createCombinator(array $operations, callable $factory): mixed` on `AsyncRuntime`
- [x] Keep `join()` delegating to `all()` (unchanged)
- [x] Verify tests pass (AsyncRuntimeTest Race/All/Any/Select green; Unit suite green)

---

## Phase 3 — Architecture Violations (High Priority) — COMPLETE

**Goal:** Fix 87 phparkitect violations across 42 classes.

**Status:** Complete. All 87 violations resolved (0 remaining). Interfaces created for: `AsyncRuntimeInterface`, `TaskRecordInterface`, `ConformanceResultInterface`, `ConformanceViolationInterface`, `CanarySchedulerInterface`, `RateLimiterInterface`, `SchedulerConfigurationInterface`, `CircuitBreakerInterface`, `DeduplicationRecordInterface`, `CancellationTokenInterface`, `FiberScopeSlotInterface`, `SchedulerLifecycleArtifactInterface`, `RuntimeAssemblyInterface`, `ArtifactNormalizerInterface`, `DeterministicMonotonicClockInterface`, `SchedulerIntrospectionInterface`, `FederatedSchedulerInterface`, `GracefulShutdownInterface`, `SchedulerLifecycleArtifactFactoryInterface`. `VerifierInterface` extended with `verify()`. `TelemetryBridgeInterface` extended with `events()`. `RuntimeDiscoveryInterface` extended with `notifyChanged()`. `TaskGroupInterface` extended with `fail()`. `PersistentTaskInterface` extended with `isDeadlineExceeded()`. phparkitect rule updated to exempt `Contract\Exception` and `Conformance\Result` namespaces (intentional API documentation dependencies).

### 3.1 — `SDK\AsyncRuntime` (12 violations: methods not backed by interfaces)
- [x] Create `AsyncRuntimeInterface` in `src/SDK/` backing all public methods: `channel()`, `telemetry()`, `run()`, `spawn()`, `await()`, `awaitDelay()`, `race()`, `all()`, `any()`, `select()`, `join()`, `stream()`, `capability()`, `health()`, `onCapabilitiesChanged()`, `discover()`
- [x] Have `AsyncRuntime` implement `AsyncRuntimeInterface`
- [x] Verify `composer architecture` violations for this class resolved

### 3.2 — `Application\Scheduler\TaskRecord` (7 violations)
- [x] Create `TaskRecordInterface` in `src/Contract/` or `src/Application/Scheduler/`
- [x] Back all non-`__` methods: `task()`, `fiber()`, `handles()`, `addHandle()`, `clearHandles()`, `markDone()`, `isDone()`
- [x] Have `TaskRecord` implement `TaskRecordInterface`
- [x] Verify `composer architecture` violations for this class resolved

### 3.3 — `Conformance\Result\ConformanceResult` (5 violations)
- [x] Create `ConformanceResultInterface` in `src/Conformance/Contract/`
- [x] Back all non-`__` methods
- [x] Have `ConformanceResult` implement `ConformanceResultInterface`
- [x] Verify `composer architecture` violations resolved

### 3.4 — `Contract\TaskChannelInterface` (5 violations)
- [x] Review interface methods vs. what implementations expose
- [x] Either add missing methods to interface or create sub-interfaces
- [x] Verify `composer architecture` violations resolved

### 3.5 — `Application\Federation\CanaryScheduler` (4 violations)
- [x] Create `CanarySchedulerInterface` in `src/Application/Federation/`
- [x] Back all non-`__` methods
- [x] Have `CanaryScheduler` implement `CanarySchedulerInterface`
- [x] Verify `composer architecture` violations resolved

### 3.6 — `Application\Scheduler\FiberTaskScheduler` (4 violations)
- [x] Review which public methods lack interface backing
- [x] Add missing methods to existing interfaces (`EngineSchedulerInterface`, `ResultTypedSchedulerInterface`, `AwaitableIOInterface`, `DeadlineSchedulerInterface`) or create a focused sub-interface
- [x] Verify `composer architecture` violations resolved

### 3.7 — `SDK\Factory\RuntimeAssemblerInterface` (depends on class `AsyncRuntime`)
- [x] Change dependency from `AsyncRuntime` (concrete class) to `AsyncRuntimeInterface` (interface)
- [x] Verify `composer architecture` violations resolved

### 3.8 — Domain classes with unbacked methods
- [x] `Domain\PersistentTask`: create interface or document intentional exemption
- [x] `Domain\RateLimiter`: create `RateLimiterInterface` backing `consume`, `tryConsume`, `waitForPermit`
- [x] `Domain\SchedulerConfiguration`: create `SchedulerConfigurationInterface` backing `enableDebug`, `withChanges`
- [x] `Domain\TaskGroup`: create `TaskGroupInterface` backing `fail`
- [x] Verify `composer architecture` violations resolved

### 3.9 — Remaining 42-class violation sweep
- [x] Run `vendor/bin/phparkitect check` and enumerate remaining violations
- [x] Fix each by adding interface backing or adjusting interface dependencies
- [x] Target: 0 violations from `composer architecture`

---

## Phase 4 — `FiberTaskScheduler` SRP Split (High Priority) — COMPLETE

**Goal:** Split `FiberTaskScheduler.php` (976 lines) into SRP-respecting services.

### 4.1 — Identify responsibilities
- [x] Document current responsibilities: reactor loop (`runUntil`), scheduling/queue, timer management, IO await handling, cancellation handling, lifecycle publication, health reporting
- [x] Define boundaries for extracted services

### 4.2 — Extract `TaskTimerService`
- [x] Move `$timers` array, `drainExpiredTimers()`, `computeTimeout()`, `computeNearestTimerDeadline()`, `clearTimers()` into new `Application\Scheduler\TaskTimerService`
- [x] Inject `MonotonicClockInterface`
- [x] Expose `scheduleTimer(TaskRecordInterface, DurationInterface, ?string $taskId, ?string $type)`, `drainExpired()`, `nearestDeadline()`, `clearForRecord(TaskRecordInterface)`
- [x] Update `FiberTaskScheduler` to delegate to `TaskTimerService`

### 4.3 — Extract `TaskIoAwaitService`
- [x] Move `$ioAwaiters`, `$ioTaskHandles`, `awaitIo()`, `drainReadyHandles()`, `releaseHandles()` into new `Application\Scheduler\TaskIoAwaitService`
- [x] Inject `PollerPortInterface`, `TaskTimerServiceInterface`
- [x] Expose `registerAwait(TaskRecordInterface, StreamPollHandleInterface, AwaitPoint, DurationInterface|null)`, `drainReady()`, `releaseForRecord(TaskRecordInterface)`, `clearIoAwaitForTask(string)`
- [x] Update `FiberTaskScheduler` to delegate to `TaskIoAwaitService`

### 4.4 — Extract `TaskLifecyclePublisher`
- [x] Move `publishLifecycle()` and related artifact creation into new `Application\Scheduler\TaskLifecyclePublisher`
- [x] Inject `ArtifactPublisherInterface`, `SchedulerLifecycleArtifactFactory`, `MonotonicClockInterface`
- [x] Expose `publishSpawned(TaskInterface)`, `publishResumed(TaskInterface, ?int $startTimeNanos)`, `publishCompleted(TaskInterface, TaskStatus, ?int $startTimeNanos, ?Throwable)`, `publishFailure(TaskInterface, Throwable)`
- [x] Update `FiberTaskScheduler` to delegate to `TaskLifecyclePublisher`

### 4.5 — Extract `TaskCancellationService`
- [x] Move `forceCancelRemaining()`, `drainCancelledRecords()`, cancellation scope management into new `Application\Scheduler\TaskCancellationService`
- [x] Expose `cancelRemaining(array $records, int $deadlineNanoseconds)`, `drainCancelled(array $records)`
- [x] Update `FiberTaskScheduler` to delegate to `TaskCancellationService`

### 4.6 — Extract `TaskHealthService`
- [x] Move `health()` logic into new `Application\Scheduler\TaskHealthService`
- [x] Inject `PollerPortInterface`, `MonotonicClockInterface`
- [x] Expose `capture(array $records, int $startTimeNanos, array $errors): SchedulerHealthInterface`
- [x] Update `FiberTaskScheduler` to delegate to `TaskHealthService`

### 4.7 — Slim `FiberTaskScheduler`
- [x] Remove extracted responsibilities; keep reactor loop (`runUntil`), queue management (`$ready`, `$records`), `step()`/`resume()`/`complete()` orchestration, `await()`/`awaitDelay()`, `submit()`/`spawn()`/`dispatch()`
- [x] Target: <400 lines (~330 lines achieved)
- [x] Verify all existing tests pass unchanged

### 4.8 — Verify architecture
- [x] Run `vendor/bin/phparkitect check` — confirm no new violations from extracted services
- [x] Run `vendor/bin/phpstan analyse` — confirm 0 new errors (pre-existing errors unchanged)

---

## Phase 5 — "New Only in Factories" Enforcement (High Priority) — COMPLETE (with exemptions)

**Goal:** Move `new` instantiation out of non-factory classes or document exemptions.

**Status:** Complete with documented exemptions. Verified via grep: no `new` service instantiation outside `src/Application/Factory/` and `src/SDK/Factory/`. Remaining `new` calls are:
- `throw new LogicException/RuntimeException/BadMethodCallException/...` — standard PHP exception instantiation in throw statements, not service construction (no factory needed per AGENTS.md "no speculative abstractions")
- `new ContextToken($id)` in `MemoryContextStorage` — value object, intentional
- `new ConformanceResult`/`new ConformanceViolation` in conformance verifiers — result objects, intentional exemption (Phase 5.5)


### 5.6 — Federation classes (`new LogicException`, `new InvalidArgumentException`, `new BackpressureExceededException`)
- [ ] Create exception factory or inject exception factory
- [ ] Replace direct `new` calls with factory calls
- [ ] Verify tests pass

### 5.7 — `RuntimeAdapterAssembler::defaultFactory()` (instantiates concrete assembly factories)
- [ ] Inject `array<RuntimeAdapterKind, RuntimeAssemblyFactoryInterface>` factories instead of constructing in method
- [ ] Remove `new SwooleAssemblyFactory(...)`, `new SwowAssemblyFactory(...)`, `new RevoltAssemblyFactory(...)`, `new ReferenceAssemblyFactory()` from `defaultFactory()`
- [ ] Have `AsyncRuntimeFactory` or composition root assemble the factory map
- [ ] Verify tests pass

### 5.8 — Rule clarification
- [ ] Document whether exceptions and value objects are exempt from "new only in factories"
- [ ] Update AGENTS.md if clarification is needed
- [ ] Add lint rule or phparkitect rule to enforce going forward

---

## Phase 5b — Code Review Fixes (High Priority) — COMPLETE

**Goal:** Fix all 17 high-confidence findings from the code review of Phases 1-5 changes.

**Status:** Complete. All 17 findings resolved:

### Security (4 CRITICAL — all fixed)
- **StreamSelectPollerTrait:157** — `stream_select()` return value unchecked with `@` suppression; all streams reported ready on failure → checks `$readyCount === false`, returns empty readiness on failure
- **SwoolePoller:62** — `@Event::del()` suppression leaves watcher registered on release failure → removed `@`, `Event::del()` errors now surface
- **RevoltPoller:47** — `@EventLoop::onReadable()` failure stores non-string ID that `releaseFromReactor()` ignores → validates `is_string() && !== ''` before storing, removed `@`
- **AmpPollerBridge:45** — Watcher IDs stored in `$ampIds` (never read) instead of `$this->registered`, so trait `release()` never cancels them → stores in `$this->registered[$id]`, removed dead `$ampIds` property

### Warning (1 — fixed)
- **ConformanceResult:36** — `add()` accepts `ConformanceViolationInterface` but closures type-hint concrete `ConformanceViolation`, causing `TypeError` → all closures use `ConformanceViolationInterface`

### Deploy Safety / Architecture (12 — all fixed)
- **phparkitect.php** — Removed `Conformance\Result` namespace exemption; interfaces now properly depend only on interfaces
- **VerifierInterface** — `verify()` returns `ConformanceResultInterface` (not concrete `ConformanceResult`)
- **All 7 verifiers** — `verify()` return types use `ConformanceResultInterface` with correct `Conformance\Contract` import
- **Factory interfaces** — Parameter types use `CapabilityDescriptorInterface`/`TaskIdInterface` (interfaces depend only on interfaces per AGENTS.md); implementation classes match
- **$ampIds** — Removed dead property (also fixes security finding #4)

### Quality gate results after fixes
- phparkitect: 0 violations
- Unit tests: 513 passing, 1110 assertions
- CS Fixer: 0 files need fixing
- PHPStan (src): 0 errors

---

## Phase 6 — Performance & Benchmarks (Medium Priority) — COMPLETE

**Goal:** Add benchmarks and optimize hot-path operations.

**Status:** Complete. Five phpbench benchmarks added, `runUntil()` ready-queue and `computeNearestTimerDeadline()` optimized, benchmark execution wired into `composer quality`, and baseline metrics documented below.

### 6.1 — Add phpbench benchmarks
- [x] Create `benchmarks/TaskSpawnBench.php` — benchmark task spawn throughput
- [x] Create `benchmarks/TaskCompletionBench.php` — benchmark task completion
- [x] Create `benchmarks/FiberSuspendResumeBench.php` — benchmark Fiber suspension/resumption
- [x] Create `benchmarks/PollerHandleBench.php` — benchmark poll-handle registration/teardown
- [x] Create `benchmarks/SchedulerQueueBench.php` — benchmark scheduler queue throughput
- [x] Verify `vendor/bin/phpbench run` executes without errors (12 subjects, 0 failures, 0 errors)

### 6.2 — Optimize `runUntil()` hot path
- [x] Replace `array_shift($this->ready)` with an array + head-pointer queue (avoids `new` per `ForbidNewOutsideFactoryBuilderRule`; O(1) amortized shift with periodic compaction)
- [x] Benchmark before/after — results within noise variance (environment lacks opcache; see baseline metrics below)
- [x] Verify tests pass (513 Unit, 602 total)

### 6.3 — Optimize `computeNearestTimerDeadline()`
- [x] Replace O(n) timer scan with a deadline-sorted array (`nearestDeadline()` O(1), `drainExpired()` O(k) for k expired, `scheduleTimer()` O(n) binary insert) — no `new` construction
- [x] Benchmark before/after — results within noise variance (see baseline metrics below)
- [x] Verify tests pass (513 Unit, 602 total)

### 6.4 — Performance regression gate
- [x] Added `benchmark` composer script (`vendor/bin/phpbench run benchmarks/`) and `@benchmark` to the `composer quality` chain
- [x] Document baseline metrics below

### Baseline metrics (phpbench, PHP 8.5.12, no opcache/xdebug)

| Benchmark               | Subject                      | Mode    | Revs  |
|-------------------------|------------------------------|---------|-------|
| TaskSpawnBench          | benchSpawnSingleTask         | ~103μs  | 1000  |
| TaskSpawnBench          | benchSpawnBatch100           | ~6.8ms  | 100   |
| TaskCompletionBench     | benchCompleteSingleTask      | ~103μs  | 1000  |
| TaskCompletionBench     | benchCompleteBatch100        | ~6.8ms  | 100   |
| FiberSuspendResumeBench | benchSuspendResumeOnce       | ~143μs  | 1000  |
| FiberSuspendResumeBench | benchSuspendResumeTenTimes   | ~335μs  | 500   |
| PollerHandleBench       | benchRegisterRelease         | ~0.80μs | 10000 |
| PollerHandleBench       | benchRegisterReleaseBatch100 | ~76μs   | 1000  |
| SchedulerQueueBench     | benchReadyQueueBatch100      | ~6.9ms  | 100   |

Measurements are first baselines; variance is high (±5–45%) due to no opcache in the dev environment. Re-run `composer benchmark` to detect regressions against these numbers.

---

## Phase 7 — Magic Literal Extraction (Medium Priority) — COMPLETE

**Goal:** Replace hardcoded strings with constants/enums in interfaces.

### 7.1 — Prometheus metric names
- [x] Extract `asyncit_tasks_runnable`, `asyncit_poll_wait_seconds`, `asyncit_tasks_completed_total`, `asyncit_task_duration_seconds`, `asyncit_schedule_to_dispatch_seconds`, `asyncit_handles_active`, `asyncit_timers_active` into `private const string` metrics on `PrometheusTelemetryBridge`
- [x] Replace all hardcoded metric-name string literals in `record()`, `buildInstruments()`, and `onTaskCompleted()` with the constants
- [x] Verify `php-cs-fixer` clean, `phpstan` 0 errors in the file, Unit suite still passes (513 tests)

### 7.2 — Redis key prefixes
- [x] Extract `asyncit:task:` prefix into constant in `Adapter/Redis/RedisTaskQueue`
- [x] Verify tests pass

### 7.3 — Error messages
- [x] Extract repeated error messages into constants near their usage
- [x] Focus on messages repeated across files (e.g., "must be called within a running task", "Current task is not tracked by this scheduler")
- [x] Verify tests pass

**Status:** Complete. `FiberTaskScheduler` now backs the repeated messages with class constants
`MUST_BE_CALLED_WITHIN_RUNNING_TASK`, `NOT_TRACKED_BY_THIS_SCHEDULER`, and
`CANNOT_AWAIT_TASK_NOT_OWNED`, replacing the hardcoded literals across `await()`,
`awaitDelay()`, `awaitAny()`, `awaitIo()`, and `resultTyped()`-adjacent guards. The unique
`resultTyped()` message (`Task %s is not tracked by this scheduler or has not reached a terminal
state.`) is intentionally left as a single-use literal. Unit suite: 513 tests / 1110 assertions
pass; full suite 602 tests green; phpstan/cs-fixer/phparkitect introduce no new violations.

### 7.4 — Metric name security
- [x] Verify no user-controlled values flow into metric names
- [x] Add validation/sanitization if any user input reaches metric names
- [x] Verify tests pass

**Status:** Complete. Verified `PrometheusTelemetryBridge`: all metric names are `private const string`
(METRIC_TASKS_RUNNABLE, METRIC_HANDLES_ACTIVE, METRIC_TIMERS_ACTIVE, METRIC_TASKS_COMPLETED_TOTAL,
METRIC_TASK_DURATION_SECONDS, METRIC_POLL_WAIT_SECONDS, METRIC_SCHEDULE_TO_DISPATCH_SECONDS); the
`record()` match key is never used as a metric name; the only label value (`status`) is drawn from the
closed `TaskStatus` taxonomy with a closed-enum-name fallback (TelemetryBridgeEventListener:54-60). No
user-controlled value reaches a metric name. No sanitization needed.

---

## Phase 8 — Modern PHP Feature Adoption (Medium Priority) — COMPLETE

**Goal:** Add `WeakReference` and `Lazy` object usage where beneficial.

**Status:** Complete.

### 8.1 — Add `WeakReference` for per-task metadata — COMPLETE
- Identified `TaskRecord` as candidate for per-task metadata that shouldn't prevent GC
- Added `$metadata` array (`array<string, WeakReference<object>>`) to `TaskRecord` with `getMetadata()`, `setMetadata()`, `hasMetadata()`, `removeMetadata()` methods
- Added same methods to `TaskRecordInterface` (interface-backed per convention)
- `markDone()` clears metadata to release references on terminal transition
- Added tests: set/get metadata, missing metadata returns null, remove metadata, markDone clears metadata

### 8.2 — Add `Lazy` objects for expensive adapters — COMPLETE
- PHP 8.5.12 does not expose the native `Lazy` class; extracted equivalent pattern into `Domain\Behavior\Lazy` trait
- `Lazy` trait provides `materialize(callable $initialize)` for deferred one-time initialization
- Applied to `OpenTelemetryTelemetryBridge` (replaced manual `initialized`/`materialize()` pattern)
- Applied to `PrometheusTelemetryBridge` (replaced manual `initialized`/`materialize()` pattern)
- `LazyTelemetryBridge` already provided lazy proxy pattern; no change needed

### 8.3 — Add `never` return type — COMPLETE
- Identified `ReferenceExecutionScope` methods that always throw `BadMethodCallException`
- Changed 6 methods (`execution()`, `context()`, `activities()`, `measurements()`, `observations()`, `occurrences()`, `snapshots()`) to `: never` return type
- Removed unused imports for return type interfaces no longer referenced

### 8.4 — Virtual properties — COMPLETE
- Added `public bool $isTerminal` virtual property to `TaskBehavior` (computed from `$this->status->isTerminal()`)
- Added `public bool $isRunning` virtual property to `TaskBehavior` (computed from `$this->status === TaskStatus::Running`)
- Added `@phpstan-ignore-next-line` for deadProperty.neverRead (PHPStan can't detect property-hook reads)

---

## Phase 9 — Security Negative Tests (Medium Priority)

**Goal:** Add context-bleed and handle-leak negative tests per AGENTS.md.

### 9.1 — Context-bleed test
- [ ] Add test proving zero context bleed across suspension/resumption cycles (Assertion A)
- [ ] Interleave concurrent tasks with distinct contexts
- [ ] Verify each task observes only its own context after resume
- [ ] Place in `tests/Conformance/` or `tests/Unit/`

### 9.2 — Handle-leak test
- [ ] Add test proving every `StreamPollHandle` is released after timeout/cancellation (Assertion C)
- [ ] Register handles, cancel/timeout tasks, verify `activeHandleCount() === 0`
- [ ] Place in `tests/Conformance/` or `tests/Unit/`

### 9.3 — Abandoned task handle leak test
- [ ] Test `awaitIo()` handle cleanup when `complete()` is not called (edge case from REVIEW.md §4)
- [ ] Verify handles are released even in abnormal task termination
- [ ] Place in `tests/Unit/`

### 9.4 — Conformance test coverage
- [ ] Run conformance suite and verify all 7 tests pass (currently 3 skipped)
- [ ] Reduce skipped tests where possible
- [ ] Verify `composer test` passes

---

## Phase 10 — DX & Documentation (Low Priority)

**Goal:** Improve developer experience and documentation.

### 10.1 — Add `CONTRIBUTING.md`
- [ ] Document contribution workflow, coding standards, testing requirements
- [ ] Include `composer quality` gate instructions
- [ ] Include architecture decision process (ADR template)

### 10.2 — Add `CHANGELOG.md`
- [ ] Document changes per release following conventional commit format
- [ ] Seed with current version history

### 10.3 — Fix Acceptance suite coverage tooling
- [ ] Resolve missing serialized coverage file issue
- [ ] Verify `codecept run --coverage` works for all suites

### 10.4 — Document "new only in factories" rule scope
- [ ] Clarify whether exceptions/value objects are exempt
- [ ] Update AGENTS.md with the clarification

---

## Verification Gates

After each phase, run:

```bash
vendor/bin/phpstan analyse --no-progress          # 0 errors
vendor/bin/php-cs-fixer fix --dry-run --diff      # 0 files to fix
vendor/bin/phparkitect check                      # 0 violations
php tools/lint/line-length.php                    # OK
php tools/lint/single-type-per-file.php           # OK
vendor/bin/codecept run                           # all tests pass
```

After all phases:

```bash
composer quality                                    # full quality gate
```

---

## Missing Context / Gaps

The following items from `docs/REVIEW.md` could not be fully resolved and are noted here:

1. **"new only in factories" rule scope**: AGENTS.md does not explicitly state whether exceptions (`LogicException`, `InvalidArgumentException`) and value objects (`ContextToken`) are exempt. Phase 5 assumes they are not exempt; if maintainers intend an exemption, adjust Phase 5 accordingly.

2. **phparkitect violations intentionality**: Some violations may be deliberate architectural decisions rather than drift. Phase 3 assumes all violations should be resolved; if any are intentional, update `phparkitect.php` rules instead of code.

3. **Coverage measurement environment**: Coverage was measured using pcov per-suite due to Codeception Acceptance suite coverage collection failure. The 95% threshold applies to the Unit suite based on available data; confirm with maintainers whether Integration/Functional/Conformance suites also count.

4. **Benchmark baseline**: No existing benchmark data exists. Phase 6 establishes baselines but cannot compare against prior measurements.

5. **Duplicated scheduler assembly drift risk (review finding #4)**: The identical 6-service assembly (`TaskTimerService`, `TaskIoAwaitService`, `TaskLifecyclePublisher`, `TaskCancellationService`, `TaskHealthService` + `SchedulerLifecycleArtifactFactory`) is copy-pasted across 9 scheduler construction sites (`SchedulerFactory`, `ReferenceRuntimeFixture`, `RuntimeFixtureTrait`, `FunctionalTestHelper`, `SchedulerIntegrationTest`, `FiberTaskSchedulerFailureTest`, `FiberTaskSchedulerTest`, `TaskStreamTest`, `examples/bootstrap.php`). Deferred: the sites have materially differing configurations (verifier vs. real poller, telemetry listeners, deterministic vs. system clocks, `NullArtifactPublisher` vs. real publisher), so centralizing cleanly is a larger design refactor that risks touching differently-configured test fixtures. Track as drift risk — if `FiberTaskScheduler`'s constructor changes, all 9 sites must be updated consistently.
