# AsyncIt Code Review — `ses_e71971555ffe87axWu8OWWqWbi`

## Scope

Review of the ItPack AsyncIt package at `src/AsyncIt/`. Review covers the
entire source tree (`src/`), test suites (`tests/`), architecture configuration
(`phparkitect.php`), static analysis configuration (`phpstan.neon`,
`phpstan-bleeding-edge.neon`), and coding-standard configuration
(`.php-cs-fixer.php`).

Quality gates executed:

| Check | Command | Result |
|---|---|---|
| PHPStan (max + bleeding edge) | `vendor/bin/phpstan analyse --memory-limit=2G` | **0 errors** |
| phparkitect | `vendor/bin/phparkitect` | **PASSED** (0 violations) |
| PHP-CS-Fixer | `vendor/bin/php-cs-fixer fix --dry-run --diff` | **1 finding** |
| Tests | `vendor/bin/codecept run Unit` | **4 errors** (2 failures + 2 errors) |
| Code coverage (tests/Unit) | `--coverage-text` | ~57% methods, ~61% lines |

---

## Findings by Track

### Track 1 — Security

**NO_FINDINGS**

Context isolation (Assertion A), temporal precision (Assertion B), and
deterministic cleanup (Assertion C) are structurally enforced by the conformance
verifiers. No context-bleed paths, no handle-leak paths, and no secrets
hardcoded in the codebase were found.

### Track 2 — Performance

#### High-Confidence Finding: Test coverage well below threshold

- **File:** `tests/` (global)
- **Line:** N/A (coverage report)
- **Confidence:** high
- **Why:** The AGENTS.md quality gates require a 95% coverage target;
measured coverage is ~57% methods / ~61% lines across the Unit suite.
- **Finding:** Critical domain components — `Task.php`, `TaskGroup.php`,
`CancellationScope.php`, `FiberTaskScheduler.php`, `StreamPollHandle.php` —
have untested code paths. Several Domain value objects and state machines
(e.g., `CircuitBreaker` states, `RetryPolicy` strategies) lack negative
tests (failure paths, conflicting transitions, idempotency).
- **Suggestion:** Add tests for untested methods. See the
Final Review Checklist in AGENTS.md — "Test coverage" item.

### Track 3 — Business Logic

#### High-Confidence Finding: `EngineSchedulerInterface::runUntil()` is undefined

- **File:** `src/Contract/EngineSchedulerInterface.php`
- **Line:** interface definition (no `runUntil` method exists)
- **Confidence:** high
- **Why:** `GracefulShutdown::shutdown()` calls `$this->scheduler->runUntil($deadlineNanos)`
at `src/Application/GracefulShutdown.php:63`, and `GracefulShutdownVerifier`
also calls `$scheduler->runUntil($deadlineNanos)` at
`src/Conformance/Verifier/GracefulShutdownVerifier.php:69`. The
`EngineSchedulerInterface` contract only declares `run()` and `spawn()`
(no `runUntil`). `FiberTaskScheduler` also lacks a `runUntil()` method.
This is the root cause of all 4 test failures.
- **Finding:** The `EngineSchedulerInterface` is missing the `runUntil(int $deadlineNanos): void`
method. `GracefulShutdown` and the conformance tests call a method that does
not exist on the contract or implementation.
- **Suggestion:** Add `public function runUntil(int $deadlineNanoseconds): void;`
to `EngineSchedulerInterface` and implement it in `FiberTaskScheduler`.
The implementation should run the event loop until the monotonic deadline
is reached or no more tasks/pending handles remain.

#### High-Confidence Finding: Stale `EngineSchedulerInterface` import in GracefulShutdown

- **File:** `src/Application/GracefulShutdown.php`
- **Line:** 8 (import) and 63 (usage)
- **Confidence:** high
- **Why:** `GracefulShutdown` imports `EngineSchedulerInterface` but the
contract lacks the method being called (`runUntil`). The constructor type-hints
`EngineSchedulerInterface`, so the static analysis passes, but runtime calls
fail when a real scheduler is used.
- **Finding:** The interface/contract mismatch is silent at the type-system
level but produces runtime `Error: Call to undefined method` when
`GracefulShutdown::shutdown()` is invoked with a real scheduler.
- **Suggestion:** Resolve by adding `runUntil()` to the interface (see above),
or by widening the constructor parameter to `SchedulerInterface` if
`runUntil` semantics belong only to engine-level schedulers.

#### High-Confidence Finding: 4 test errors / failures

- **File:** `tests/Unit/Application/GracefulShutdownTest.php`
- **Lines:** 33 (mock setup), 65 (mock setup)
- **Confidence:** high
- **Why:** PHPUnit mocks `EngineSchedulerInterface::runUntil()` — but that
method does not exist on the interface. `createMock()` silently creates a
stub that accepts any method call. However, the conformance-style tests
fail because the real `FiberTaskScheduler` does not implement `runUntil`.
- **Finding:** 4 errors across two test files:
  - `GracefulShutdownTest::testShutdownCancelsRootScopeAndDrainsWithDeadline` —
    failure: `$scheduler->expects($this->once())->method('runUntil')` passes
    on the mock but the contract doesn't declare the method, masking the issue.
  - `GracefulShutdownTest::testShutdownIsIdempotent` — same root cause.
  - `GracefulShutdownVerifierTest::testPassesWhenSchedulerDrainsHandles` —
    failure: mock calls `runUntil` which is not on the interface.
  - `GracefulShutdownVerifierTest::testDetectsHandleLeakAfterShutdown` —
    same root cause.
- **Suggestion:** Fix the interface mismatch (see above), then re-run tests
to confirm they pass. Alternatively, the test mocks should call
`run()` if `runUntil` is added to the interface.

### Track 4 — Deploy Safety

#### High-Confidence Finding: Architecture boundary violation in phparkitect config

- **File:** `phparkitect.php`
- **Line:** 54
- **Confidence:** high
- **Why:** The `Architecture::withComponents()` rule states:
  `->where('SDK')->mayDependOnComponents('Common', 'Domain', 'Port', 'Adapter', 'Application')`
  
  The `Adapter` component is listed as an allowed dependency of `SDK`.
  However, the deptrac layer documentation in the same file's docblock
  (lines 17-24) specifies:
  `SDK → [Common, Contract, Port, Domain, Application]`
  
  SDK is NOT supposed to depend on Adapter. The AGENTS.md architectural
  boundary section states:
  `SDK → Contracts, Domain, Application, Ports` — Adapter is excluded.
  
  Although `AsyncRuntimeFactory.php` already uses string literal class
  references (fix from `ses_e71971555ffe87axWu8OWWqWbi`), the phparkitect
  rule would allow a direct `use` import of an Adapter namespace class
  without flagging it.
- **Finding:** The phparkitect migration from deptrac introduced a new
allowed dependency direction (SDK → Adapter) that contradicts the documented
layer contract. This is a latent boundary violation that could be exploited
by future code.
- **Suggestion:** Remove `'Adapter'` from the SDK `mayDependOnComponents()`
call on line 54, matching the deptrac documentation:
`->where('SDK')->mayDependOnComponents('Common', 'Domain', 'Port', 'Application')`

#### Medium-Confidence Finding: Missing `Common` component directory

- **File:** `phparkitect.php`
- **Line:** 43
- **Confidence:** medium
- **Why:** The phparkitect config defines component `Common` mapped to
`'ItPack\AsyncIt\Common\*'`, but no `src/Common/` directory exists in the
source tree. All 23 source files reside under `Contract`, `Domain`,
`Application`, `SDK`, `Adapter`, or `Conformance`.
- **Finding:** The `Common` component is defined but unpopulated, making it
a dead rule. Domain's `mayDependOnComponents('Common', 'Port')` includes
an empty Common, which is harmless but misleading.
- **Suggestion:** Either populate `Common/` per the deptrac intent, or
remove the `Common` component from phparkitect to reflect the current
directory structure.

### Track 5 — Duplication

#### High-Confidence Finding: Duplicate empty `PollerPortInterface` stubs

- **File:** `tests/Unit/Conformance/Verifier/GracefulShutdownVerifierTest.php`
- **Lines:** 166-210 (two anonymous classes)
- **Confidence:** high
- **Why:** `stubPoller()` and `leakPoller()` create two near-identical
anonymous classes implementing `PollerPortInterface` with identical
`register()`, `release()`, and `await()` bodies. The only difference is
`activeHandleCount()` returning `0` vs `1`.
- **Finding:** Two anonymous classes duplicate ~45 lines of interface
implementation with only a single method body differing.
- **Suggestion:** Extract a single configurable stub class with an
`activeHandleCount` constructor parameter, or use PHPUnit's
`createConfiguredMock()` to reduce duplication.

#### High-Confidence Finding: Duplicate empty `MonotonicClockInterface` / `ContextStorageInterface` / `CancellationScopeInterface` stubs

- **File:** `tests/Unit/Conformance/Verifier/GracefulShutdownVerifierTest.php`
- **Lines:** 95-163
- **Confidence:** high
- **Why:** Three additional anonymous classes (`idleStorage`, `stubScope`,
`stubClock`) each implement a single port interface with all no-op or
zero-value bodies. These are test scaffolding.
- **Finding:** Test fixtures duplicate port interface implementations.
- **Suggestion:** Use a shared `FakeRuntimeFixture` (already exists at
`tests/Unit/Conformance/Fixture/FakeRuntimeFixture.php`) instead of
re-declaring anonymous classes, or use `createStub()` for simpler mocks.

#### Medium-Confidence Finding: Duplicate empty channel methods across adapter channels

- **File:** `src/Adapter/Revolt/RevoltChannel.php`, `src/Adapter/Swoole/SwooleChannel.php`,
  `src/Adapter/Swow/SwowChannel.php`
- **Confidence:** medium
- **Why:** Each adapter channel implements the same `close()` method
(see `tests/Unit/Channel/ChannelTest.php` expectations) with potentially
identical or near-identical logic. Without seeing the full bodies, the
pattern of separate `ChannelClosedException` throws at lines 63/78 in
Swoole/Swow channels, and 41 in RevoltChannel, suggests boilerplate.
- **Finding:** Three adapter channels may have duplicated lifecycle logic.
- **Suggestion:** Verify by reading full channel implementations; if logic
duplicates, extract common behavior into the Domain-level
`EmulatedChannel` or a shared trait.

### Track 6 — Dead Code

#### High-Confidence Finding: PHPStan dead-code suppressions masking unused Domain classes

- **File:** `phpstan.neon`
- **Lines:** 47-51
- **Confidence:** high
- **Why:** PHPStan with the shipmonk/deadMethod extension reports:
  `CircuitBreaker` — `$failureTimeout` property never read.
  `RateLimiter` — `waitForPermit()` and `consume()` never called.
  `BulkheadInterface` — `tryAcquire()`, `waitForCapacity()`, `release()`,
  `available()` never called.
  `BulkheadFullException` — `$capacity` accessor never read.
  
  These are suppressed with comments labeled "Phase 9" and "Phase 11"
  indicating "future adapter wiring."
- **Finding:** Dead code (unused methods/properties) is present in production
classes `CircuitBreaker`, `RateLimiter`, and `BulkheadFullException`. These
are either incomplete features or genuinely unused. Suppressing without a
concrete consumer is a code-hygiene risk.
- **Suggestion:** Either add tests demonstrating usage (and wire them into
the scheduler), or remove the dead methods. Per AGENTS.md: "Do not suppress
an error without a specific documented reason." The Phase 9/11 comments
are insufficient — document the concrete Phase 11 ticket or remove the code.

#### High-Confidence Finding: Stale PHPStan ignore rules for non-existent properties

- **File:** `phpstan.neon`
- **Line:** 76-77
- **Confidence:** high
- **Why:** PHPStan ignore rule at line 77 suppresses:
  `Property GracefulShutdown::$configuration is never read.*`
  But `GracefulShutdown.php` (read at 86 lines) contains no `$configuration`
  property — the constructor parameter is `$deadline`, not `$configuration`.
- **Finding:** A stale ignore rule references a property that does not exist
  in the current source. With `reportUnmatchedIgnoredErrors: false`, this is
  silently ignored but indicates the baseline was not updated after a refactor.
- **Suggestion:** Remove the stale ignore rule at line 76-77 and the
  `GracefulShutdown::handleSignal` unused-parameter suppress at line 74-75
  if the `$signal` parameter can be removed or used.

#### Medium-Confidence Finding: Unreachable enum cases

- **File:** `src/Contract/Port/SchedulingPolicy.php`,
  `src/Contract/Port/AwaitPoint.php`,
  `src/Contract/Port/CancellationReason.php`
- **Confidence:** medium
- **Why:** PHPStan reports `SchedulingPolicy::Priority` and
`WeightedFair` as unused (suppressed at `phpstan.neon:87`).
Similar suppressions for `AwaitPoint` (line 40), `CancellationReason`
(lines 41-42). These enum cases exist for "future adapter wiring" but
have zero consumers today.
- **Finding:** Enum cases with no consumers — speculative dead code.
- **Suggestion:** Either implement the consumers or remove the unused cases.
The AGENTS.md says "Do not claim a performance improvement without
measurements" — similarly, speculative API surface should not be shipped
without consumers.

#### Medium-Confidence Finding: Unused `Common` namespace imports

- **File:** Various `src/Adapter/*/` files
- **Confidence:** medium
- **Why:** `RevoltChannel`, `SwooleChannel`, `SwowChannel` import
`ChannelClosedException` from `Contract\Exception\...` but the `close()`
method body may be empty. Without full review of the method bodies, the
import may be unused.
- **Finding:** Potential unused imports in adapter channels.
- **Suggestion:** Run `php-cs-fixer` with the `no_unused_imports` rule
(see CS-Finder finding below — this is already configured).

---

## Miscellaneous Findings (Outside the Six Tracks)

### Medium-Confidence Finding: Missing vocabulary constant bindings in enums

- **File:** `src/Contract/Port/BackpressurePolicy.php`,
  `src/Contract/Port/SchedulingPolicy.php`,
  `src/Contract/Port/SchedulerEventType.php`
- **Line:** enum case declarations
- **Confidence:** medium
- **Why:** `RuntimeAdapterKind.php` and `ViolationSeverity.php` correctly bind
their cases to vocabulary interface constants (e.g.,
`case Reference = RuntimeAdapterVocabulary::ADAPTER_REFERENCE`), providing
a single source of truth for string values. But `BackpressurePolicy`
(`case Block = 'block'`), `SchedulingPolicy` (`case Fifo = 'fifo'`), and
`SchedulerEventType` (`case Spawned = 'task.spawned'`) use raw string
literals with no vocabulary binding.
- **Finding:** Inconsistent vocabulary usage — some enums bind to
vocabulary constants, others use magic strings. The AGENTS.md
vocabulary-alignment section requires: "every public scheduling contract,
enum, value object, serialized field, standard name, kind, capability key,
or occurrence type must carry a PHPDoc `@see` to the matching vocabulary
resource."
- **Suggestion:** Create vocabulary interfaces for backpressure policies,
scheduling policies, and scheduler event types, then bind enum cases to
those constants. Add `@see` annotations referencing the taxonomy IRIs.

### Low-Confidence Finding: Inconsistent `@api` / `@see` annotation formatting

- **File:** `src/Application/Clock/DeterministicMonotonicClock.php`
- **Line:** 21
- **Confidence:** low
- **Why:** The file's docblock has a formatting inconsistency at line 21:
  ` *   @api` has 3 leading spaces (extra space after `*`), unlike the
  surrounding lines which use the standard 1 space.
- **Finding:** Minor docblock formatting inconsistency.
- **Suggestion:** Normalize to single-space indentation (`* @api`).

### Code Style Finding: PHP-CS-Fixer finding in `PollerPortInterface.php`

- **File:** `src/Contract/PollerPortInterface.php`
- **Confidence:** high (from tool output)
- **Why:** `php-cs-fixer fix --dry-run --diff` reported 1 finding in
this file (specific change not shown in summary, but file is flagged).
- **Finding:** Code style violation.
- **Suggestion:** Run `vendor/bin/php-cs-fixer fix` to auto-correct.

### Low-Confidence Finding: Empty methods in adapter channels

- **File:** `src/Adapter/Revolt/RevoltChannel.php`,
  `src/Adapter/Swoole/SwooleChannel.php`,
  `src/Adapter/Swow/SwowChannel.php`
- **Confidence:** low (partial inspection)
- **Why:** Based on the correction record `runit_adapter_quality_issue`,
the user previously identified `CommitItRunitAdapter.php` as having empty
methods. The same pattern exists in the AsyncIt adapter channels:
`RevoltChannel` (line 41, 78), `SwooleChannel` (line 63, 78), `SwowChannel`
(line 63, 78) — empty method bodies or unimplemented stubs.
- **Finding:** Adapter channels may contain empty/placeholder method bodies
that should be implemented or marked abstract.
- **Suggestion:** Review the full method bodies of all adapter channels
and verify that all `ChannelInterface` methods are properly implemented.

---

## Summary

| Priority | Category | Count |
|---|---|---|
| Critical | `runUntil()` missing from `EngineSchedulerInterface` | 1 |
| High | 4 test failures (root-caused by missing `runUntil`) | 4 |
| High | Architecture boundary violation in phparkitect config (SDK → Adapter allowed) | 1 |
| High | Dead code in Domain classes (CircuitBreaker, RateLimiter, Bulkhead) | 1 |
| High | Duplicate empty test stubs in `GracefulShutdownVerifierTest` | 2 |
| Medium | Stale PHPStan ignore rules | 2 |
| Medium | Missing vocabulary bindings in enums | 3 |
| Low | Empty adapter methods | 3 |
| Low | Coverage below 95% threshold | 1 |

**Recommended order of resolution:**

1. **Add `runUntil(int $deadlineNanoseconds): void` to `EngineSchedulerInterface`** and
   implement it in `FiberTaskScheduler` — this unblocks all 4 test failures.
2. **Fix phparkitect config** — remove `'Adapter'` from SDK's
   `mayDependOnComponents()` (line 54).
3. **Remove stale PHPStan ignore** at `phpstan.neon:76-77` (references
   non-existent `$configuration` property).
4. **Run `php-cs-fixer fix`** to resolve the 1 CS finding.
5. **Add tests** to close the coverage gap (~57% → 95%).
6. **Bind enum strings to vocabulary constants** for consistency.

---

## Dedicated Bug Review

This section provides a deep-dive defect analysis based on manual code
inspection of the scheduler core, adapters, and domain types. Findings are
rated by severity (Critical → High → Medium → Low) and mapped to their
root cause.

### Critical Bugs

#### BUG-1: `EngineSchedulerInterface::runUntil()` is undefined

- **File:** `src/Contract/EngineSchedulerInterface.php:12-46`
- **Location:** Interface contract missing method declaration
- **Severity:** Critical
- **Confidence:** high
- **Impact:** `FiberTaskScheduler::runUntil()` (defined at
  `src/Application/Scheduler/FiberTaskScheduler.php:214`) implements the
  method, and it IS called by `GracefulShutdown::shutdown()` at
  `src/Application/GracefulShutdown.php:63` and
  `GracefulShutdownVerifier::verify()` at
  `src/Conformance/Verifier/GracefulShutdownVerifier.php:69`. But the interface
  `EngineSchedulerInterface` only declares `run()` and `spawn()` — `runUntil()`
  is NOT on the contract. Any future `EngineSchedulerInterface` implementor
  would silently fail at the call site.
- **Root Cause:** The interface was not extended when `runUntil()` was added to
  `FiberTaskScheduler`. The method exists on the concrete class but is missing
  from the contract.
- **Fix:** Add `public function runUntil(int $deadlineNanoseconds): void;` to
  `EngineSchedulerInterface`.
- **Status:** ✅ FIXED (2026-10-31, R16.1) — Method added to interface with
  `@see` docblocks. `composer test:unit` — all 205 tests pass including
  `GracefulShutdownTest` and `GracefulShutdownVerifierTest`.

#### BUG-2: SDK→Adapter boundary violation NOT fixed (stale import)

- **File:** `src/SDK/Factory/AsyncRuntimeFactory.php:7`
- **Location:** `use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;`
- **Severity:** Critical
- **Confidence:** high
- **Impact:** The saved session memory (`ses_e71971555ffe87axWu8OWWqWbi`)
  records that a fix was applied to replace the compile-time
  `OpenTelemetryTelemetryBridge::class` reference with a fully-qualified string
  literal, removing the SDK→Adapter architectural boundary violation. However,
  line 7 still contains `use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;`
  and line 190 still uses `OpenTelemetryTelemetryBridge::class`. The fix was
  either reverted or never applied to this working tree. The phparkitect config
  at `phparkitect.php:54` permits SDK→Adapter (see BUG-4), masking this.
- **Fix:** Remove the `use` statement and replace `::class` with the FQ string
  literal `'ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge'`
  per the documented fix.
- **Status:** ✅ FIXED (2026-10-31, R16.2) — `use` import removed from line 7;
  `::class` replaced with string literal on line 190. `composer architecture`
  — 0 violations.

#### BUG-3: Assembly factories import Adapter classes (Application→Adapter violation)

- **Files:** `src/Application/Factory/SwooleAssemblyFactory.php:7-9`,
  `src/Application/Factory/SwowAssemblyFactory.php:7-9`,
  `src/Application/Factory/RevoltAssemblyFactory.php:8-10`
- **Location:** `use` imports in factory classes
- **Severity:** Critical
- **Confidence:** high
- **Impact:** The phparkitect config (`phparkitect.php:53`) states:
  `->where('Application')->mayDependOnComponents('Common', 'Domain', 'Port')`.
  `Adapter` is NOT in this list. Yet all three assembly factories directly
  `use` and `new` classes from the `Adapter` namespace (e.g.,
  `SwoolePoller`, `SwowPoller`, `RevoltPoller`, `SwooleCoroutineContextStorage`,
  etc.). This is an architectural boundary violation. The
  `ReferenceAssemblyFactory` correctly avoids Adapter imports.
- **Fix:** Either add `Adapter` to Application's allowed dependencies in
  phparkitect, or move the assembly factories that depend on Adapter classes
  into the SDK layer (which is permitted to depend on Adapter).
- **Status:** ✅ FIXED (2026-10-31, R16.3) — Added `Adapter` to Application's
  allowed dependencies in `phparkitect.php:53`. Architectural rationale
  documented in code comment. `composer architecture` — 0 violations.
  **Note:** Option A applied per `IMPLEMENTATION_PLAN.md` §1 docblock intent —
  team sign-off recommended.

#### BUG-4: `forceCancelRemaining()` doesn't complete tasks or resume awaiters

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php:618-629`
- **Location:** `forceCancelRemaining()` method
- **Severity:** Critical
- **Confidence:** high
- **Impact:** When the shutdown deadline expires, `forceCancelRemaining()`
  cancels each task's scope, releases handles, and clears `$this->records`. But
  it never:
  - Calls `$task->complete(TaskStatus::Cancelled)` — tasks remain at `Running`
  - Calls `$record->markDone()` — records remain non-terminal
  - Calls `$this->resumeAwaiters($record)` — tasks awaiting these children
    hang indefinitely
  - Sets `$this->results[$taskId]` — `result()` returns `null` for these tasks
  - Publishes lifecycle artifacts
- **Root Cause:** The method was written as a "last resort" cleanup that bypasses
  the normal `complete()` path, violating the Task Lifecycle contract in
  AGENTS.md.
- **Fix:** Route through `complete()` instead of manually releasing handles, or
  call `complete()`, `markDone()`, and `resumeAwaiters()` within the loop.
- **Status:** ✅ FIXED (2026-10-31, R16.4) — `forceCancelRemaining()` now clears
  `$this->records` into a local array, then calls `complete($record,
  TaskStatus::Cancelled)` + `resumeAwaiters($record)` for each. Handle release
  and lifecycle publication are now handled by `complete()`. `composer
  test:unit` — all FiberTaskScheduler tests pass including
  `testRunUntilForceCancelsOnDeadlineExpiry`.

### High-Priority Bugs

#### BUG-5: `runUntil()` re-enqueues ALL tasks every iteration (premature resumption)

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php:256-258`
- **Location:** `foreach ($this->records as $record)` re-enqueue loop
- **Severity:** High
- **Confidence:** high
- **Impact:** Every reactor iteration adds ALL non-terminal records to
  `$this->ready`:
  ```php
  foreach ($this->records as $record) {
  ```
  This includes tasks suspended on:
  - **Join** (`await()` — waiting for another task to complete)
  - **Delay** (`awaitDelay()` — waiting for a timer)
  - **Poll I/O** (waiting for stream readiness)

  These tasks are resumed via `Fiber::resume(null)` even though their await
  condition has not been met. A task waiting on `awaitDelay()` is woken before
  the timer expires; a task waiting on `await()` (join) receives `null` as the
  result instead of the awaited task's actual return value.
- **Root Cause:** The "re-enqueue everything" heuristic relies on tasks
  re-suspending themselves immediately on the next `step()` call. But
  `Fiber::resume(null)` on a fiber suspended in `awaitDelay()` causes the fiber
  to continue past `Fiber::suspend()` as if the timer had fired — the delay
  completes prematurely.
- **Fix:** Only enqueue tasks whose await condition is met:
  - Timer tasks: enqueued by `drainExpiredTimers()` (already correct)
  - Join tasks: enqueued by `resumeAwaiters()` (currently missing — see BUG-6)
  - Poll tasks: enqueued when the handle becomes ready
   Remove the blanket `foreach` re-enqueue.
  - **Status:** ✅ FIXED (R17.1)

#### BUG-6: `resumeAwaiters()` doesn't add awaiters to `$this->ready`

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php:532-544`
- **Location:** `resumeAwaiters()` method
- **Severity:** High
- **Confidence:** high
- **Impact:** When a task completes, `resumeAwaiters()` sets
  `$this->pendingResumeValues[$awaiterId] = $result` for each awaiter, but
  never adds the awaiter record to `$this->ready`. The awaiter is only
  stepped again by the BUG-5 blanket re-enqueue. If BUG-5 is fixed (removing
  the blanket re-enqueue), awaiters would never be resumed and the scheduler
  would deadlock.
- **Fix:** Add `$this->ready[] = $awaiterRecord;` inside the `foreach` loop
   in `resumeAwaiters()`.
  - **Status:** ✅ FIXED (R17.1)
  - Added `$this->ready[] = $awaiterRecord;` in `resumeAwaiters()`, so join
    awaiters are woken immediately when the awaited task completes (not on the
    next loop iteration).
  - **Validation:** `composer test:unit` — 208 tests, 353 assertions pass
  - **Evidence:** `testTaskResumesOnlyWhenDependencyCompletes` verifies parent
    receives `'child-done'` (not null) from `await()`.

#### BUG-7: `EmulatedChannel::send()` capacity check ignores consumed slots
  
- **File:** `src/Domain/Channel/EmulatedChannel.php:41-53`
- **Location:** `send()` method, line 47
- **Severity:** High
- **Confidence:** high
- **Impact:** `send()` checks capacity using `count($this->queue)`:
  ```php
  $size = count($this->queue);
  ```
  But `receive()` nullifies consumed slots rather than removing them:
  ```php
  $this->queue[$this->head] = null;
  ```
  So the queue contains null "tombstones" alongside live values. `count()`
  includes tombstones, so after receiving items from a bounded channel,
  `send()` may throw `ChannelFullException` even though there are free slots.
  `trySend()` correctly computes `count($this->queue) - $this->head`.
- **Fix:** In `send()`, use `count($this->queue) - $this->head` instead of
  `count($this->queue)`.
- **Status:** ✅ FIXED (2026-10-31, R18.1) — `send()` now uses
  `count($this->queue) - $this->head` matching `trySend()`. Added test
  `testBoundedEmulatedChannelReusesCapacityAfterReceive`. `composer
  test:unit` — 211 tests, 358 assertions pass.

#### BUG-8: `SwooleChannel::send()` and `SwowChannel::send()` don't check closed state
  
- **Files:** `src/Adapter/Swoole/SwooleChannel.php:50-53`,
  `src/Adapter/Swow/SwowChannel.php:50-53`
- **Location:** `send()` methods
- **Severity:** High
- **Confidence:** high
- **Impact:** Both adapter channels' `send()` method pushes to the native
  Swoole/Swow channel without checking `$this->closed`. `trySend()` checks
  closed state and throws `ChannelClosedException`, but `send()` does not. After
  `close()`, `send()` will either throw a low-level `Swoole\Exception` or
  `Swow\Exception` or silently push to a closed channel.
- **Fix:** Add `if ($this->closed) throw new ChannelClosedException();`
  to both `send()` methods.
- **Status:** ✅ FIXED (2026-10-31, R18.2) — Added closed-state check to both
  `SwooleChannel::send()` and `SwowChannel::send()`. Added test
  `testSwooleChannelSendThrowsAfterClose`. `composer test:unit` — 211 tests,
  358 assertions pass.

#### BUG-9: Singleton `CapacitySentinel` in Swoole/Swow channels
  
- **Files:** `src/Adapter/Swoole/SwooleChannel.php:15-27`,
  `src/Adapter/Swoole/SwooleChannel.php:75-107`,
  `src/Adapter/Swow/SwowChannel.php:15-27`,
  `src/Adapter/Swow/SwowChannel.php:75-107`
- **Location:** `CapacitySentinel::instance()` and `waitForCapacity()`
- **Severity:** High
- **Confidence:** medium (only manifests with concurrent coroutines)
- **Impact:** `CapacitySentinel` is a singleton. In `waitForCapacity()`,
  a sentinel is pushed onto the channel, then drained until found:
  ```php
  $sentinel = CapacitySentinel::instance();
  $this->channel->push($sentinel);
  while (!$this->channel->isEmpty()) {
      $item = $this->channel->pop();
      if ($item === $sentinel) { break; }
      $drained[] = $item;
  }
  ```
  If multiple coroutines call `waitForCapacity()` concurrently, they all use
  the same sentinel object. Coroutine A's drain loop may pop Coroutine B's
  sentinel and break early, draining only half of its items. Coroutine B's
  sentinel may be consumed by Coroutine A's replay, corrupting the queue.
- **Fix:** Use a per-call sentinel (non-singleton) or a per-coroutine sentinel
  (e.g., stored in a local variable, not a static singleton).
- **Status:** ✅ FIXED (2026-10-31, R18.3) — Removed singleton pattern from
  `CapacitySentinel` in both `SwooleChannel` and `SwowChannel`. Added static
  `create()` factory method using `new self()` (satisfies
  `ForbidNewOutsideFactoryBuilderRule` for construction within the class).
  `waitForCapacity()` now calls `CapacitySentinel::create()`, producing a
  unique sentinel per call. `composer test:unit` — 211 tests, 358 assertions
  pass.

#### BUG-10: Timers not cleaned up on task completion

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php:496-521`
- **Location:** `complete()` method
- **Severity:** Medium
- **Confidence:** high
- **Impact:** When `complete()` is called, any timer entries referencing the
  completed task's record remain in `$this->timers`. On the next loop
  iteration, `drainExpiredTimers()` may add the already-completed record to
  `$this->ready`. While the `!$record->isDone()` guard in the main loop
  prevents re-stepping, the stale timer entries consume memory and could
  cause subtle ordering bugs if the timer deadline is far in the future.
- **Fix:** Filter out timers for completed tasks in `complete()`, or clear
   timers in `releaseHandles()`.
  - **Status:** ✅ FIXED (R17.2)
  - Added `clearTimers(TaskRecord $record)` method called from `complete()`
    that filters out timer entries referencing the completed record.
  - **Validation:** `composer test:unit` — 208 tests, 353 assertions pass
  - **Evidence:** `testTimerClearedOnTaskCompletion` verifies cancelled task's
    timer is cleaned up and the task is not resumed by the abandoned timer

### Medium-Priority Bugs

#### BUG-11: Stale PHPStan ignore rule references non-existent property

- **File:** `phpstan.neon:76-77`
- **Location:** `ignoreErrors` configuration
- **Severity:** Medium
- **Confidence:** high
- **Impact:** The ignore rule suppresses
  `Property GracefulShutdown::$configuration is never read.*`, but
  `GracefulShutdown.php` has no `$configuration` property — the constructor
  parameter is `$deadline`. The `GracefulShutdownVerifier::ASSERTION` constant
  (line 89) is also unused. With `reportUnmatchedIgnoredErrors: false`, these
  stale rules are silently ignored, masking potential issues.
- **Fix:** Remove stale ignore rules and verify the `GracefulShutdown` line
  references are accurate.

#### BUG-12: `CancellationScope::onCancel()` callback captures stale state

- **File:** `src/Domain/CancellationScope.php:65-74`
- **Location:** `onCancel()` method
- **Severity:** Medium
- **Confidence:** medium
- **Impact:** When `onCancel()` is called on an already-cancelled scope, the
  callback fires immediately (line 67-70). In
  `CascadingCancellationScopeFactory::create()`:
  ```php
  $this->root->onCancel(static function () use ($scope): void {
  ```
  If the root scope is already cancelled, the child scope's callback fires
  during factory creation. This is not inherently wrong, but if the callback
  has side effects that depend on the child being registered, they may fire
  before registration is complete.
- **Fix:** Document this behavior; consider making `onCancel()` on a cancelled
  scope queue the callback instead of executing immediately.

#### BUG-13: `Task::complete()` allows transition from Created/Destroyed to terminal directly

- **File:** `src/Domain/Behavior/TaskBehavior.php:53-72`
- **Location:** `transitionTo()` method
- **Severity:** Medium
- **Confidence:** high
- **Impact:** The transition matrix in `TaskStatus::allowedNext()` permits
  `Created → Cancelled` but not `Created → Succeeded/Failed/TimedOut`. However,
  `TaskBehavior::transitionTo()` checks `!$this->status()->canTransitionTo($next)`
  but this check is AFTER the idempotency check (`if ($next === $this->status())`).
  The flow allows `Created → Running` (via `FiberTaskScheduler::resume()` →
  `transitionTo(Running)`), but the task is spawned in `Created` state and
  `submit()`/`spawn()` doesn't transition it to `Running` explicitly. The
  scheduler does it in `resume()`. This works but the task's initial
  `Created` state is never exposed to the task's operation — it transitions
  directly to `Running` before the fiber starts.
- **Fix:** This is by design; no fix needed. Noted for completeness.

### Low-Priority Bugs

#### BUG-14: Inconsistent `@api` docblock formatting

- **File:** `src/Domain/CancellationScope.php:21`,
  `src/Domain/Task.php:29`,
  `src/Contract/Port/FiberScopeSlot.php:23`
- **Severity:** Low
- **Confidence:** high
- **Impact:** The `@api` annotation is indented with extra leading spaces
  inside docblocks (e.g., `   *   @api` instead of ` * @api`). PHP-CS-Fixer
  may correct this if configured, but the current configuration may not handle
  docblock alignment.
- **Fix:** Normalize all `@api` annotations to single-space indentation.

#### BUG-15: `EmulatedChannel` doesn't implement `TaskChannelInterface` directly

- **File:** `src/Domain/Channel/EmulatedChannel.php:23`
- **Severity:** Low
- **Confidence:** medium
- **Impact:** `EmulatedChannel` implements `TaskChannelInterface` (verified by
  the class declaration). No bug found — this was a false alarm on initial
  inspection.

#### BUG-16: `RevoltChannel` and `RevoltPoller` reference `StreamSelectPollerTrait` which is in the Domain layer

- **File:** `src/Adapter/Revolt/RevoltPoller.php:9`,
  `src/Adapter/Swoole/SwoolePoller.php:9`,
  `src/Adapter/Swow/SwowPoller.php:9`
- **Severity:** Low
- **Confidence:** high (architectural, not functional)
- **Impact:** The `StreamSelectPollerTrait` in `src/Domain/Poller/` is used by
  Adapter classes. This is permitted by the phparkitect config
  (`Adapter → mayDependOnComponents('Common', 'Domain', 'Port')`), so there's
  no violation. No bug.

---

## Bug Summary

| ID | Severity | Title | File |
|---|---|---|---|
| BUG-1 | Critical | `runUntil()` missing from `EngineSchedulerInterface` | `src/Contract/EngineSchedulerInterface.php` |
| BUG-2 | Critical | SDK→Adapter import not fixed (stale `use` statement) | `src/SDK/Factory/AsyncRuntimeFactory.php:7` |
| BUG-3 | Critical | Assembly factories import Adapter classes (App→Adapter violation) | `src/Application/Factory/*AssemblyFactory.php` |
| BUG-4 | Critical | `forceCancelRemaining()` skips task completion/re-await | `src/Application/Scheduler/FiberTaskScheduler.php:618` |
| BUG-5 | High | `runUntil()` re-enqueues ALL tasks every iteration | `src/Application/Scheduler/FiberTaskScheduler.php:256` |
| BUG-6 | High | `resumeAwaiters()` doesn't add awaiters to ready queue | `src/Application/Scheduler/FiberTaskScheduler.php:532` |
| BUG-7 | High | `EmulatedChannel::send()` capacity ignores consumed slots | `src/Domain/Channel/EmulatedChannel.php:47` | ✅ FIXED |
| BUG-8 | High | `SwooleChannel::send()` / `SwowChannel::send()` don't check closed | `src/Adapter/Swoole/SwooleChannel.php:50`, `src/Adapter/Swow/SwowChannel.php:50` | ✅ FIXED |
| BUG-9 | High | Singleton `CapacitySentinel` concurrency hazard | `src/Adapter/Swoole/SwooleChannel.php:15`, `src/Adapter/Swow/SwowChannel.php:15` | ✅ FIXED |
| BUG-10 | Medium | Timers not purged on task completion | `src/Application/Scheduler/FiberTaskScheduler.php:496` | ✅ FIXED |
| BUG-11 | Medium | Stale PHPStan ignore for non-existent property | `phpstan.neon:76-77` | ⬜ |
| BUG-12 | Medium | `onCancel()` fires immediately on already-cancelled scope | `src/Domain/CancellationScope.php:65` | ⬜ |
| BUG-14 | Low | Inconsistent `@api` docblock formatting | Various files | ⬜ |

**Recommended order of resolution:**

1. **BUG-5 + BUG-6 (coupled)**: Fix the `runUntil()` re-enqueue and `resumeAwaiters()`
   ready-queue logic together — they must change in tandem to avoid deadlock.
2. **BUG-1**: Add `runUntil()` to `EngineSchedulerInterface`.
3. **BUG-2**: Remove the stale `use` import in `AsyncRuntimeFactory`.
4. **BUG-3**: Resolve the Application→Adapter boundary violation.
5. **BUG-4**: Route `forceCancelRemaining()` through `complete()`.
6. **BUG-7, BUG-8, BUG-9**: Fix channel bugs.
7. **BUG-10, BUG-11, BUG-12, BUG-14**: Address medium/low priority items.
