# AsyncIt — In-Depth Review

> **Scope:** `it-pack/async-it` (`src/AsyncIt/`), the scheduling/progress tier of the ItPack execution stack.
> **Date:** 2026-10-29
> **Reviewer:** Automated review pass (Kilo)

---

## 0. Executive Summary

AsyncIt is a **well-architected, capability-driven scheduling layer** that cleanly separates scheduling semantics from execution semantics (RunIt) and I/O polling (engine). The hexagonal boundary enforcement (phparkitect) passes, PHPStan at bleeding-edge level passes, and CS-Fixer reports zero violations. The conformance-led design (Assertions A and C) is genuinely sound.

However, the project has **three concrete problem areas**:

| Area | Issue | Severity |
|---|---|---|
| **DRY** | 7 near-identical poller adapters; 3 near-identical context-storage adapters; 3 interchangeable context-token classes | 🔴 High |
| **Coverage** | 32.8 % classes / 52.5 % lines — far below the 95 % target | 🔴 High |
| **Magic literals** | `RuntimeAdapterKind` and `ViolationSeverity` enums use bare strings instead of vocabulary constants; nanosecond conversion factors are magic numbers in 7 files | 🟡 Medium |

---

## 1. Features and Overall Architecture

### 1.1 Layer Structure

AsyncIt follows a strict hexagonal/ports-and-adapters layering:

```
Domain  (Task, CancellationScope, FiberScopeSlot, Channel)
  ↓
Contract (interfaces, enums, value objects, vocabulary)
  ↓
Application (Scheduler, Factory, Clock, Context, Poller, Publisher, Scope)
  ↓
SDK (AsyncRuntime — the composition root)
  ↓
Adapter (Swoole, Swow, Revolt, Amp, ReactPhp interop)
  ↓
Conformance (verifiers + fixtures)
```

This is codified and enforced by **phparkitect** (`phparkitect.php` lines 42-56) with the following permitted dependency directions:

- **Domain** → Common, Port
- **Application** → Common, Domain, Port, Adapter
- **SDK** → Common, Domain, Port, Application
- **Adapter** → Common, Domain, Port
- **Conformance** → all

PHPStan (level max + bleeding edge) and phparkitect both pass with zero violations.

### 1.2 Key Architectural Decisions

- **Scheduling ≠ Polling ≠ Semantics** — the `PollerPortInterface` is an OS-readiness notifier only. The scheduler (`FiberTaskScheduler`) owns task queues, Fiber suspension/resumption, and cancellation. RunIt owns execution identity and context. ✅
- **Capability negotiation, not runtime guessing** — `AsyncRuntimeFactory::negotiate()` probes for class existence (`class_exists('Swoole\\Coroutine')`, etc.) rather than calling `extension_loaded()`. ✅
- **Context is immutable and fiber-safe** — `FiberTaskScheduler::step()` attaches the RunIt scope before each Fiber resume and detaches it under `finally` (conformance Assertion A). ✅
- **Cancellation is observed, not preemptive** — `CancellationScope` is a state machine that tasks check at cooperation points. ✅
- **Terminal handling releases handles and rethrows original exceptions** — `FiberTaskScheduler::resume()` catches `Throwable`, publishes via the recursion-guarded `FailSafeArtifactPublisher`, marks the task failed, releases all `StreamPollHandle` objects, and rethrows the original. ✅

### 1.3 What the README Promises vs. What Exists

The README (`src/AsyncIt/README.md`) references `AsyncRuntime::discover()->spawn()` and `awaitReadable()`. However, the actual `AsyncRuntime` class does not expose `spawn()` or `awaitReadable()` — it only exposes `run()`, `capability()`, `channel()`, and `telemetry()`. The README explicitly notes: *"This example is illustrative of the intended public surface. Concrete APIs are finalized through the conformance-led roadmap below."* This mismatch is documented and acceptable for the current phase, but it creates DX friction (see §6).

---

## 2. Maintenance and Maintainability

### 2.1 Code Duplication (DRY) — 🔴 High

This is the most significant maintainability concern. The poller and context-storage adapters are **near-identical copies** that differ only in which runtime API they call.

#### 2.1.1 Poller `await()` Method — Duplicated 7×

The `await()` method and `activeHandleCount()` are copy-pasted across all seven poller implementations:

| File | Lines | Unique Logic |
|---|---|---|
| `Application/Poller/SelectPoller.php` | §44-74 | `stream_select` only |
| `Application/Poller/NativePoller.php` | §71-82 | `Io\Poll::poll()` |
| `Adapter/Revolt/RevoltPoller.php` | §66-94 | `stream_select` + `Io\Poll::poll` |
| `Adapter/Swoole/SwoolePoller.php` | §65-93 | `stream_select` (Swoole watcher in register/release only) |
| `Adapter/Swow/SwowPoller.php` | §68-91 | `stream_select` (Swow watcher in register/release only) |
| `Adapter/Interop/Amp/AmpPollerBridge.php` | §66-94 | `stream_select` (Amp in register/release only) |
| `Adapter/Interop/ReactPhp/ReactPhpPollerBridge.php` | §71-100 | `stream_select` (ReactPHP in register/release only) |

The `await()` body is **byte-for-byte identical** (except Swoole/Swow which use `stream_select` directly):

```php
$seconds = intdiv($timeoutNanoseconds, 1_000_000_000);
$microseconds = intdiv($timeoutNanoseconds % 1_000_000_000, 1000);
$write = [];
$except = [];
try {
    @stream_select($read, $write, $except, $seconds, $microseconds);
} catch (\ValueError) {
}
```

Even `SelectPoller` and the adapter pollers share the same `$handles` array, `$counter`, `register()` (which creates a `StreamPollHandle` with a prefixed ID), and `activeHandleCount()` (which calls `count($this->handles)`).

**Recommendation:** Extract a shared `StreamSelectCapablePoller` abstract base or trait in `Application/Poller/` that provides the `await()` + `activeHandleCount()` logic and the handle-tracking bookkeeping. Adapters would only override `registerWithReactor()` and `$idPrefix`.

#### 2.1.2 Context Storage — Duplicated 3×

`SwooleCoroutineContextStorage` (134 lines), `SwowCoroutineContextStorage` (134 lines), and `RevoltFiberContextStorage` (124 lines) share **identical** `current()`, `attach()`, `detach()`, `slot()`, `fallback()`, and `blank()` methods. The *only* difference is the coroutine/fiber ID resolution:

| File | ID Source |
|---|---|
| `SwooleCoroutineContextStorage` | `\Swoole\Coroutine::getCid()` |
| `SwowCoroutineContextStorage` | `\Swow\Coroutine::getCid()` |
| `RevoltFiberContextStorage` | `\Fiber::getCurrent()` (with a `WeakMap`) |

**Recommendation:** Extract a `CoroutineContextStorage` abstract class or trait in `Domain/` or `Application/Context/` with a `protected function coroutineId(): int` hook. Note: the AGENTS.md explicitly says "no abstract classes; prefer traits and composition" — a trait is the correct approach here.

#### 2.1.3 Context Tokens — Duplicated 3×

`SwooleContextToken`, `SwowContextToken`, and `RevoltContextToken` are **byte-for-byte identical** (25 lines each):

```php
final readonly class SwowContextToken implements ContextTokenInterface, ValueObjectInterface
{
    public function __construct(public int $id) {}
    public function id(): int { return $this->id; }
}
```

Even the class name `MemoryContextToken` in `Application/Context/` has the same shape (minus `readonly` on the class). A single `AsyncIt\Contract\Port\ContextToken` value object would eliminate all three.

#### 2.1.4 Conformance Fixtures

The `RuntimeFixtureTrait` already does the right thing — it was introduced to replace an `AbstractRuntimeFixture` abstract class (per AGENTS.md line 29-31). This is good. ✅

### 2.2 Complexity

phpmetrics results for the most complex classes (CCN = cyclomatic complexity):

| Class | CCN | CCN max/method | WMC | Externals | Notes |
|---|---|---|---|---|---|
| `SwowPoller` | 12 | 5 | 16 | 6 | Above threshold |
| `ReactPhpPollerBridge` | 11 | 5 | 17 | 4 | Above threshold |
| `AmpPollerBridge` | 11 | 5 | 16 | 4 | Above threshold |
| `SwoolePoller` | 11 | 5 | 15 | 6 | Above threshold |
| `SwooleCoroutineContextStorage` | 11 | 4 | 17 | 12 | Above threshold |
| `SwowCoroutineContextStorage` | 11 | 4 | 17 | 12 | Above threshold |
| `FiberTaskScheduler` | 10 | 5 | 17 | 14 | At threshold |
| `RevoltPoller` | 10 | 5 | 14 | 6 | Above threshold |
| `NativePoller` | 9 | 4 | 15 | 4 | — |
| `RevoltFiberContextStorage` | 8 | 3 | 14 | 13 | — |
| `AsyncRuntimeFactory` | 7 | 7 | 9 | 23 | Highest externals; `negotiate()` is a single 7-branch chain |

**Classes above CCN 10** (per the task's threshold): `SwowPoller` (12). Five additional classes are at CCN 10-11 (`ReactPhpPollerBridge`, `AmpPollerBridge`, `SwoolePoller`, `SwooleCoroutineContextStorage`, `SwowCoroutineContextStorage`, `RevoltPoller`).

**phpmetrics outlier flags:**
- `RuntimeAdapterAssembler` — "Too dependent" (efferent coupling = 23). This is the central assembly point for all adapters and imports every concrete adapter. While expected for an assembler, the number could be reduced by extracting per-runtime `RuntimeAssemblyFactory` classes (Strategy pattern).
- `ReferenceExecutionScope` — "Blob / God object" (8 public methods, LCOM=8). However, this is by design: all methods throw `BadMethodCallException` because it's a scheduling stand-in that only needs identity. This is documented in the class header. The real scope will be supplied by RunIt in Phase 3.

### 2.3 Test Suite Organization

- **Codeception** is used for all four suites: `Unit`, `Integration`, `Conformance`, `Concurrency`.
- The `Integration` and `Concurrency` suites have **zero tests** (only `.gitkeep`).
- The `Conformance` suite has 7 test cases, 4 of which are **skipped** (Native/Swow/Swoole/Io\Poll adapters require extensions not installed in this environment).
- 41 unit tests, 84 assertions, 1 skipped, 4 conformance tests skipped.

### 2.4 Technical Debt Indicators

- `tests/_output/var/coverage/` — stale generated coverage HTML from prior runs. This directory is excluded by `.gitignore` (`tests/_output/`), so it is not committed. ✅
- `var/` — all generated artifacts (`.phpunit.cache`, phpmetrics output, coverage reports). Excluded by `.gitignore`. ✅

---

## 3. Performance

### 3.1 Scheduler Hot Path

`FiberTaskScheduler::run()` (`src/Application/Scheduler/FiberTaskScheduler.php:66-83`):

```php
while ($this->records !== []) {
    foreach ($this->records as $record) {
        if ($record->isDone()) {
            continue;
        }
        $this->step($record);
    }
    $this->poller->await(0);
    $this->records = array_filter(
        $this->records,
        static fn (TaskRecord $record): bool => !$record->isDone(),
    );
}
```

- **O(n²) iteration**: On each reactor tick, ALL records are iterated even if only one is ready. As task count grows, the `foreach` + `array_filter` reindexing becomes quadratic.
- The `await(0)` call passes `0` nanoseconds — a non-blocking poll. This means the reactor busy-spins when tasks are suspended waiting on I/O.
- `array_filter` + reassignment on every loop is O(n) per tick, compounding the O(n) iteration.

**Recommendation:** Use a `SplPriorityQueue` or `SplObjectStorage` for ready-task dispatch. A proper ready-queue (only schedule tasks that are unblocked) and a pending queue (tasks waiting on I/O) would reduce per-tick work to O(ready) instead of O(total).

### 3.2 Channel `array_shift()` — O(n) per receive

`EmulatedChannel::receive()` (`src/Domain/Channel/EmulatedChannel.php:52-59`) uses `array_shift($this->queue)`, which reindexes the entire array. For a bounded channel with frequent send/receive, this is O(n) per operation. A `Ds\Queue` or a circular buffer would be O(1).

### 3.3 Context Storage `array_values()` Reindex — O(n) per detach

`MemoryContextStorage::detach()` (`src/Application/Context/MemoryContextStorage.php:64-78`) calls `array_values($this->stack)` after each `unset` to maintain contiguous integer keys. This is O(n) per detach. Using the last-key index directly (as the other storages do) would be O(1).

### 3.4 Positive: Lazy Telemetry

`LazyTelemetryBridge` correctly defers construction of `ArrayTelemetryRecorder` (and by extension any OTel SDK) until the first `record()` call. This keeps the hot path free of heavy dependency initialization. ✅

### 3.5 Positive: Deterministic Clock

`DeterministicMonotonicClock` uses `hrtime()`-derived nanosecond counters and never touches wall-clock time for durations. ✅

---

## 4. Security

### 4.1 Context Isolation — ✅ Pass

The conformance Assertion A (`ContextIsolationVerifier`) is properly enforced:

- `FiberTaskScheduler::step()` attaches context before resume, detaches under `finally`. ✅
- `RuntimeFixtureTrait` provides a separate verifier poller instance so handle accounting is independent. ✅
- Context is not stored in process-global state (per-Fiber `WeakMap` in Revolt, per-coroutine ID in Swoole/Swow). ✅

### 4.2 Handle Leak Prevention — ✅ Pass

- Every `StreamPollHandle` is tracked in `$record->handles` by the `TaskRecord`. ✅
- `FiberTaskScheduler::releaseHandles()` is called on every terminal transition (success, failure). ✅
- `DeterministicCleanupVerifier` (Assertion C) proves zero retained handles after a register/await/release cycle. ✅

### 4.3 Error Suppression

The poller adapters use `@` to suppress errors from runtime APIs:

- `SwoolePoller::registerWithReactor()`: `@\Swoole\Event::add()` — suppresses warnings when the descriptor is non-selectable.
- `SwowPoller`: `@\Swow\Event::add()`
- `AmpPollerBridge`: `@\Amp\Loop::onReadable()`
- `NativePoller`: No suppression (uses `method_exists` checks instead).

This is **acceptable** for interop adapters where the runtime may not be loaded, but the broad `@\Swoole\Event::del()` in `SwoolePoller::release()` should at minimum log a suppressed error rather than silently ignoring it. **No secrets or credentials are present.** No user-controlled values are used as metric names.

### 4.4 Recursion Guard

The `FailSafeArtifactPublisher` (from RunIt) wraps publication with a `CallbackHealthReporter` and `FailOpenPolicy`. The `FiberTaskScheduler` catches `Throwable`, publishes via the publisher, then rethrows the original — preserving the business exception. ✅

---

## 5. Developer Experience (DX)

### 5.1 Quality Tooling — ✅ Strong

| Tool | Config | Status |
|---|---|---|
| PHPStan | `phpstan.neon` + `phpstan-bleeding-edge.neon` | Level max, 0 errors |
| phparkitect | `phparkitect.php` | 0 violations |
| PHP CS Fixer | `.php-cs-fixer.php` | 0 violations (PHP 8.4 ruleset) |
| Codeception | `codeception.yml` | All suites configured |
| Infection | `infection.json5` | Configured (not run during this review) |
| phpmetrics | Available | Metrics computed |

Custom lint scripts (`tools/lint/line-length.php`, `tools/lint/single-type-per-file.php`) enforce line length and single-type-per-file rules. ✅

### 5.2 DX Friction Points

- **README/example mismatch**: The README shows `$runtime->spawn()` and `$runtime->awaitReadable()`, but the actual `AsyncRuntime` class has neither. The README acknowledges this as "illustrative," but developers following the example will hit runtime errors.
- **Missing `@api` annotations**: Several public contracts (`TaskChannelInterface`, `TaskStatusInterface`, `TelemetryBridgeInterface`) have `@api` tags, but `PollerVocabulary` and several Factory interfaces (`PollerFactoryInterface`, `RuntimeDiscoveryFactoryInterface`) are missing `@api` annotations on their docblocks.
- **Inconsistent `@api` placement**: Some interfaces use ` * @api` on the line before the closing `*/` (e.g., `TaskInterface`), while others place it at the end of the docblock on the line with `*`. This is cosmetic but inconsistent.

---

## 6. SOLID and DRY Principles

### 6.1 Single Responsibility Principle (SRP) — ✅ Mostly

- `FiberTaskScheduler` has one responsibility: drive the reactor. ✅
- `CapabilityDescriptor` is a pure data holder. ✅
- `AsyncRuntimeFactory` does both **discovery** (negotiating capabilities) and **assembly** (constructing the runtime). This is two responsibilities in one class. ✅/🟡

### 6.2 Open/Closed Principle — ✅

The `RuntimeAdapterAssembler` uses a `match` expression on `RuntimeAdapterKind` to select adapters. Adding a new runtime requires adding a new enum case and a new match arm — this is technically a modification. However, an interface-based strategy map (e.g., `map<RuntimeAdapterKind, AdapterFactoryInterface>`) would be more OCP-compliant, allowing new adapters without modifying the assembler.

### 6.3 Liskov Substitution — ✅

All contract implementations are substitutable. The `PollerPortInterface` implementations are all interchangeable. ✅

### 6.4 Interface Segregation — ✅

Contracts are small and focused:
- `PollerPortInterface` (4 methods: register, release, await, activeHandleCount)
- `SchedulerInterface` (2 methods: submit, run)
- `EngineSchedulerInterface extends SchedulerInterface` (1 additional method: dispatch)
- `TaskChannelInterface` (4 methods)
- `CancellationScopeInterface` (3 methods)
- `TelemetryBridgeInterface` (2 methods)
- `VerifierInterface` (1 method: port)
- `AsyncRuntimeFixtureInterface` (7 methods)

None of the interfaces mix high-level and low-level concerns. ✅

### 6.5 Dependency Inversion — ✅

- `FiberTaskScheduler` depends on `FiberFactoryInterface`, `TaskRecordFactoryInterface`, `PollerPortInterface`, `ContextStorageInterface`, `ExecutionScopeFactoryInterface`, `ArtifactPublisherInterface` — all interfaces. ✅
- `AsyncRuntime` depends on `SchedulerFactoryInterface`, `TaskFactoryInterface`, `CancellationScopeInterface`, etc. — all interfaces. ✅
- `AsyncRuntimeFactory` (the composition root) creates concrete implementations, which is the correct pattern. ✅
- The SDK layer depends only on `RuntimeAdapterAssemblerInterface`, never on concrete adapters. ✅

### 6.6 DRY Violations — 🔴 Severe (see §2.1)

The three context-storage adapters, seven poller adapters, and three context-token classes are near-identical copies. This is the most significant DRY violation in the codebase.

---

## 7. Hexagonal Architecture

### 7.1 Layer Separation — ✅ Pass

phparkitect enforces the layering strictly:
- **Domain** depends only on `Common` and `Port`
- **Port** (Contract\Port\*) depends on `Common` and `Domain`
- **Application** depends on `Common`, `Domain`, `Port`, and `Adapter`
- **SDK** depends on `Common`, `Domain`, `Port`, and `Application`
- **Adapter** depends on `Common`, `Domain`, and `Port`
- **Conformance** depends on everything (test infrastructure)

### 7.2 Ports — ✅

The project defines clear port interfaces:
- `PollerPortInterface` — the polling port (OS-readiness notifier)
- `TaskChannelInterface` — the channel coordination port
- `SchedulerInterface` / `EngineSchedulerInterface` — the scheduling port
- `CancellationScopeInterface` — the cancellation port
- `TelemetryBridgeInterface` — the telemetry port

RunIt ports (`ContextStorageInterface`, `ArtifactPublisherInterface`, `MonotonicClockInterface`, `IdentifierGeneratorInterface`) are consumed directly, which is correct — AsyncIt is a port under RunIt.

### 7.3 Adapters — ✅ (with DRY caveat)

Runtime adapters (Swoole, Swow, Revolt, Amp, ReactPHP) correctly translate runtime concepts without changing AsyncIt scheduling semantics. The interop adapters use `class_exists` guards and `object` types to avoid hard dependencies. ✅

However, the adapters are **duplicated** rather than composed around a shared base (see §2.1).

### 7.4 Composition Root — ✅

`AsyncRuntimeFactory::create()` is the single composition root. It:
1. Negotiates the runtime (`negotiate()`)
2. Assembles adapter primitives via `RuntimeAdapterAssembler`
3. Constructs all factories and services
4. Wires the `FiberTaskScheduler` through `SchedulerFactory`
5. Returns a fully-assembled `AsyncRuntime`

This is clean and follows the composition root pattern. ✅

---

## 8. Code Quality and Design Requirements

### 8.1 Concrete Services Backed by Interfaces — ✅

Every concrete service intended for DI is backed by an interface:

| Concrete Class | Interface |
|---|---|
| `FiberTaskScheduler` | `EngineSchedulerInterface` |
| `CancellationScope` | `CancellationScopeInterface` |
| `NativePoller` / `SelectPoller` | `PollerPortInterface` |
| All `*Poller` adapters | `PollerPortInterface` |
| All `*ContextToken` | `ContextTokenInterface` |
| `AsyncRuntime` | `ExecutionEngineInterface` (RunIt) |
| `SchedulerFactory` | `SchedulerFactoryInterface` |
| `TaskFactory` | `TaskFactoryInterface` |
| `PollerFactory` | `PollerFactoryInterface` |
| `AsyncRuntimeFactory` | `RuntimeAssemblerInterface` |
| `RuntimeAdapterAssembler` | `RuntimeAdapterAssemblerInterface` |
| `MemoryContextStorage` | `ContextStorageInterface` (RunIt) |
| `NullArtifactPublisher` | `ArtifactPublisherInterface` (RunIt) |
| `AsyncRuntimeVerifier` | `VerifierInterface` |
| `LazyTelemetryBridge` | `TelemetryBridgeInterface` |
| `ArrayTelemetryRecorder` | `TelemetryRecorderInterface` |
| `ReferenceRuntimeDiscovery` | `RuntimeDiscoveryInterface` |

Value objects (`CapabilityDescriptor`, `StreamPollHandle`, `TaskId`, `RuntimeAssembly`) and data holders (`TaskRecord`, `FiberScopeSlot`) correctly have no interface — they are not services. ✅

### 8.2 No Abstract Classes — ✅

All concrete classes are `final`. The only reuse mechanism besides composition is `RuntimeFixtureTrait` (which replaced a former `AbstractRuntimeFixture`). Phpmetrics confirms: 0 abstract classes in `src/`. ✅

### 8.3 Complexity — 🟡

- **CCN > 10**: `SwowPoller` (12). Five additional classes are at CCN 10-11.
- **Most complex class**: `AsyncRuntimeFactory` has CCN=7 (the `negotiate()` method alone is CCN=7) with 23 externals — the highest in the codebase.
- **phpmetrics outlier**: `RuntimeAdapterAssembler` flagged as "Too dependent" (23 externals). `ReferenceExecutionScope` flagged as "Blob / God object" (design-intended stand-in).

### 8.4 No `else` / `elseif` — ✅

```
grep -rn 'else\b\|elseif' src/ → no results
```

All conditional logic uses early returns, `match` expressions, or guard clauses. ✅

### 8.5 Magic Strings and Numbers — 🟡

| Location | Issue |
|---|---|
| `RuntimeAdapterKind` (4 cases) | Bare strings `'reference'`, `'revolt'`, `'swoole'`, `'swow'` — should bind to a `RuntimeAdapterVocabulary` interface constant (like `TaskStatus` → `TaskStatusVocabulary`, `CapabilityKind` → `CapabilityVocabulary`) |
| `ViolationSeverity` (2 cases) | Bare strings `'error'`, `'warning'` — should bind to vocabulary constants |
| All 7 poller adapters | `1_000_000_000` (nanoseconds per second) and `1000` (nanoseconds per microsecond) used directly — should extract to a constant (e.g., `PollerVocabulary::NANOSECONDS_PER_SECOND`) |
| `SchedulerFailureArtifact::normalize()` | `'kind' => 'asyncit.scheduler.failure'` — magic string literal |

### 8.6 Vocabulary Constants in Interfaces — ✅/🟡

Correctly implemented for:
- `TaskStatusVocabulary` (TaskStatus enum) ✅
- `CapabilityVocabulary` (CapabilityKind, CapabilityStatus enums) ✅
- `PollerVocabulary` (PollerVocabulary interface) ✅
- `ViolationVocabulary` (ViolationVocabulary interface) ✅

**Missing:** `RuntimeAdapterKind` and `ViolationSeverity` do not bind to vocabulary constants.

### 8.7 No Yoda Conditions — ✅

```
grep -rn 'true ===\|false ===\|null ===' src/ → no results
```

All comparisons use the `$value === $expected` form. ✅

### 8.8 Imports — ✅

PHPStan at max level + bleeding edge confirms all referenced classes are imported. ✅

### 8.9 `new` Outside Factories — 🟡

The AGENTS.md rule states: *"New class instances must only be created in factories or builders."*

**Compliant:** `FiberTaskScheduler`, `SchedulerFactory`, etc. never call `new` on collaborators — they receive them via constructor injection. ✅

**Edge case:** `AsyncRuntimeFactory` is itself a factory/composition root and creates concrete objects. This is the correct pattern for a composition root. ✅

**Violation:** `SwizzlePoller` and other adapter pollers create `new StreamPollHandle($id)` directly inside `register()`. However, `StreamPollHandle` is a **value object** (implements `ValueObjectInterface`). The AGENTS.md explicitly exempts value objects: *"they carry no behavior and are not subject to dependency-injection factory construction."* ✅

### 8.10 Declaration Ordering — 🟡

The AGENTS.md requires: 1. Traits → 2. Constants → 3. Properties/hooks → 4. Constructor → 5. Factory methods → 6. Non-static methods → 7. Static methods.

**Violations found:**

1. **`AsyncRuntime`** (`src/SDK/AsyncRuntime.php:57`): `public static function discover()` (static) appears **before** the non-static methods `channel()`, `telemetry()`, `run()`, `capability()`. Per the rule, static methods should come last.
2. **`AsyncRuntimeFactory`** (`src/SDK/Factory/AsyncRuntimeFactory.php:54`): `public static function create()` (static) appears before the private non-static methods `negotiate()` and `buildTelemetry()`.

These are minor ordering issues but violate the stated convention. The `ordered_class_elements` CS-fixer rule is enabled but does not enforce the full AGENTS.md ordering (it uses default ordering groups).

### 8.11 One Class Per File — 🟡

Two test files define multiple classes:

| File | Classes |
|---|---|
| `tests/Unit/Application/Poller/NativePollerTest.php` | `NativePollerTest` + `FakeNativePoll` |
| `tests/Unit/Adapter/Interop/ReactPhp/ReactPhpPollerBridgeTest.php` | `ReactPhpPollerBridgeTest` + `FakeReactLoop` |

These violate the PSR-4 "one class per file" rule that the AGENTS.md states applies to all code files. The fake classes should be extracted to their own files.

### 8.12 Line Length — ✅

The custom `tools/lint/line-length.php` script confirms all lines are ≤120 characters. ✅

### 8.13 `readonly` Class Consistency — 🟡

`MemoryContextToken` (`src/Application/Context/MemoryContextToken.php:18`) is declared as `final class` with a `readonly` promoted constructor parameter, while its adapter siblings (`SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`) are declared as `final readonly class`. This is a minor inconsistency — `MemoryContextToken` should also be `final readonly class` for consistency.

---

## 9. Testing and Coverage

### 9.1 Overall Coverage — 🔴 Below Target

| Metric | Achieved | Target |
|---|---|---|
| Classes | 32.81 % (21/64) | ≥ 95 % |
| Methods | 43.77 % (102/233) | ≥ 95 % |
| Lines | 52.54 % (403/767) | ≥ 95 % |

### 9.2 Coverage by Layer

| Layer | Classes Covered | Notes |
|---|---|---|
| **Contract** | 8/13 | `StreamPollHandle` (0%), `TaskId` (0%) uncovered — value objects not tested directly |
| **Domain** | 3/9 | `Task` (60%), `EmulatedChannel` (72.7%), `CancellationScope` (64%) — task lifecycle transitions, conflicting completion, and cancellation callbacks are not fully tested |
| **Application** | 14/23 | `FiberTaskScheduler` (95.1% lines), `SelectPoller` (100%), `PollerFactory` (57%), `RuntimeAdapterAssembler` (50%) |
| **SDK** | 3/5 | `AsyncRuntimeFactory` (63%), `AsyncRuntime` (80%) — `negotiate()` branches for Swoole/Swow/Revolt/reference not fully exercised |
| **Adapter** | 0/14 | Most adapters are skipped when the underlying extension is absent (Swoole, Swow, Revolt) |
| **Conformance** | 8/8 | All verifiers and result classes at 100% |

### 9.3 Key Coverage Gaps

- **`Domain\Task`** (60% lines, 57% methods): `transitionTo()` and `complete()` are partially covered. The `ConflictingCompletionException` and `InvalidLifecycleTransitionException` paths are not directly exercised by a dedicated `TaskTest`.
- **`Contract\Port\StreamPollHandle`** (0% methods): The empty-id validation path is never tested.
- **`Contract\TaskId`** (0% methods): The empty-string validation is never tested.
- **`Application\Scheduler\FiberTaskScheduler`** (75% methods): The `releaseHandles()` private method and the `$results[]` path in `dispatch()` are partially covered.
- **`Application\Context\MemoryContextStorage`** (85% lines but 50% methods): `detach()` error paths not tested.
- **`Domain\Channel\EmulatedChannel`** (72.7% lines): `close()` and `isClosed()` not tested.
- **`Application\Factory\RuntimeAdapterAssembler`** (50% lines, 20% methods): Only the Reference branch is exercised; Swoole/Swow/Revolt branches are untested (extensions absent).
- **`SDK\Factory\AsyncRuntimeFactory`** (63% lines, 33% methods): Only the Reference negotiation path is covered.

### 9.4 Skipped Tests

The `Conformance` suite skips 4 of 7 tests because Swoole/Swow extensions and PHP 8.6 `Io\Poll` are not installed:

```
RuntimeAdapterConformanceTest: Swow adapter conforms — skipped
RuntimeAdapterConformanceTest: Native poller conforms — skipped
RuntimeAdapterConformanceTest: React php interop conforms — skipped
RuntimeAdapterConformanceTest: Amp interop conforms — skipped
```

This is **environmental**, not a code defect. When extensions are present, the conformance suite validates Assertion A (context isolation) and C (deterministic cleanup) against the real adapter.

### 9.5 Test Quality — ✅

- Tests verify **observable behavior**, not private implementation details. ✅
- `@covers` annotations are precise and scoped to the tested class. ✅
- **Coverage leakage**: No `@covers` annotation leaks. The `FakeRuntimeFixture` and `FakeExecutionScope` test doubles are in `tests/`, which is excluded from coverage reporting. ✅
- Conformance tests use fake fixtures (`FakeRuntimeFixture`) for negative-path testing of verifiers, while production adapters are tested through the `RuntimeAdapterConformanceTest`. ✅

### 9.6 What's Missing

- A dedicated `TaskTest` covering all lifecycle transitions and exception paths.
- A dedicated `TaskIdTest` and `StreamPollHandleTest` for validation.
- A dedicated `EmulatedChannelTest` covering `close()`/`isClosed()`.
- A dedicated `CancellationScopeTest` covering `onCancel()` callback execution.
- A dedicated `CapabilityDescriptorTest` covering `statusFor()` with unknown kinds.
- `MemoryContextStorageTest` covering the LIFO violation and type-mismatch error paths.

---

## 10. Modern PHP Features

| Feature | Usage | Assessment |
|---|---|---|
| **Fibers** | `FiberFactory`, `FiberTaskScheduler` | ✅ Core scheduling primitive |
| **WeakMap** | `RevoltFiberContextStorage` (per-Fiber context) | ✅ Prevents memory leaks; allows GC of dead fibers |
| **Property hooks** | `TaskId::$value` (virtual read-only) | ✅ Clever use — virtual property backed by private `$id` |
| **Readonly classes** | `AsyncRuntime`, `CapabilityDescriptor`, `StreamPollHandle`, `ConformanceResult`, `ConformanceViolation`, `LazyTelemetryBridge`, `RuntimeAssembly` | ✅ Applied where mutation is impossible |
| **Enums (backed)** | `TaskStatus`, `CapabilityKind`, `CapabilityStatus`, `RuntimeAdapterKind`, `ViolationSeverity` | ✅ Closed sets use enums; extensible sets use vocabulary interfaces |
| **`readonly` promoted constructors** | Throughout (`AsyncRuntime`, `EngineSchedulerInterface`, etc.) | ✅ Consistent |
| **Virtual properties** | `TaskId::$value` | ✅ |
| **Lazy objects (PHP 8.4)** | Not used | 🟡 `LazyTelemetryBridge` uses a closure-based lazy proxy instead — acceptable, but PHP 8.4 lazy objects could be considered |
| **`never` return type** | Not used | 🟡 No exceptions marked `never` (none exist that should throw unconditionally) |

### 10.1 Positive: `TaskId` Virtual Property

```php
public string $value {
    get => $this->id;
}
private string $id;
```

This is a **virtual property** — `$value` has no backing store and reads from `$this->id`. This is the correct PHP 8.4 pattern for a computed read-only projection. ✅

### 10.2 Positive: WeakMap for Per-Fiber State

`RevoltFiberContextStorage` uses `\WeakMap<\Fiber, FiberScopeSlot>` for per-fiber context storage. This allows fibers to be garbage-collected when they go out of scope, preventing memory leaks. ✅

### 10.3 Observation: `EngineCapability` Construction

`AsyncRuntimeFactory::create()` constructs `EngineCapability` with hardcoded `false` for `supportsCoroutines` and `concurrent`, deriving only `supportsFibers` from the `CapabilityDescriptor`. A more capability-driven approach would map `CapabilityKind::BlockingHook` → `supportsCoroutines` and `CapabilityKind::EdgeTriggeredPolling` → `concurrent`, making the descriptor the sole source of truth.

---

## 11. Factories and Builders

### 11.1 Factory Pattern — ✅ Well Applied

All factories follow a consistent interface:

| Factory | Interface | Purpose |
|---|---|---|
| `FiberFactory` | `FiberFactoryInterface` | Creates Fibers |
| `TaskFactory` | `TaskFactoryInterface` | Creates Tasks with generated IDs |
| `TaskRecordFactory` | `TaskRecordFactoryInterface` | Creates TaskRecords |
| `SchedulerFactory` | `SchedulerFactoryInterface` | Creates FiberTaskScheduler per scope |
| `PollerFactory` | `PollerFactoryInterface` | Selects NativePoller or SelectPoller |
| `CancellationScopeFactory` | `CancellationScopeFactoryInterface` | Creates CancellationScopes |
| `RuntimeAdapterAssembler` | `RuntimeAdapterAssemblerInterface` | Assembles poller + storage + channel per adapter |
| `RuntimeDiscoveryFactory` | `RuntimeDiscoveryFactoryInterface` | Selects runtime assembly |
| `AsyncRuntimeFactory` | `RuntimeAssemblerInterface` | Composition root |

### 11.2 Factory Composition — ✅

`SchedulerFactory::create()` delegates to `PerRunExecutionScopeFactory` (which wraps a single scope). This keeps the scope factory's contract uniform with future real scope factories. ✅

### 11.3 `RuntimeAdapterAssembler` — 🟡 Too Many Externals

With 23 external dependencies (the highest in the codebase), `RuntimeAdapterAssembler` is the single point through which all concrete adapter classes flow. While this is architecturally correct (Application\Factory is allowed to depend on Adapter\*), it could be decomposed into per-runtime assemblers (`SwooleAssembler`, `SwowAssembler`, `RevoltAssembler`) implementing a common `AdapterFactoryInterface`, reducing the blast radius of changes and the class's external coupling count.

---

## 12. Conformance and Verification Strategy — ✅ Excellent

The conformance design is genuinely well-executed:

1. **`AsyncRuntimeVerifier`** composes `ContextIsolationVerifier` (Assertion A) and `DeterministicCleanupVerifier` (Assertion C) into a single runnable check.
2. **`RuntimeFixtureTrait`** replaces the former abstract class, allowing each fixture to remain `final`.
3. **Verifiers are framework-agnostic** — they depend only on port interfaces and the fixture interface, not on any test framework base class. ✅
4. **Negative tests** prove the verifiers detect violations (`doublyBrokenFixture`, `bleedingFixture`, `leakingFixture`). ✅
5. **Positive tests** prove the reference runtime passes (`testReferenceRuntimeConforms`, `testReferenceRuntimeConforms`). ✅
6. **Adapter conformance** runs the same suite against Swoole, Swow, Revolt, Native, ReactPHP, and Amp (skipped when extensions absent). ✅
7. **RunIt conformance** is reused — `AsyncRuntimeTest` runs RunIt's `ExecutionEngineVerifier` against the discovered runtime. ✅

---

## 13. Summary of Findings

### 🔴 Critical / High

| # | Finding | Location |
|---|---|---|
| 1 | **Test coverage 52.5%** (target: 95%) | Global |
| 2 | **Poller `await()` duplicated 7×** with magic number `1_000_000_000` | `SelectPoller.php:57-58`, `NativePoller.php:73`, all 5 adapter pollers |
| 3 | **Context storage duplicated 3×** (Swoole/Swow/Revolt) | `SwooleCoroutineContextStorage.php`, `SwowCoroutineContextStorage.php`, `RevoltFiberContextStorage.php` |
| 4 | **Context tokens identical 3×** | `SwooleContextToken.php`, `SwowContextToken.php`, `RevoltContextToken.php` |
| 5 | **`RuntimeAdapterAssembler` efferent coupling = 23** (phpmetrics "Too dependent") | `src/Application/Factory/RuntimeAdapterAssembler.php` |

### 🟡 Medium

| # | Finding | Location |
|---|---|---|
| 6 | `RuntimeAdapterKind` enum uses magic strings (no vocabulary interface) | `Contract/Port/RuntimeAdapterKind.php:24-33` |
| 7 | `ViolationSeverity` enum uses magic strings (no vocabulary interface) | `Conformance/Result/ViolationSeverity.php:20-21` |
| 8 | `SchedulerFailureArtifact::normalize()` uses magic string `'asyncit.scheduler.failure'` | `Application/Artifact/SchedulerFailureArtifact.php:28` |
| 9 | `AsyncRuntime` — static method `discover()` before non-static methods (declaration order) | `src/SDK/AsyncRuntime.php:57` |
| 10 | `AsyncRuntimeFactory` — static method `create()` before non-static methods (declaration order) | `src/SDK/Factory/AsyncRuntimeFactory.php:54` |
| 11 | `NativePollerTest.php` — two classes in one file (`NativePollerTest` + `FakeNativePoll`) | `tests/Unit/Application/Poller/NativePollerTest.php:59` |
| 12 | `ReactPhpPollerBridgeTest.php` — two classes in one file (`ReactPhpPollerBridgeTest` + `FakeReactLoop`) | `tests/Unit/Adapter/Interop/ReactPhp/ReactPhpPollerBridgeTest.php:58` |
| 13 | `MemoryContextToken` not `readonly class` (inconsistent with adapter tokens) | `src/Application/Context/MemoryContextToken.php:18` |
| 14 | `FiberTaskScheduler::run()` is O(n²) — iterates all records every tick | `src/Application/Scheduler/FiberTaskScheduler.php:66-83` |
| 15 | `EmulatedChannel::receive()` uses O(n) `array_shift()` | `src/Domain/Channel/EmulatedChannel.php:58` |
| 16 | `AsyncRuntimeFactory::negotiate()` has CCN=7 (highest method complexity) | `src/SDK/Factory/AsyncRuntimeFactory.php:110-159` |

### 🟢 Passed (but worth noting)

| Check | Result |
|---|---|
| PHPStan (max + bleeding edge) | 0 errors ✅ |
| phparkitect architecture rules | 0 violations ✅ |
| PHP CS Fixer (PHP 8.4 ruleset) | 0 violations ✅ |
| Line length ≤120 | OK ✅ |
| Single-type-per-file | OK ✅ |
| No `else`/`elseif` | OK ✅ |
| No Yoda conditions | OK ✅ |
| All imports present | ✅ |
| All concrete services backed by interfaces | ✅ |
| No abstract classes | ✅ |
| Hexagonal dependency direction | ✅ |
| Context isolation (Assertion A) | Conformance pass ✅ |
| Deterministic cleanup (Assertion C) | Conformance pass ✅ |
| Original exceptions preserved | ✅ |
| Recursion-guarded publication | ✅ |
| Fibers, WeakMap, readonly, enums, property hooks | ✅ Used appropriately |

---

## 14. Checklist Verification

| Requirement | Status | Notes |
|---|---|---|
| Every concrete DI service backed by interface | ✅ | Section 8.1 |
| No abstract classes | ✅ | Section 8.2 |
| Methods with CCN > 10 flagged | ✅ | Section 8.3 (`SwowPoller` = 12) |
| phpmetrics outliers identified | ✅ | Section 8.3 |
| No `else`/`elseif` | ✅ | Section 8.4 |
| No magic strings/numbers | 🟡 | Section 8.5 — 3 violations |
| Constants/enums for magic literals | 🟡 | Sections 8.5-8.6 |
| Vocabulary constants in interfaces | ✅/🟡 | Section 8.6 — 3 missing |
| No Yoda conditions | ✅ | Section 8.7 |
| All imports present | ✅ | Section 8.8 |
| `new` only in factories/builders | ✅ | Section 8.9 (value objects exempt) |
| Declaration ordering followed | 🟡 | Section 8.10 — 2 violations |
| One class per file | 🟡 | Section 8.11 — 2 test files violate |
| No lines > 120 chars | ✅ | Section 8.12 |
| Test coverage ≥ 95% | 🔴 | Section 9.1 — 52.5% |
| No coverage leakage | ✅ | Section 9.5 |
| Hexagonal architecture | ✅ | Section 7 |
| Ports/adapters pattern | ✅ | Section 7.2-7.3 |
| Factories and builders | ✅ | Section 11 |
| Modern PHP features (Fibers, WeakMap, readonly, enums) | ✅ | Section 10 |

---

## 15. Recommendations (Prioritized)

### Immediate (High Priority)

1. **Extract shared poller base** — Create a `StreamSelectPoller` trait or abstract in `Application/Poller/` providing `await()`, `activeHandleCount()`, handle tracking, and the nanosecond conversion. Adapters override only `registerWithReactor()` and expose a prefix. This eliminates ~7× duplication and removes the magic numbers.

2. **Extract shared context storage** — Create a `CoroutineContextStorage` trait in `Domain/` providing `current()`, `attach()`, `detach()`, `slot()`, `fallback()`, `blank()`. Adapters implement only a `coroutineId(): int` hook.

3. **Consolidate context tokens** — Replace `SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`, and `MemoryContextToken` with a single `AsyncIt\Contract\Port\ContextToken` value object (or an `AsyncIt\Domain\ContextToken`).

4. **Add missing test coverage** — Write dedicated tests for `Task`, `TaskId`, `StreamPollHandle`, `CancellationScope`, `EmulatedChannel`, `CapabilityDescriptor`, and `MemoryContextStorage` error paths. Target: bring line coverage from 52% → 95%.

### Short-Term (Medium Priority)

5. **Add `RuntimeAdapterVocabulary` and `ViolationSeverityVocabulary`** interfaces and bind the enum cases to their constants (matching the pattern used by `TaskStatusVocabulary`, `CapabilityVocabulary`, etc.).

6. **Fix declaration ordering** in `AsyncRuntime.php` and `AsyncRuntimeFactory.php` — move static methods after non-static methods.

7. **Split multi-class test files** — Extract `FakeNativePoll` and `FakeReactLoop` into their own files under `tests/Unit/.../Support/` or similar.

8. **Decompose `RuntimeAdapterAssembler`** — Split into per-runtime assemblers implementing a common interface to reduce efferent coupling from 23 to ~6-8 per assembler.

### Long-Term (Low Priority)

9. **Optimize `FiberTaskScheduler::run()`** — Replace the full-iteration + `array_filter` loop with a ready-queue (`SplPriorityQueue`) and separate pending set to avoid O(n²) scheduling.

10. **Optimize `EmulatedChannel::receive()`** — Replace `array_shift()` with a circular buffer or `Ds\Queue` for O(1) dequeue.

11. **Align `EngineCapability` construction** with the `CapabilityDescriptor` — map capability statuses to `supportsCoroutines`/`concurrent` rather than hardcoding `false`.

12. **Add `@api` tags** to all public contracts that are missing them.

13. **Update README** — either implement `spawn()`/`awaitReadable()` or revise the example to match the current public API (`run()`, `dispatch()`).
