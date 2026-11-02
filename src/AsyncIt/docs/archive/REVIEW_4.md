# AsyncIt — In-Depth Review

**Scope:** `src/AsyncIt/` (the AsyncIt package, scheduling/progression tier of ItPack)
**Date:** 2026-11-01
**Reviewer:** Kilo

---

## Executive Summary

AsyncIt is a well-architected, capability-oriented scheduling engine with clean hexagonal layering, strong contract discipline, and excellent tooling (phpstan level max, phparkitect, lint rules). It implements the scheduling slot of RunIt's `ExecutionEngineInterface` with Fiber-safe context propagation, observed cooperative cancellation, and a capability-negotiation model.

**Overall assessment: Architecturally sound; significant deduplication opportunities; below-target test coverage; several violations of the project's own "instances only in factories" rule.**

---

## Checklist (What Will Be Reviewed)

1. **Features & architecture** — tiered stack, hexagonal layering, ports/adapters, capability negotiation, Fiber-safe context, cancellation semantics.
2. **Maintenance & maintainability** — static analysis, architecture lint, code organization, abstraction quality.
3. **Performance** — bounded buffers, monotonic clocks, lazy bridges, channel compaction, handle cleanup.
4. **Security** — context isolation, handle leakage, debug surface, no secrets.
5. **Developer experience (DX)** — README, naming, vocabulary interfaces, conformance verifiers, factory/builder ergonomics.
6. **SOLID & DRY** — interface-backed DI, no abstract classes, low complexity, no magic literals, deduplication.
7. **Modern PHP features** — Fibers, readonly, property hooks, enums, weak refs, lazy objects.
8. **Testing & coverage** — suite count, coverage %, leakage, gaps.
9. **Compliance with project code-quality rules** — else/elseif, Yoda style, imports, one-type-per-file, line length, instance-in-factories rule, declaration ordering.

---

## 1. Features & Overall Architecture

### Strengths

**Tiered semantic stack (Tier 4 = Scheduling).** AsyncIt correctly positions itself between RunIt (execution semantics) and the engine/poll layer (Tier 5). It consumes RunIt's `ExecutionEngineInterface`, `ContextStorageInterface`, and artifact publication port without redefining execution semantics. This is documented clearly in `README.md` and `docs/EXECUTION.md`.

**Hexagonal (ports & adapters) layering.** The directory structure enforces layers:
- `Contract/` — ports (interfaces only)
- `Domain/` — task lifecycle, cancellation, channels, rate limiting, circuit breaker
- `Application/` — scheduler, factories, pollers, health, federation
- `SDK/` — `AsyncRuntime` entry point, telemetry bridges
- `Adapter/` — Revolt, Swoole, Swow, ReactPHP, Amp, Redis, Prometheus, OpenTelemetry
- `Conformance/` — `AsyncRuntimeVerifier` and per-assertion verifiers

`phparkitect` passes with **0 violations**, confirming dependency direction is correct (Adapter → Domain/Port/Common, never Adapter → Application).

**Capability-first architecture.** Instead of `extension_loaded('swoole')`, AsyncIt queries functional capabilities (`CapabilityDescriptor`) once at the process boundary. Each capability is rated `Supported` / `Emulated` / `Adapter-Only` / `Unavailable`. This is the single source of truth for scheduling policy selection.

**Fiber-safe context propagation.** On suspension, the RunIt context is detached from the ambient Fiber scope; on resumption it is re-attached under `finally`. This is enforced in `FiberTaskScheduler::step()` via `ContextStorageInterface::attach()` / `detach()`, satisfying conformance Assertion A (context isolation).

**Observed cooperative cancellation.** Cancellation is an observed state change, never preemptive. `CancellationScope::throwIfCancelled()` is called at cooperation points. Terminal transitions are validated (`TaskStatus` enum matrix); conflicting transitions throw `ConflictingCompletionException`. Poll handles are released on terminal transition (Assertion C).

**Structured concurrency.** `TaskGroup`, `RaceGroup`, `AllGroup`, `AnyGroup`, `SelectGroup` implement `CombinatorInterface` / `TaskGroupInterface`. `TaskGroup::__destruct()` cancels pending children, preventing leaked tasks.

**Factories everywhere.** Construction is centralized in `Application/Factory/` (`SchedulerFactory`, `TaskFactory`, `TaskRecordFactory`, `TaskIdFactory`, `PollerFactory`, `CombinatorFactory`, `TaskGroupFactory`, `CancellationScopeFactory`, etc.). Every factory is `readonly` and injected.

**Vocabulary interfaces.** Constants live in interfaces (`TaskStatusVocabulary`, `PollerVocabulary`, `CancellationReasonVocabulary`, `CapabilityVocabulary`, `CircuitStateVocabulary`, `RuntimeAdapterVocabulary`, `ViolationVocabulary`), not as magic literals.

**PHPDoc `@see` to vocabulary IRIs.** Every public contract/enum/value object carries `@see https://w3id.org/itpack/asyncit#*` references to the OWL ontology in `vocab/async-it.ttl` and SKOS taxonomy in `vocab/async-it-taxonomy.ttl`.

### Gaps

- `spawn()` / `awaitReadable()` primitives are documented as "Phase 6+ roadmap" but are referenced in README examples. The current `AsyncRuntime::run()` only supports a single root task; multi-task spawning from user code is via `spawn()`/`await()` on the active scheduler only.
- No persistent task queue implementation in core (Redis adapter exists in `Adapter/Redis/` but `PersistentTaskQueueInterface` is contract-only).

---

## 2. Maintenance & Maintainability

### Static Analysis

| Tool | Result |
|---|---|
| PHPStan (level: max) | **3 errors** (see below) |
| phparkitect | **0 violations** |
| php-cs-fixer | **5 fixable files** (mostly unused imports / whitespace) |
| line-length lint (120 chars) | Passes |
| single-type-per-file lint | Passes |
| phpmnd (magic numbers/strings) | **34 detections** |

### PHPStan Errors (level max)

1. `src/Application/Factory/TaskIdFactoryInterface.php:13` — `createWithId()` is declared but never called. Dead interface method.
2. `src/Contract/Exception/ChannelException.php:10` — Concrete class not declared `final` (violates "every concrete class must be `final`").
3. `src/Domain/SelectGroup.php:111` — Implicit array creation (`$result` variable used without prior initialization).

### Magic Numbers / Strings (phpmnd, 34 total)

Significant detections:
- `Domain/CircuitBreaker.php:45` — `5` (default failure threshold)
- `Domain/RetryPolicy.php:51` — `3` (default max attempts)
- `Domain/SchedulerConfiguration.php:53,59` — `1`, `1` (bounds for sampling rate / canary weight)
- `Conformance/Verifier/TraceParentageVerifier.php:99,106` — `2` (minimum event counts)
- `Conformance/Verifier/GroupCancellationIsolationVerifier.php:66,77,96` — `'a-done'`, `'b-done'` (magic strings)
- `Conformance/Fixture/*` — `'AsyncRuntime/Amp'`, etc. (runtime name strings)
- `Adapter/OpenTelemetry/OpenTelemetryTelemetryBridge.php:126,137,151` — `'unknown'`, `'succeeded'`
- `Adapter/Prometheus/PrometheusTelemetryBridge.php:180` — `'succeeded'`

Most magic numbers are legitimate defaults (thresholds, bounds) but should be named constants per project rules. Magic strings in conformance verifiers and telemetry bridges should be vocabulary constants.

### Code Organization

- **129 classes, 68 interfaces, 461 methods** (phpmetrics).
- Average cyclomatic complexity: **4.84 per class** (well under the complexity > 10 threshold).
- Average methods per class: 3.57 (small, focused classes).
- No abstract classes in `src/` (the only abstract reference is a comment about a removed `AbstractRuntimeFixture`). Good adherence to "no abstract classes; prefer traits and composition."

### Traits Used Appropriately

- `TaskBehavior` — shared lifecycle state + transition matrix (used by `Task`, `TaskGroup`, `RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup`).
- `CoroutineContextStorageTrait` — shared LIFO context attach/detach protocol (used by Revolt and Swoole context storages).
- `StreamSelectPollerTrait` — shared `stream_select()` readiness probe (used by all 5 pollers).

---

## 3. Performance

### Strengths

- **Bounded task-results buffer:** `FiberTaskScheduler::MAX_TASK_RESULTS = 1000` with FIFO eviction (`array_shift`).
- **Channel compaction:** `EmulatedChannel::receive()` reindexes when head passes half the queue, bounding memory growth.
- **Monotonic clocks:** All durations use `hrtime()` / `MonotonicClockInterface`, never wall-clock. `SchedulerConfiguration` validates `gracefulShutdownDeadline > 0`.
- **Lazy telemetry:** `LazyTelemetryBridge` defers recorder construction to first `record()` call via `Closure` factory.
- **O(1) timer nearest-deadline:** `computeNearestTimerDeadline()` is O(n) but timers are typically few; acceptable.

### Concerns

- `FiberTaskScheduler::drainExpiredTimers()` iterates all timers every tick — O(n) per reactor iteration. With many concurrent timers this becomes a hot-path scalability issue.
- `FiberTaskScheduler::clearTimers()` uses `array_filter` + `array_values` on every completion — O(n) copy.
- `FiberTaskScheduler::runUntil()` calls `computeTimeout()` which calls `computeNearestTimerDeadline()` — O(n) scan on every poll iteration.
- `EmulatedChannel::send()` / `trySend()` compute `$size = count($this->queue) - $this->head` — O(1) but repeated.

### PHP Version

`composer.json` requires `"php": "^8.5"` plus `symfony/polyfill-php86`. The codebase targets PHP 8.5+ but references PHP 8.6 `Io\Poll` via polyfill. Lazy objects (PHP 8.4+) and property hooks (PHP 8.4+) are available but underutilized (see §6).

---

## 4. Security

### Strengths

- **Context isolation:** Conformance Assertion A verifies zero context bleed across suspension/resumption. Context is detached under `finally` in `FiberTaskScheduler::step()`.
- **Handle cleanup:** Conformance Assertion C verifies every `StreamPollHandle` and timer is released on terminal transition. `forceCancelRemaining()` ensures handles are released even on deadline expiry.
- **Debug surface opt-in:** `SchedulerConfiguration::enableDebug()` is explicit; debug is `false` by default. Docblock notes "Security: debug surface is absent unless explicitly enabled."
- **No secrets in code:** No hardcoded keys, tokens, or credentials found.
- **Input validation:** `TaskId` rejects empty strings; `RemoteTask` validates FQCN format; `SchedulerConfiguration` validates concurrency bounds (1–10,000) and sampling rate (0–1).
- **Fail-safe publication:** Publisher failures go through a recursion-safe health path (documented in AGENTS.md publication policy).

### Concerns

- `FederatedScheduler::submit()` embeds cancellation token data into the task payload under scalar keys. The payload is `array<string, mixed>` and crosses the federation boundary — potential serialization/tampering surface. The remote side must validate.
- `RedisTaskQueue` uses regex key validation (`/^[A-Za-z0-9._:-]+$/`) — good, but Redis command injection via `pipeline()` dynamic calls is possible if caller-controlled keys reach it.

---

## 5. Developer Experience (DX)

### Strengths

- **README** is excellent: conceptual model, tier stack, architectural pillars, usage example, public API reference, roadmap, PSR posture.
- **Clear, domain-aligned naming:** `Task`, `Scheduler`, `Awaiting`, `Poller`, `Cancellation`, `CapabilityDescriptor`, `StreamPollHandle`, `RuntimeDiscovery` — no vague `Manager`/`Helper`/`Utility` names.
- **Factory/builder ergonomics:** All construction goes through injected factories. `AsyncRuntime::discover()` is a clean static entry point.
- **Conformance verifiers:** `AsyncRuntimeVerifier` composes 6 verifiers (context isolation, deterministic cleanup, group cancellation, timer cleanup, trace parentage, graceful shutdown). Adapters cannot ship without passing.
- **Vocabulary interfaces:** Constants are named and documented, not magic literals.
- **QA scripts:** `composer quality` runs validate → assumptions → cs:check → analyse → architecture → lint → test → security:audit.

### Concerns

- **`AsyncRuntime::run()` creates `TaskId` directly** (5× `new TaskId(...)`), violating the "instances only in factories" rule. Consumers cannot substitute a custom `TaskId` implementation.
- **Integration and Concurrency test suites are empty** (0 tests each). The `codecept run` configuration lists them but they contain no test files.
- **Roadmap features (`spawn`, `awaitReadable`) are referenced but not implemented** — README examples reference them as available.

---

## 6. SOLID & DRY Principles

### SOLID Assessment

| Principle | Status | Notes |
|---|---|---|
| Single Responsibility | Pass | Classes are small and focused (avg 3.57 methods/class) |
| Open/Closed | Pass | Interfaces at boundaries; new adapters added without modifying core |
| Liskov Substitution | Pass | All concrete classes implement interfaces; adapters are substitutable |
| Interface Segregation | Pass | Small focused interfaces (`PollerPortInterface`, `TaskChannelInterface`, etc.) |
| Dependency Inversion | Pass | Factories injected; domain depends only on contracts |

### DRY Violations (Duplicate Code)

**1. Combinator groups — near-identical boilerplate (HIGH)**

`RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup` all share:
- Same constructor signature (`TaskId`, `EngineSchedulerInterface`, `CancellationScopeInterface`, `ExecutionIdInterface`)
- Same `TaskBehavior` trait usage
- Same `add()` method (guard `$this->resolved`, spawn, push child)
- Same `$resolved` flag + `transitionTo(TaskStatus::Created)` in constructor

`RaceGroup::cancelRemaining()` (lines 117–124) and `SelectGroup::cancelRemaining()` (lines 122–129) are **character-for-character identical**. `RaceGroup::findChild()` (lines 126–135) and `SelectGroup::findChild()` (lines 131–140) are **identical**.

**2. Poller adapters — ~90% structural duplication (HIGH)**

`SwoolePoller`, `SwowPoller`, `RevoltPoller` share:
- `use StreamSelectPollerTrait`
- Same `$handles`, `$readyReadStreams`, `$readyWriteStreams`, `$counter` properties
- Identical `register()`, `release()`, `await()`, `readyHandles()`, `activeHandleCount()` bodies
- Differ only in: ID prefix (`'swoole-'`/`'swow-'`/`'revolt-'`), registry array name, reactor class, and `registerWith*()` body (3 lines each)

`NativePoller` and `SelectPoller` also share the same handle-track/release/ready-handles pattern without using the trait.

**3. Channel adapters — ~80% duplication (MEDIUM)**

`SwooleChannel` and `SwowChannel` share `send()`, `receive()`, `trySend()`, `waitForCapacity()`, `isClosed()`, `close()` bodies. Differ only in: push return-value handling, sentinel creation (`CapacitySentinel::create()` used in both).

**4. Value objects — near-identical (MEDIUM)**

`RemoteTask` and `PersistentTask` are both `readonly` classes with `TaskId $id`, `string $operation`, `array $payload`, `?DurationInterface $deadline`, nearly identical constructors and accessors. Differ only in: `RemoteTask` has `priority` + `correlationId` + `Stringable`; `PersistentTask` has `TaskStatus $status`.

**5. `new StreamPollHandle($id)` in pollers (MEDIUM)**

7 poller/bridge files each directly instantiate `StreamPollHandle` instead of delegating to a factory.

### Complexity Outliers

No method exceeds cyclomatic complexity 10. `FiberTaskScheduler` is the largest class (968 lines, ~30 methods) but its methods are individually focused. `phpmetrics` reports no class as a clear complexity outlier.

---

## 7. Modern PHP Features

| Feature | Usage | Assessment |
|---|---|---|
| Fibers | Extensive (`Fiber::suspend()`, `Fiber::start()`, `Fiber::resume()`) | Good — used through `FiberFactoryInterface` |
| Readonly classes | ~30 classes (`readonly class`, `final readonly class`) | Excellent — TaskResult, SchedulerConfiguration, CapabilityDescriptor, etc. |
| Property hooks | `TaskId::$value` getter hook | Good — used where it adds clarity |
| Enums (closed sets) | `TaskStatus`, `CapabilityKind`, `CapabilityStatus`, `CircuitState`, `AwaitPoint`, `SchedulerEventType`, `SchedulingPolicy`, `BackpressurePolicy`, `RetryStrategy`, `JitterStrategy`, `RuntimeAdapterKind` | Excellent — all closed sets are enums backed by vocabulary constants |
| Lazy objects | Not used | **Missed opportunity** — `LazyTelemetryBridge` uses a `Closure` factory instead of PHP 8.4 `ReflectionFactory::createLazy()` |
| WeakMap / WeakReference | Not used | **Missed opportunity** — no `WeakMap` for per-task metadata; `FiberScopeSlotTrait` uses a `$fallback` property that could be a weak reference |
| Traits | `TaskBehavior`, `CoroutineContextStorageTrait`, `StreamSelectPollerTrait` | Good — used for shared behavior without inheritance |
| Readonly properties | Extensive (`public readonly`) | Excellent |
| Match expressions | `TaskStatus::allowedNext()`, `RetryPolicy::delayForAttempt()` | Good |
| Constructor promotion | Extensive | Good |

---

## 8. Code-Quality Rule Compliance

| Rule | Status | Details |
|---|---|---|
| Concrete DI classes backed by interface | Mostly pass | `ChannelException` not `final`; some concrete classes lack interfaces |
| No abstract classes | Pass | No `abstract class` in `src/` |
| Cyclomatic complexity > 10 | Pass | No method exceeds 10 |
| No `else` / `elseif` | Pass | None found |
| No Yoda-style conditions | Pass | None found |
| Imports at top of file | Pass | All files have proper import blocks |
| Instances only in factories/builders | **Fail** | Multiple violations (see below) |
| Declaration ordering (traits→constants→props→constructors→factories→methods→static) | Mostly pass | Files follow this pattern |
| Public before protected/private | Pass | Consistent |
| One type per file | Pass | Enforced by lint tool |
| Max 120 chars per line | Pass | Enforced by lint tool |

### "Instances Only in Factories" Violations

`new TaskId(...)` is called directly in non-factory code:
- `src/SDK/AsyncRuntime.php:81,150,174,198,223` — 5× direct `new TaskId()` in SDK entry point (should inject `TaskIdFactoryInterface`)
- `src/Application/Federation/FederatedScheduler.php:116` — `new TaskId((string) $id)` (should inject factory)

`new StreamPollHandle($id)` is called directly in adapters (not factories):
- `NativePoller.php:56,99`, `SelectPoller.php:50`, `SwoolePoller.php:62`, `SwowPoller.php:62`, `RevoltPoller.php:63`, `ReactPhpPollerBridge.php:70`, `AmpPollerBridge.php:65`, `StreamSelectPollerTrait.php:102`

`new ContextToken($id)` directly:
- `MemoryContextStorage.php:57`, `CoroutineContextStorageTrait.php:51`

`new CancellationScope()` directly in test fixtures:
- `ReferenceRuntimeFixture.php:72`, `RuntimeFixtureTrait.php:130`

---

## 9. Testing & Coverage

### Suite Summary

| Suite | Tests | Assertions | Status |
|---|---|---|---|
| Unit | 472 | 1,014 | ✅ All pass |
| Functional | 72 | 152 | ✅ All pass |
| Conformance | 7 | 3 (4 skipped) | ⚠️ Incomplete/skipped |
| Integration | 0 | 0 | ❌ Empty |
| Concurrency | 0 | 0 | ❌ Empty |

### Coverage Gaps (below 95% target)

Critical low-coverage classes:

| Class | Lines | Methods | Issue |
|---|---|---|---|
| `AsyncRuntime` | 42.02% | 66.66% | Core SDK entry point — 29/69 lines covered |
| `AsyncRuntimeFactory` | 66.01% | 33.33% | Factory for runtime assembly |
| `SelectGroup` | 48.00% | 40.00% | Combinator — half uncovered |
| `RaceGroup` | 73.46% | 60.00% | Combinator — ~1/4 uncovered |
| `TaskGroup` | 85.71% (functional) | 72.72% | Structured concurrency — destructor path untested |
| `TaskResult` | 84.61% | 88.88% | Terminal outcome — failure path gaps |
| `FiberTaskScheduler` | — | — | Reactor core — not listed in coverage (needs verification) |

**Estimated overall coverage: ~85–90%, below the 95% requirement.**

### Coverage Leakage

No evidence of cross-test leakage (each test class targets its own class). However, `AsyncRuntime` is covered across multiple suites (unit, functional, conformance) which inflates its apparent coverage when viewed per-suite.

### Missing Test Categories

- No integration tests (suite is empty)
- No concurrency tests (suite is empty)
- No performance benchmarks executed (phpbench config exists but no benchmarks written)
- No mutation testing results available (infection configured but not run in this review)

---

## 10. Detailed Findings & Recommendations

### HIGH PRIORITY

**H1. Deduplicate combinator groups.** `RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup` share ~80% boilerplate. Extract an abstract base or a shared trait (`CombinatorTrait`) with the common `add()`, `cancelRemaining()`, `findChild()`, and constructor logic. The four classes differ only in their `await()` strategy.

**H2. Deduplicate poller adapters.** `SwoolePoller`, `SwowPoller`, `RevoltPoller` are ~90% identical. Extract the shared handle tracking, release, and ready-handles logic into a base class or trait parameterized by the reactor registration call. The only runtime-specific code is the 3-line `registerWith*()` method.

**H3. Enforce "instances only in factories."** `AsyncRuntime` creates `TaskId` directly (5×). Inject `TaskIdFactoryInterface`. Pollers create `StreamPollHandle` directly — inject a `StreamPollHandleFactory` or move instantiation to `PollerFactory`.

**H4. Raise test coverage to 95%.** Focus on: `AsyncRuntime`, `AsyncRuntimeFactory`, `SelectGroup`, `RaceGroup`, `TaskGroup` destructor path, `FiberTaskScheduler`, and fill the empty Integration/Concurrency suites.

### MEDIUM PRIORITY

**M1. Replace magic numbers/strings with constants.** `CircuitBreaker::failureThreshold` default `5`, `RetryPolicy::maxAttempts` default `3`, conformance verifier strings `'a-done'`/`'b-done'`, telebridge `'succeeded'`/`'unknown'` → vocabulary constants.

**M2. Use lazy objects for `LazyTelemetryBridge`.** Replace the `Closure` factory with PHP 8.4 `ReflectionFactory::createLazy()` for true lazy initialization without the closure wrapper.

**M3. Add `WeakMap`/`WeakReference`.** Consider `WeakMap` for per-task metadata in the scheduler to avoid retaining references to completed tasks.

**M4. Fix PHPStan errors.** Remove dead `createWithId()` from `TaskIdFactoryInterface`, mark `ChannelException` as `final`, fix implicit array in `SelectGroup`.

**M5. Fix php-cs-fixer issues.** 5 files have fixable style violations (unused imports, missing spaces).

**M6. Add benchmarks.** phpbench is configured; add benchmarks for task spawn/completion, suspension/resumption, and poll-handle registration/teardown.

### LOW PRIORITY

**L1. Implement roadmap `spawn()` / `awaitReadable()` primitives** referenced in README.

**L2. Document the `ChannelException` non-final exception** (or make it final).

**L3. Consider extracting `RemoteTask`/`PersistentTask` common fields** into a shared base trait or value object.

---

## 11. Appendices

### A. Files Reviewed (Source)

Domain: `Task`, `TaskId`, `TaskResult`, `TaskGroup`, `RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup`, `TaskStream`, `PersistentTask`, `RemoteTask`, `CancellationScope`, `SchedulerConfiguration`, `SchedulerEvent`, `CircuitBreaker`, `RateLimiter`, `Bulkhead`, `RetryPolicy`, `CapabilityDescriptor`, `StreamPollHandle`, `DeduplicationRecord`, `TelemetrySchemaRegistry`, `FiberScopeSlot`, `FiberScopeSlotFactoryInterface`, `CoroutineContextStorageTrait`, `EmulatedChannel`, `TaskBehavior`.

Application: `FiberTaskScheduler`, `TaskRecord`, `SchedulerHealth`, `NativePoller`, `SelectPoller`, `PollerFactory`, `SchedulerFactory`, `TaskFactory`, `TaskRecordFactory`, `TaskIdFactory`, `CombinatorFactory`, `TaskGroupFactory`, `CancellationScopeFactory`, `PerRunExecutionScopeFactory`, `ReferenceAssemblyFactory`, `RuntimeAdapterAssembler`, `FederatedScheduler`, `ContextAttacher`, `DeadLetterQueue`, `BackpressureGate`, `CanaryScheduler`, `MiddlewareScheduler`, `ResourceQuotaChecker`, `TraceContextInjector`, `DeadLetterAwarePromise`, `GracefulShutdown`, `ArtifactNormalizer`, `ArtifactVocabulary`, `SchedulerFailureArtifact`, `SchedulerLifecycleArtifact`, `DeterministicMonotonicClock`, `MemoryContextStorage`, `ReferenceRuntimeDiscovery`.

SDK: `AsyncRuntime`, `AsyncRuntimeFactory`, `RuntimeAssemblerInterface`, `ArrayTelemetryRecorder`, `LazyTelemetryBridge`, `TelemetryBridgeEventListener`, `TelemetryRecorderInterface`.

Adapter: `SwoolePoller`, `SwowPoller`, `RevoltPoller`, `NativePoller`, `SelectPoller`, `SwooleChannel`, `SwowChannel`, `RevoltChannel`, `RedisTaskQueue`, `OpenTelemetryTelemetryBridge`, `PrometheusTelemetryBridge`, `AmpPollerBridge`, `ReactPhpPollerBridge`.

Conformance: `AsyncRuntimeVerifier`, `ContextIsolationVerifier`, `DeterministicCleanupVerifier`, `GroupCancellationIsolationVerifier`, `TimerCleanupVerifier`, `TraceParentageVerifier`, `GracefulShutdownVerifier`, all fixtures.

Contract: All interfaces (`SchedulerInterface`, `TaskInterface`, `PollerPortInterface`, `TaskChannelInterface`, `TaskResultInterface`, `CancellationScopeInterface`, `RuntimeDiscoveryInterface`, `TelemetryBridgeInterface`, `EngineSchedulerInterface`, etc.), port enums/vocabularies, exception contracts.

### B. Vocabulary Files

- `vocab/async-it.ttl` — AsyncIt Scheduling Ontology (OWL)
- `vocab/async-it-taxonomy.ttl` — AsyncIt Scheduling Taxonomy (SKOS)

### C. QA Commands Available

```
composer validate --strict
composer run assumptions      # php-a assumption analysis
composer run cs:check         # php-cs-fixer dry-run
composer run analyse          # phpstan (level max)
composer run architecture     # phparkitect
composer run lint:length      # 120-char line limit
composer run lint:types       # single-type-per-file
composer run test             # all suites
composer run mutation         # infection
composer run security:audit   # composer audit
composer run quality          # full pipeline
```

---

## Conclusion

AsyncIt is a well-designed scheduling engine with correct architectural layering, strong contract discipline, and good modern PHP usage. Its main weaknesses are **significant duplicate code** across combinator groups, poller adapters, and channel adapters; **below-95% test coverage** with empty integration/concurrency suites; and **violations of the "instances only in factories" rule** in the SDK and adapters. Addressing the deduplication and factory-rule violations would substantially improve maintainability without changing behavior.