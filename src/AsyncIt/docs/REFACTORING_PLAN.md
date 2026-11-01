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
- PHPStan at max level, phparkitect architecture rules, PHP-CS-Fixer clean.

---

## 0. Checklist (conceptual)

- [ ] Raise Unit suite coverage from ~79% to ≥95%
- [ ] Deduplicate poller adapters (~240 lines) via shared `register()`/`release()` trait
- [ ] Fix 87 phparkitect violations across 42 classes
- [ ] Split `FiberTaskScheduler` (976 lines) into SRP-respecting services
- [ ] Enforce "new class instances only in factories/builders"
- [ ] Add benchmarks for hot-path operations
- [ ] Extract magic literals to constants/enums in interfaces
- [ ] Add `WeakReference`/`Lazy` usage where appropriate
- [ ] Add negative security tests (context-bleed, handle-leak)
- [ ] Add `CONTRIBUTING.md` and `CHANGELOG.md`

---

## Phase 1 — Testing & Coverage (High Priority)

**Goal:** Raise Unit suite coverage from ~79% to ≥95%. Add tests for under-covered adapters and factories.

### 1.1 — Acceptance suite coverage collection
- [ ] Fix `codecept run --coverage` failure on Acceptance suite (missing serialized coverage file at `tests/_output/c3tmp/codecoverage.serialized`)
- [ ] Verify per-suite coverage collection works for all suites (Unit, Integration, Functional, Conformance, Concurrency)
- [ ] Confirm coverage does not leak between suites

### 1.2 — OpenTelemetryTelemetryBridge (2% lines / 11% methods)
- [ ] Add unit tests for `OpenTelemetryTelemetryBridge` in `tests/Unit/Adapter/OpenTelemetry/`
- [ ] Cover event recording (spawned, suspended, resumed, completed)
- [ ] Cover telemetry span parentage and attribute injection
- [ ] Cover no-op behavior when OTEL not configured

### 1.3 — Poller adapters (33–62% lines)
- [ ] Add unit tests for `RevoltPoller`, `SwoolePoller`, `SwowPoller` in `tests/Unit/Adapter/`
- [ ] Cover `register()`/`release()` handle lifecycle
- [ ] Cover `await()` readiness probe and `readyHandles()`
- [ ] Cover deterministic cleanup (Assertion C) — no dangling handles

### 1.4 — Factories (50–67% lines/methods)
- [ ] Add unit tests for `AsyncRuntimeFactory` in `tests/Unit/Application/Factory/`
- [ ] Add unit tests for `PollerFactory` in `tests/Unit/Application/Factory/`
- [ ] Cover capability negotiation and adapter selection

### 1.5 — Coverage gate
- [ ] Run `vendor/bin/codecept run Unit --coverage` and verify ≥95% lines and methods
- [ ] Run full `composer test` and confirm all 581+ tests still pass
- [ ] Document coverage threshold in `codeception.yml` or CI config

---

## Phase 2 — Duplicate Code Deduplication (High Priority)

**Goal:** Eliminate ~240 lines of near-identical code across poller adapters.

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
- [ ] Extract `if ($this->closed) throw new ChannelClosedException();` into `ChannelBehaviorTrait` (shared by `SwooleChannel`, `SwowChannel`)
- [ ] Verify `RevoltChannel` delegates correctly via `EmulatedChannel` (already done, no change needed)
- [ ] Verify channel tests pass

### 2.8 — Deduplicate `AsyncRuntime` combinator factory methods
- [ ] Extract shared pattern from `race()`, `all()`, `any()`, `select()` into a private helper `createCombinator(array $operations, callable $factory): mixed`
- [ ] Keep `join()` delegating to `all()`
- [ ] Verify tests pass

---

## Phase 3 — Architecture Violations (High Priority)

**Goal:** Fix 87 phparkitect violations across 42 classes.

### 3.1 — `SDK\AsyncRuntime` (12 violations: methods not backed by interfaces)
- [ ] Create `AsyncRuntimeInterface` in `src/SDK/` backing all public methods: `channel()`, `telemetry()`, `run()`, `spawn()`, `await()`, `awaitDelay()`, `race()`, `all()`, `any()`, `select()`, `join()`, `stream()`, `capability()`, `health()`, `onCapabilitiesChanged()`, `discover()`
- [ ] Have `AsyncRuntime` implement `AsyncRuntimeInterface`
- [ ] Verify `composer architecture` violations for this class resolved

### 3.2 — `Application\Scheduler\TaskRecord` (7 violations)
- [ ] Create `TaskRecordInterface` in `src/Contract/` or `src/Application/Scheduler/`
- [ ] Back all non-`__` methods: `task()`, `fiber()`, `handles()`, `addHandle()`, `clearHandles()`, `markDone()`, `isDone()`
- [ ] Have `TaskRecord` implement `TaskRecordInterface`
- [ ] Verify `composer architecture` violations for this class resolved

### 3.3 — `Conformance\Result\ConformanceResult` (5 violations)
- [ ] Create `ConformanceResultInterface` in `src/Conformance/Contract/`
- [ ] Back all non-`__` methods
- [ ] Have `ConformanceResult` implement `ConformanceResultInterface`
- [ ] Verify `composer architecture` violations resolved

### 3.4 — `Contract\TaskChannelInterface` (5 violations)
- [ ] Review interface methods vs. what implementations expose
- [ ] Either add missing methods to interface or create sub-interfaces
- [ ] Verify `composer architecture` violations resolved

### 3.5 — `Application\Federation\CanaryScheduler` (4 violations)
- [ ] Create `CanarySchedulerInterface` in `src/Application/Federation/`
- [ ] Back all non-`__` methods
- [ ] Have `CanaryScheduler` implement `CanarySchedulerInterface`
- [ ] Verify `composer architecture` violations resolved

### 3.6 — `Application\Scheduler\FiberTaskScheduler` (4 violations)
- [ ] Review which public methods lack interface backing
- [ ] Add missing methods to existing interfaces (`EngineSchedulerInterface`, `ResultTypedSchedulerInterface`, `AwaitableIOInterface`, `DeadlineSchedulerInterface`) or create a focused sub-interface
- [ ] Verify `composer architecture` violations resolved

### 3.7 — `SDK\Factory\RuntimeAssemblerInterface` (depends on class `AsyncRuntime`)
- [ ] Change dependency from `AsyncRuntime` (concrete class) to `AsyncRuntimeInterface` (interface)
- [ ] Verify `composer architecture` violations resolved

### 3.8 — Domain classes with unbacked methods
- [ ] `Domain\PersistentTask`: create interface or document intentional exemption
- [ ] `Domain\RateLimiter`: create `RateLimiterInterface` backing `consume`, `tryConsume`, `waitForPermit`
- [ ] `Domain\SchedulerConfiguration`: create `SchedulerConfigurationInterface` backing `enableDebug`, `withChanges`
- [ ] `Domain\TaskGroup`: create `TaskGroupInterface` backing `fail`
- [ ] Verify `composer architecture` violations resolved

### 3.9 — Remaining 42-class violation sweep
- [ ] Run `vendor/bin/phparkitect check` and enumerate remaining violations
- [ ] Fix each by adding interface backing or adjusting interface dependencies
- [ ] Target: 0 violations from `composer architecture`

---

## Phase 4 — `FiberTaskScheduler` SRP Split (High Priority)

**Goal:** Split `FiberTaskScheduler.php` (976 lines) into SRP-respecting services.

### 4.1 — Identify responsibilities
- [ ] Document current responsibilities: reactor loop (`runUntil`), scheduling/queue, timer management, IO await handling, cancellation handling, lifecycle publication, health reporting
- [ ] Define boundaries for extracted services

### 4.2 — Extract `TaskTimerService`
- [ ] Move `$timers` array, `drainExpiredTimers()`, `computeTimeout()`, `computeNearestTimerDeadline()`, `clearTimers()` into new `Application\Scheduler\TaskTimerService`
- [ ] Inject `MonotonicClockInterface`
- [ ] Expose `scheduleTimer(TaskRecord, DurationInterface, ?string $taskId, string $type = null)`, `drainExpired()`, `nearestDeadline()`, `clearForRecord(TaskRecord)`
- [ ] Update `FiberTaskScheduler` to delegate to `TaskTimerService`

### 4.3 — Extract `TaskIoAwaitService`
- [ ] Move `$ioAwaiters`, `$ioTaskHandles`, `awaitIo()`, `drainReadyHandles()`, `releaseHandles()` into new `Application\Scheduler\TaskIoAwaitService`
- [ ] Inject `PollerPortInterface`
- [ ] Expose `registerAwait(TaskRecord, StreamPollHandleInterface, AwaitPoint, DurationInterface|null)`, `drainReady()`, `releaseForRecord(TaskRecord)`
- [ ] Update `FiberTaskScheduler` to delegate to `TaskIoAwaitService`

### 4.4 — Extract `TaskLifecyclePublisher`
- [ ] Move `publishLifecycle()` and related artifact creation into new `Application\Scheduler\TaskLifecyclePublisher`
- [ ] Inject `ArtifactPublisherInterface`, `SchedulerLifecycleArtifactFactory`, `MonotonicClockInterface`
- [ ] Expose `publishSpawned(TaskInterface)`, `publishResumed(TaskInterface, int $startTime)`, `publishCompleted(TaskInterface, TaskStatus, ?int $startTime, ?Throwable)`, `publishFailure(TaskInterface, Throwable)`
- [ ] Update `FiberTaskScheduler` to delegate to `TaskLifecyclePublisher`

### 4.5 — Extract `TaskCancellationService`
- [ ] Move `forceCancelRemaining()`, `drainCancelledRecords()`, cancellation scope management into new `Application\Scheduler\TaskCancellationService`
- [ ] Inject `CancellationScopeFactoryInterface`
- [ ] Expose `cancelRemaining(array $records, int $deadlineNanos)`, `drainCancelled(array $records)`
- [ ] Update `FiberTaskScheduler` to delegate to `TaskCancellationService`

### 4.6 — Extract `TaskHealthService`
- [ ] Move `health()` logic into new `Application\Scheduler\TaskHealthService`
- [ ] Inject `PollerPortInterface`, `MonotonicClockInterface`
- [ ] Expose `capture(array $records, int $startTimeNanos, array $errors): SchedulerHealthInterface`
- [ ] Update `FiberTaskScheduler` to delegate to `TaskHealthService`

### 4.7 — Slim `FiberTaskScheduler`
- [ ] Remove extracted responsibilities; keep reactor loop (`runUntil`), queue management (`$ready`, `$records`), `step()`/`resume()`/`complete()` orchestration, `await()`/`awaitDelay()`, `submit()`/`spawn()`/`dispatch()`
- [ ] Target: <400 lines
- [ ] Verify all existing tests pass unchanged

### 4.8 — Verify architecture
- [ ] Run `vendor/bin/phparkitect check` — confirm no new violations from extracted services
- [ ] Run `vendor/bin/phpstan analyse` — confirm 0 errors

---

## Phase 5 — "New Only in Factories" Enforcement (High Priority)

**Goal:** Move `new` instantiation out of non-factory classes or document exemptions.

### 5.1 — `FiberTaskScheduler.php` (11× `new LogicException`, `new RuntimeException`, `new SchedulerFailureArtifact`)
- [ ] Create `Application\Factory\SchedulerExceptionFactory` or inject exception factory
- [ ] Replace `new LogicException(...)` with factory-created exceptions
- [ ] Replace `new SchedulerFailureArtifact(...)` with factory-created artifact
- [ ] Replace `new RuntimeException(...)` with factory-created exception
- [ ] Verify tests pass

### 5.2 — `MemoryContextStorage.php` (`new ContextToken`)
- [ ] Inject `FiberScopeSlotFactoryInterface` or context token factory
- [ ] Replace `new ContextToken(...)` with factory call
- [ ] Verify tests pass

### 5.3 — `ReferenceExecutionScope.php` (7× `new BadMethodCallException`)
- [ ] Create exception factory or inject exception factory
- [ ] Replace `new BadMethodCallException(...)` with factory call
- [ ] Verify tests pass

### 5.4 — Domain classes (`new InvalidArgumentException`, `new DomainException`, `new RateLimitExceededException`)
- [ ] Create `Domain\Factory\DomainExceptionFactory` or inject per-class exception factories
- [ ] Replace direct `new` calls with factory calls
- [ ] Verify tests pass

### 5.5 — Conformance verifiers (`new ConformanceResult`, `new ConformanceViolation`)
- [ ] These are result objects, not services — document as intentional exemption OR create `Conformance\Factory\ConformanceResultFactory`
- [ ] Decide based on AGENTS.md interpretation (value objects vs services)

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

## Phase 6 — Performance & Benchmarks (Medium Priority)

**Goal:** Add benchmarks and optimize hot-path operations.

### 6.1 — Add phpbench benchmarks
- [ ] Create `benchmarks/TaskSpawnBench.php` — benchmark task spawn throughput
- [ ] Create `benchmarks/TaskCompletionBench.php` — benchmark task completion
- [ ] Create `benchmarks/FiberSuspendResumeBench.php` — benchmark Fiber suspension/resumption
- [ ] Create `benchmarks/PollerHandleBench.php` — benchmark poll-handle registration/teardown
- [ ] Create `benchmarks/SchedulerQueueBench.php` — benchmark scheduler queue throughput
- [ ] Verify `vendor/bin/phpbench run` executes without errors

### 6.2 — Optimize `runUntil()` hot path
- [ ] Replace `array_shift($this->ready)` with a more efficient queue (SplQueue or array with pointer)
- [ ] Benchmark before/after to quantify improvement
- [ ] Verify tests pass

### 6.3 — Optimize `computeNearestTimerDeadline()`
- [ ] Replace O(n) iteration with a sorted structure or min-heap
- [ ] Benchmark before/after
- [ ] Verify tests pass

### 6.4 — Performance regression gate
- [ ] Add benchmark execution to CI or `composer quality` script
- [ ] Document baseline metrics

---

## Phase 7 — Magic Literal Extraction (Medium Priority)

**Goal:** Replace hardcoded strings with constants/enums in interfaces.

### 7.1 — Prometheus metric names
- [ ] Extract `asyncit_tasks_runnable`, `asyncit_poll_wait_seconds`, `asyncit_tasks_completed_total`, `asyncit_task_duration_seconds`, `asyncit_schedule_to_dispatch_seconds`, `asyncit_handles_active`, `asyncit_timers_active` into constants
- [ ] Place in `Adapter/Prometheus/PrometheusTelemetryBridge` constants or a vocabulary interface
- [ ] Verify tests pass

### 7.2 — Redis key prefixes
- [ ] Extract `asyncit:task:` prefix into constant in `Adapter/Redis/RedisTaskQueue`
- [ ] Verify tests pass

### 7.3 — Error messages
- [ ] Extract repeated error messages into constants near their usage
- [ ] Focus on messages repeated across files (e.g., "must be called within a running task", "Current task is not tracked by this scheduler")
- [ ] Verify tests pass

### 7.4 — Metric name security
- [ ] Verify no user-controlled values flow into metric names
- [ ] Add validation/sanitization if any user input reaches metric names
- [ ] Verify tests pass

---

## Phase 8 — Modern PHP Feature Adoption (Medium Priority)

**Goal:** Add `WeakReference` and `Lazy` object usage where beneficial.

### 8.1 — Add `WeakReference` for per-task metadata
- [ ] Identify candidate locations (e.g., per-task cache, metadata that shouldn't prevent GC)
- [ ] Add `WeakReference` usage in `TaskRecord` or scheduler where appropriate
- [ ] Verify correctness — object lifetime must not affect correctness
- [ ] Verify tests pass

### 8.2 — Add `Lazy` objects for expensive adapters
- [ ] Identify expensive optional adapters (e.g., OpenTelemetry bridge, Prometheus bridge)
- [ ] Wrap in `Lazy` proxy where initialization can be deferred
- [ ] Verify tests pass

### 8.3 — Add `never` return type
- [ ] Identify methods that should never throw (e.g., pure getters, simple setters)
- [ ] Add `never` return type where appropriate
- [ ] Verify PHPStan passes

### 8.4 — Virtual properties
- [ ] Identify candidate computed projections (e.g., derived task properties)
- [ ] Add virtual properties where they improve clarity
- [ ] Verify tests pass

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