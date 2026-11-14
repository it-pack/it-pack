# Changelog

All notable changes to `it-pack/async-it` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(0.x: breaking changes may occur between minor releases).

## [Unreleased]

### Changed

- `CombinatorInterface` now declares its awaited result as a covariant PHPDoc
  type parameter. Strict static-analysis consumers implementing or annotating a
  combinator should supply its result type (for example,
  `@implements CombinatorInterface<list<mixed>>`); custom factories must declare
  the result types required by `CombinatorFactoryInterface`. PHP signatures and
  runtime behavior are unchanged.
- Coroutine context storage tracks issued tokens by weak object keys instead of
  scanning weak references on every attachment, preserving identity and LIFO.
- The factory-construction PHPStan rule permits direct construction of PHP
  internal classes, including extension classes. Existing enforcement for
  userland project and third-party classes and its other exemptions remain.

### Added

- **`CONTRIBUTING.md`** — contribution workflow, coding standards (PHP-CS-Fixer, Rector,
  PHPStan max level, phparkitect), testing requirements, `composer quality` gate, and the
  architecture decision record (ADR) process (Phase 10.1).
- **`CHANGELOG.md`** — this changelog, seeded with the completed refactoring-plan history
  (Phase 10.2).
- **Acceptance suite coverage fix** — removed the empty, non-functional Acceptance (PhpBrowser)
  suite that broke `codecept run --coverage` via a missing `codecoverage.serialized` file; all
  remaining suites now collect coverage (Phase 10.3).
- **"new only in factories" rule scope** — clarified that exception instantiation in `throw`
  statements and value-object/result-object construction are intentional exemptions; documented
  in `AGENTS.md` (Phase 10.4).

## [1.0.0] - Refactoring plan complete (Phases 1–9)

### Added

- **Task lifecycle publication** — `TaskLifecyclePublisher` emits RunIt Occurrences/Measurements
  for task spawned, resumed, and completed events via `ArtifactPublisherInterface`
  (Phase 4.4).
- **Timer service** — `TaskTimerService` owns `$timers`, `drainExpiredTimers()`,
  `computeTimeout()`, `computeNearestTimerDeadline()`, `clearTimers()` backed by
  `MonotonicClockInterface` (Phase 4.2).
- **IO await service** — `TaskIoAwaitService` owns `$ioAwaiters`, `$ioTaskHandles`,
  `awaitIo()`, `drainReadyHandles()`, `releaseHandles()` backed by `PollerPortInterface`
  (Phase 4.3).
- **Cancellation service** — `TaskCancellationService` owns `forceCancelRemaining()`,
  `drainCancelledRecords()`, and cancellation-scope management (Phase 4.5).
- **Health service** — `TaskHealthService` captures `SchedulerHealthInterface` from records,
  start time, and errors (Phase 4.6).
- **Slimmed `FiberTaskScheduler`** — reduced from ~976 lines to ~330 lines, keeping reactor
  loop (`runUntil`), queue management, `step()`/`resume()`/`complete()` orchestration,
  `await()`/`awaitDelay()`, `submit()`/`spawn()`/`dispatch()` (Phase 4.7).
- **Interface backing** — `AsyncRuntimeInterface`, `TaskRecordInterface`,
  `ConformanceResultInterface`, `ConformanceViolationInterface`, `CanarySchedulerInterface`,
  `RateLimiterInterface`, `SchedulerConfigurationInterface`, `CircuitBreakerInterface`,
  `DeduplicationRecordInterface`, `CancellationTokenInterface`, `FiberScopeSlotInterface`,
  `SchedulerLifecycleArtifactInterface`, `RuntimeAssemblyInterface`, `ArtifactNormalizerInterface`,
  `DeterministicMonotonicClockInterface`, `SchedulerIntrospectionInterface`,
  `FederatedSchedulerInterface`, `GracefulShutdownInterface`,
  `SchedulerLifecycleArtifactFactoryInterface`; `VerifierInterface::verify()`,
  `TelemetryBridgeInterface::events()`, `RuntimeDiscoveryInterface::notifyChanged()`,
  `TaskGroupInterface::fail()`, `PersistentTaskInterface::isDeadlineExceeded()` (Phase 3).
- **Shared poller trait** — `StreamSelectPollerTrait` owns `register()`/`release()`,
  `await()`, `readyHandles()`, `activeHandleCount()`, `probeReadiness()`, `matchReadyHandles()`
  and `$handles`/`$registered`/`$counter` state; 7 consumers implement the abstract hooks
  (Phase 2).
- **Channel closed-state guard** — `ChannelBehaviorTrait` with `requireOpen()` shared by
  `SwooleChannel`/`SwowChannel`; dead `$ampIds` property removed from `AmpPollerBridge`
  (Phases 2.7, 5b).
- **AsyncRuntime combinator helper** — private `createCombinator()` backing `race()`/`all()`/
  `any()`/`select()`; `join()` delegates to `all()` (Phase 2.8).
- **Metadata on `TaskRecord`** — `$metadata` (`array<string, WeakReference<object>>`) with
  `getMetadata()`/`setMetadata()`/`hasMetadata()`/`removeMetadata()`; cleared on `markDone()`
  (Phase 8.1).
- **`Lazy` trait** — `Domain\Behavior\Lazy` providing deferred one-time `materialize()`;
  applied to `OpenTelemetryTelemetryBridge` and `PrometheusTelemetryBridge` (Phase 8.2).
- **`: never` return types** — `ReferenceExecutionScope` methods that always throw now return
  `: never` (Phase 8.3).
- **Virtual properties** — `TaskBehavior::$isTerminal` and `$isRunning` computed from status
  (Phase 8.4).
- **Benchmarks** — `benchmarks/TaskSpawnBench.php`, `TaskCompletionBench.php`,
  `FiberSuspendResumeBench.php`, `PollerHandleBench.php`, `SchedulerQueueBench.php`; wired into
  `composer quality` via `composer benchmark` (Phase 6).
- **Hot-path optimizations** — `runUntil()` ready queue uses an array + head pointer
  (O(1) amortized shift); `computeNearestTimerDeadline()` uses a deadline-sorted array
  (O(1) nearest, O(k) drain) (Phases 6.2–6.3).
- **Magic literal extraction** — `FiberTaskScheduler` class constants
  `MUST_BE_CALLED_WITHIN_RUNNING_TASK`, `NOT_TRACKED_BY_THIS_SCHEDULER`,
  `CANNOT_AWAIT_TASK_NOT_OWNED`; Prometheus metric names as `private const string`; Redis key
  prefix constant in `RedisTaskQueue` (Phase 7).
- **Security negative tests** — `ContextBleedTest` (context isolation across interleaved
  suspend/resume) and `HandleLeakTest` (handles released after IO timeout, external
  cancellation, never-completing task, idle scheduler) (Phase 9).

### Fixed

- **`stream_select()` return value** — `StreamSelectPollerTrait` checks `$readyCount === false`
  and returns empty readiness on failure instead of `@`-suppressing and reporting all streams
  ready (Phase 5b security).
- **`@Event::del()` suppression** — removed from `SwoolePoller`; release errors now surface
  (Phase 5b security).
- **`@EventLoop::onReadable()` failure** — `RevoltPoller` validates the returned ID is a
  non-empty string before storing (Phase 5b security).
- **`ConformanceResult::add()`** — closures now type-hint `ConformanceViolationInterface`
  instead of concrete `ConformanceViolation`, preventing `TypeError` (Phase 5b warning).
- **phparkitect exemptions** — removed `Conformance\Result` namespace exemption; interfaces now
  depend only on interfaces; `VerifierInterface::verify()` returns
  `ConformanceResultInterface`; all 7 verifiers use `Conformance\Contract` imports
  (Phase 5b).
- **Factory parameter types** — use `CapabilityDescriptorInterface`/`TaskIdInterface`
  (interfaces depend only on interfaces) (Phase 5b).

### Changed

- **`FiberTaskScheduler`** — split into SRP-respecting services (`TaskTimerService`,
  `TaskIoAwaitService`, `TaskLifecyclePublisher`, `TaskCancellationService`, `TaskHealthService`)
  (Phase 4).
- **Poller adapters** — `SelectPoller`, `NativePoller`, `RevoltPoller`, `SwoolePoller`,
  `SwowPoller`, `AmpPollerBridge`, `ReactPhpPollerBridge` now delegate `register()`/`release()/
  await()`/`readyHandles()` to `StreamSelectPollerTrait` (Phase 2).
- **`RuntimeAssemblerInterface`** — depends on `AsyncRuntimeInterface` instead of concrete
  `AsyncRuntime` (Phase 3.7).

### Removed

- **Dead `$ampIds` property** from `AmpPollerBridge` (Phase 5b).
- **phparkitect `Conformance\Result` namespace exemption** (Phase 5b).

### Security

- Context-bleed and handle-leak negative tests added; conformance Assertion C (deterministic
  cleanup) verified across available adapters (Phase 9).
- No user-controlled value reaches a metric name (Phase 7.4).
