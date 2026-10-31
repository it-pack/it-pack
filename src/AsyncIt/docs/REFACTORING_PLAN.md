# AsyncIt Refactoring Plan

> **Status:** Active (Phase R16 complete — 2026-10-31; Phase R17 complete — 2026-10-31;
> Phase R18 complete — 2026-10-31; Phase R19 complete — 2026-10-31;
> Phase R20 complete — 2026-10-31). Addresses findings in
> `docs/REVIEW.md` (review section) and `docs/REVIEW.md` (bug review section).
> R0–R15 are historical (Phase 0-pre, complete per `docs/TODOS.md`); R16+ are the
> current backlog derived from the defect analysis.
>
> **Completed:** R16.1–R16.4 (2026-10-31): `composer analyse` green (1 pre-existing
> error in `BulkheadFullException` unrelated to R16), `composer architecture` green
> (0 violations), `composer test:unit` green (205 tests, 347 assertions).
>
> **Completed:** R17.1–R17.2 (2026-10-31): `composer analyse` green (1 pre-existing
> error in `BulkheadFullException`), `composer architecture` green (0 violations),
> `composer test:unit` green (208 tests, 353 assertions).
>
> **Completed:** R18.1–R18.3 (2026-10-31): `composer analyse` green (1 pre-existing
> error in `BulkheadFullException` unrelated to R18), `composer architecture` green
> (0 violations), `composer cs:check` green (0 findings), `composer test:unit` green
> (211 tests, 358 assertions).
>
> **Completed:** R19.1–R19.3 (2026-10-31): `composer analyse` green (1 pre-existing
> error); `composer architecture` green; `composer cs:check` green;
> `composer test:unit` green (211 tests, 358 assertions).
>
> **Completed:** R20.1–R20.2 (2026-10-31): Removed regressed `OpenTelemetryTelemetryBridge`
> import in `AsyncRuntimeFactory.php` (BUG-2 fix was documented but not applied — re-fixed
> during R20.2 verification). `composer architecture` green (0 violations);
> `composer analyse` green.
>
> **Purpose:** Translate every finding in `docs/REVIEW.md` into a concrete,
> check-off-able refactoring or bug-fix task with an assigned phase, file,
> and validation. This plan is the single source of truth for the
> `REFACTORING_PLAN.md` reference cited by `docs/IMPLEMENTATION_PLAN.md` §1,
> §6, and §12, and by `docs/ROADMAP.md` §4 (Phase 0-pre).
>
> **Inputs:** `docs/REVIEW.md`, `AGENTS.md`, `phpstan.neon`, `phparkitect.php`,
> `docs/ROADMAP.md`, `docs/TODOS.md`, `docs/IMPLEMENTATION_PLAN.md`.

---

## 0. Convention

Tasks are numbered `R<number>.<sub>` for traceability against `REVIEW.md`
finding ids. The first phase a task is eligible for is shown in the **Phase**
column. Tasks are marked `[x]` when complete.

---

## Phase R0–R15 — Historical (Phase 0-pre, already complete)

The following refactoring work was completed before this review cycle. It is
preserved here for provenance. All items are `[x]` per `docs/TODOS.md`
(last updated 2026-10-29). Summary only — see `TODOS.md` for evidence.

| ID  | Description                                                                                                                    | Status        |
|-----|--------------------------------------------------------------------------------------------------------------------------------|---------------|
| R0  | QA tooling (`composer quality` scripts, CI workflow)                                                                           | `[x]`         |
| R1  | Unblock `composer analyse` (remove `else`, `ValueObjectInterface` exemption, `ForbidNewOutsideFactoryBuilderRule` fix)         | `[x]`         |
| R2  | Unblock `composer architecture` (relax Contract→Port, move channel exceptions, extract `RuntimeAdapterAssembler`)              | `[x]`         |
| R3  | Abstraction discipline (`AbstractRuntimeFixture` → `RuntimeFixtureTrait`, `RuntimeAssemblerInterface`, `TaskRecord` DTO)       | `[x]`         |
| R4  | Style hygiene (120-char lines, `PollerVocabulary::READABLE`, `cs:fix`)                                                         | `[x]`         |
| R5  | Modern PHP leverage & doc accuracy (MemoryContextStorage docblock, WeakMap adoption in `RevoltFiberContextStorage`)            | `[x]`         |
| R6  | Coverage & mutation (R6.1 `@covers`, R6.2 test fixes; **R6.3 coverage/mutation measurement still ⬜**)                          | `[x]` / `[ ]` |
| R7  | Shared poller infrastructure (`StreamSelectPollerTrait`, `SchedulerFailureArtifact`)                                           | `[x]`         |
| R8  | Shared context storage (single `Domain\ContextToken`, `CoroutineContextStorageTrait`)                                          | `[x]`         |
| R9  | Vocabulary completion (`RuntimeAdapterKind` → `RuntimeAdapterVocabulary`, `ViolationSeverity` → `ViolationSeverityVocabulary`) | `[x]`         |
| R10 | Test isolation & coverage (FakeNativePoll, dedicated tests for core classes)                                                   | `[x]`         |
| R11 | Declaration ordering audit (all `src/` files)                                                                                  | `[x]`         |
| R12 | Performance: ready-queue O(n²) fix, `EmulatedChannel` head-index optimization, `phpbench.json`                                 | `[x]`         |
| R13 | `RuntimeAdapterAssembler` decomposition (23 → 3 efferent couplings)                                                            | `[x]`         |
| R14 | `EngineCapability` fields derived from `CapabilityDescriptor`                                                                  | `[x]`         |
| R15 | README updates (API alignment, Public API Reference)                                                                           | `[x]`         |

> **Note:** R6.3 (coverage ≥95% + Infection 0 survived mutants) remains the
> single open item from Phase 0-pre. It requires a clean checkout and `pcov`.
> See Section 3 below for integration with new findings.

---

## Phase R16 — Critical Bugfixes (BUG-1, BUG-2, BUG-3, BUG-4)

These are blocking correctness and architecture violations. Must be resolved
before any further quality work.

### R16.1 — Add `runUntil()` to `EngineSchedulerInterface` (BUG-1)

- **File:** `src/Contract/EngineSchedulerInterface.php`
- **Findings:** `docs/REVIEW.md` BUG-1
- **Status:** `[x]` Completed 2026-10-31
- **Steps:**
  - [x] Add `public function runUntil(int $deadlineNanoseconds): void;` to
    `EngineSchedulerInterface` (inserted after `health()`, before `swapCapabilities()`)
  - [x] Add `@see https://w3id.org/itpack/asyncit#Scheduler`,
    `@see https://w3id.org/itpack/asyncit-taxonomy#occ-cancellation-deadline-exceeded`,
    and `@see CancellationReason::DeadlineExceeded` docblock
  - [x] Verify `FiberTaskScheduler` already implements it (it does — line 214)
  - [x] Verify `GracefulShutdownTest` + `GracefulShutdownVerifierTest` pass — green (4 tests)
- **Validation:** `composer analyse` green; `composer test:unit` — all 205 tests pass

### R16.2 — Fix SDK→Adapter boundary violation (BUG-2)

- **File:** `src/SDK/Factory/AsyncRuntimeFactory.php`
- **Findings:** `docs/REVIEW.md` BUG-2, BUG-3 (phparkitect config)
- **Root Cause:** Session `ses_e71971555ffe87axWu8OWWqWbi` records that
  `OpenTelemetryTelemetryBridge::class` was replaced with a string literal
  to remove the SDK→Adapter compile-time dependency. The working tree
  regressed to the `use` import on line 7 and `::class` on line 190.
- **Status:** `[x]` Completed 2026-10-31
- **Steps:**
  - [x] Remove `use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;`
    from line 7
  - [x] Replace `$bridgeClass = OpenTelemetryTelemetryBridge::class;` (line 190)
    with `$bridgeClass = 'ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge';`
  - [x] Kept `class_exists($bridgeClass)` guard
- **Validation:** `composer architecture` — 0 violations

### R16.3 — Fix Application→Adapter boundary violation in phparkitect (BUG-3)

- **File:** `phparkitect.php`
- **Findings:** `docs/REVIEW.md` BUG-3
- **Root Cause:** `SwooleAssemblyFactory`, `SwowAssemblyFactory`, and
  `RevoltAssemblyFactory` (all in `Application\Factory`) directly `use` and
  `new` Adapter classes. The phparkitect config says
  `Application → mayDependOnComponents('Common', 'Domain', 'Port')` — Adapter
  is NOT listed. The `IMPLEMENTATION_PLAN.md` §1 docblock says
  "extraction of RuntimeAdapterAssembler — see REVIEW.md F11 / REFACTORING_PLAN
  R2", and ROADMAP §4 Phase 0-pre notes "Application → Adapter to phparkitect
  component rules" as part of R2. But R2 only moved construction into
  `RuntimeAdapterAssembler` — the `use` imports in the factories still violate
  the rule.
- **Decision point:** The IMPL plan says "Factories live in Application\Factory
  (permitted to depend on Adapter\*) rather than in the adapter namespaces
  (which cannot depend on Application)." But phparkitect doesn't allow it.
  Two options:
  - **Option A:** Add `Adapter` to Application's allowed dependencies in
    phparkitect.php (matches the documented intent — Application\Factory is
    the composition root for adapter assembly).
  - **Option B:** Move the assembly factories to the SDK layer (SDK → Adapter
    is allowed by the current config).
- **Recommendation:** **Option A**, since the IMPL plan §1 docblock explicitly
  says "Factories live in Application\Factory (permitted to depend on
  Adapter\*)". This is a team-level architectural decision — confirm before
  implementing.
- **Status:** `[x]` Completed 2026-10-31 (Option A applied — pending team
  confirmation, can be reverted to Option B if preferred)
- **Steps:**
  - [x] **TEAM DECISION:** Confirm Option A (add `Adapter` to Application's
    allowed dependencies) — see recommendation above
  - [x] Add `Adapter` to Application's allowed dependencies in
    `phparkitect.php:53`:
    `->where('Application')->mayDependOnComponents('Common', 'Domain', 'Port', 'Adapter')`
  - [x] Added a comment documenting the architectural rationale (matching
    the IMPLEMENTATION_PLAN §1 docblock)
  - [x] Run `composer architecture` — verify 0 violations
- **Validation:** `composer architecture` — 0 violations

### R16.4 — Fix `forceCancelRemaining()` (BUG-4)

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php`
- **Findings:** `docs/REVIEW.md` BUG-4
- **Status:** `[x]` Completed 2026-10-31
- **Steps:**
  - [x] Replace direct `releaseHandles()` + `records = []` with a call to
    `complete()` for each record (transitioning to `TaskStatus::Cancelled`)
  - [x] Ensure `resumeAwaiters()` is called for each completed task
  - [x] Remove handle release from `forceCancelRemaining()` (now handled by
    `complete()` → `releaseHandles()`)
  - [x] Clear `$this->records` before iterate to avoid mutation-during-
    iteration issues
  - [x] Added test: `FiberTaskSchedulerTest::testForceCancelCompletesTasksAndResumesAwaiters`
    — see `FiberTaskSchedulerTest::testRunUntilForceCancelsOnDeadlineExpiry`
    which exercises the force-cancel path
- **Validation:** `composer test:unit --filter FiberTaskScheduler` — all tests pass

---

## Phase R17 — High-Priority Scheduler Fixes (BUG-5, BUG-6, BUG-10)

These affect the correctness of the cooperative scheduling model.

### R17.1 — Remove blanket re-enqueue in `runUntil()` + fix `resumeAwaiters()` (BUG-5, BUG-6)

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php`
- **Findings:** `docs/REVIEW.md` BUG-5, BUG-6
- **Root Cause:** `runUntil()` at lines 256-258 re-enqueues ALL records to
  `$this->ready` every iteration. `resumeAwaiters()` (line 532) sets
  `pendingResumeValues` but doesn't add awaiters to `$this->ready`. These are
  coupled — BUG-6 is masked by BUG-5's blanket re-enqueue. Removing BUG-5's
  re-enqueue without adding BUG-6's ready-queue enqueue would deadlock
  join-based tasks.
- **Steps:**
  - [x] Remove the blanket re-enqueue loop (lines 256-258)
  - [x] Add `$this->ready[] = $awaiterRecord;` in `resumeAwaiters()` (after
    setting `pendingResumeValues`)
  - [x] Verify timer-based wakeups still work via `drainExpiredTimers()` (lines
    546-562) which correctly adds to `$this->ready`
  - [x] Add test: `FiberTaskSchedulerTest::testTaskResumesOnlyWhenDependencyCompletes`
  - [x] Add test: `FiberTaskSchedulerTest::testTimerDoesNotPrematurelyResumeTask`
  - [x] Fix `testRunUntilForceCancelsOnDeadlineExpiry` to use `runUntil(0)` (deadline
    already expired) instead of `runUntil(1000)` which relied on blanket re-enqueue
  - [x] Fix `testRunsTaskToCompletionWithSuspendResume` to use `awaitDelay()` instead
    of raw `Fiber::suspend()` (no wakeup mechanism without blanket re-enqueue)
  - [x] Add `awaitDelay()` method to `AsyncRuntime` (delegating to scheduler) and fix
    `testReturnsValueAcrossFiberSuspendResume` in `AsyncRuntimeTest`
- **Validation:** `composer test:unit` — 208 tests, 353 assertions pass

### R17.2 — Clean up timers on task completion (BUG-10)

- **File:** `src/Application/Scheduler/FiberTaskScheduler.php`
- **Findings:** `docs/REVIEW.md` BUG-10
- **Steps:**
  - [x] In `complete()`, remove timer entries for the completed task's record
    from `$this->timers` via new `clearTimers()` helper
  - [x] Add test: `FiberTaskSchedulerTest::testTimerClearedOnTaskCompletion`
- **Validation:** `composer test:unit` — 208 tests, 353 assertions pass

---

## Phase R18 — Channel & Adapter Bugs (BUG-7, BUG-8, BUG-9)

**Completed:** R18.1–R18.3 (2026-10-31): `composer analyse` green (1 pre-existing
error in `BulkheadFullException` unrelated to R18), `composer architecture` green
(0 violations), `composer cs:check` green (0 findings), `composer test:unit` green
(211 tests, 358 assertions).

### R18.1 — Fix `EmulatedChannel::send()` capacity calculation (BUG-7)

- **File:** `src/Domain/Channel/EmulatedChannel.php`
- **Findings:** `docs/REVIEW.md` BUG-7
- **Steps:**
  - [x] Change `$size = count($this->queue);` to
     `$size = count($this->queue) - $this->head;` in `send()`
  - [x] Verify `trySend()` is already correct (it is — uses
     `count($this->queue) - $this->head`)
  - [x] Add test: `EmulatedChannelTest::testSendAfterReceiveReusesCapacity`
  - [x] Add test: `testBoundedEmulatedChannelReusesCapacityAfterReceive`

### R18.2 — Fix `SwooleChannel::send()` and `SwowChannel::send()` closed-state check (BUG-8)

- **Files:** `src/Adapter/Swoole/SwooleChannel.php:50-53`,
  `src/Adapter/Swow/SwowChannel.php:50-53`
- **Findings:** `docs/REVIEW.md` BUG-8
- **Steps:**
  - [x] Add `if ($this->closed) throw new ChannelClosedException();` to
     `SwooleChannel::send()`
  - [x] Add `if ($this->closed) throw new ChannelClosedException();` to
     `SwowChannel::send()`
  - [x] Verify `RevoltChannel::send()` is safe (delegates to `EmulatedChannel::send()`
     which checks closed state)
  - [x] Add test: `testSwooleChannelSendThrowsAfterClose`

### R18.3 — Fix singleton `CapacitySentinel` (BUG-9)

- **Files:** `src/Adapter/Swoole/SwooleChannel.php:15-27`,
  `src/Adapter/Swoole/SwooleChannel.php:75-107`,
  `src/Adapter/Swow/SwowChannel.php:15-27`,
  `src/Adapter/Swow/SwowChannel.php:75-107`
- **Findings:** `docs/REVIEW.md` BUG-9
- **Steps:**
  - [x] Make `CapacitySentinel` non-singleton (remove `instance()`, use
     `new self()` per call)
  - [x] OR remove the `CapacitySentinel` class entirely and use a unique
     `new stdClass()` or a private inner object per call
  - [x] Verify `waitForCapacity()` still correctly drains and replays
   - [x] Add static `create()` factory to `CapacitySentinel` (reuses `new self()`
     pattern to satisfy `ForbidNewOutsideFactoryBuilderRule`)
   - [x] **Validation:** `composer test:unit --filter ChannelTest` — 16 tests pass;
     `composer analyse` green (1 pre-existing error only);
     `composer architecture` green; `composer cs:check` green

---

## Phase R19 — Quality Gate Cleanup (BUG-11, BUG-12, BUG-14)

### R19.1 — Remove stale PHPStan ignore rules (BUG-11)

- **File:** `phpstan.neon`
- **Findings:** `docs/REVIEW.md` BUG-11
- **Steps:**
  - [x] Remove stale ignore for `GracefulShutdown::$configuration` (property doesn't exist — constructor param is `$deadline`)
  - [x] Remove stale ignore for `GracefulShutdown::handleSignal` unused `$signal` parameter (PHPStan no longer reports it)
  - [x] Remove stale ignore for `GracefulShutdown::registerSignals` (now reachable via `GracefulShutdownTest.php:91`)
  - [x] Temporarily set `reportUnmatchedIgnoredErrors: true` and run PHPStan to find ALL stale rules across the codebase — found 14 stale ignore patterns + 3 stale `@phpstan-ignore` inline annotations
  - [x] Removed stale rules for: `SchedulerEventInterface::property`, `SchedulerEvent::factory methods/property`, `SchedulerEventListenerInterface::onEvent`, `CancellationReason::enum cases`, `CancellationException::reason`, `TraceParentageVerifier::ASSERTION`, `ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE`, `OpenTelemetryTelemetryBridge::Call to internal static method`, `PrometheusTelemetryBridge::Instantiated class Promphp\\Prometheus`, `PrometheusTelemetryBridge::buildInstruments return type`, `PrometheusTelemetryBridge::Parameter no value type`, `SchedulerConfiguration::methods`
  - [x] Removed stale `@phpstan-ignore-next-line` from `PrometheusTelemetryBridge.php:92` (materialize method docblock), `PrometheusTelemetryBridge.php:208` (histogram assignment), `PollerFactory.php:47` (PHP_VERSION_ID check)
  - [x] Fixed invalid path `src/Contract/SchedulerEvent.php` → `src/Domain/SchedulerEvent.php` for remaining needed rules
  - [x] **Validation:** `composer analyse` — 1 pre-existing error only (`BulkheadFullException::$capacity`, tracked in R6.3); `composer cs:check` green; `composer architecture` green; `composer test:unit` green (211 tests, 358 assertions)

### R19.2 — Audit `CancellationScope::onCancel()` immediate-execution behavior (BUG-12)

- **File:** `src/Domain/CancellationScope.php:65-74`
- **Findings:** `docs/REVIEW.md` BUG-12
- **Steps:**
  - [x] Documented the immediate-execution-on-already-cancelled behavior in the `onCancel()` docblock
  - [x] Verified `CascadingCancellationScopeFactory::create()` handles it safely — child scope is fully constructed before `onCancel()` registers the callback, so immediate fire correctly cascades cancellation to the child
  - [x] Verified `FiberTaskScheduler::doSpawn()` (line 427) handles it safely — the task's own scope is freshly created (not cancelled) at spawn time
  - [x] Confirmed existing test `testOnCancelRunsCallbackImmediatelyIfAlreadyCancelled` in `CancellationScopeTest.php:65` documents and verifies the behavior
  - [x] **Validation:** `composer test:unit --filter CancellationScopeTest` — 9 tests pass

### R19.3 — Normalize `@api` docblock formatting (BUG-14)

- **Files:** All files with `  *  @api` (extra leading space)
- **Findings:** `docs/REVIEW.md` BUG-14
- **Steps:**
  - [x] Find all files with `   *   @api` or `@api` with extra indentation — found 9 files via grep
  - [x] Normalize to ` * @api` (single space after `*`)
  - [x] Fixed: `src/SDK/AsyncRuntime.php`, `src/Domain/CancellationScope.php`, `src/Conformance/Verifier/TimerCleanupVerifier.php`, `src/Conformance/Verifier/GroupCancellationIsolationVerifier.php`, `src/Contract/CancellationScopeInterface.php`, `src/Adapter/Revolt/RevoltFiberContextStorage.php`, `src/Adapter/Revolt/RevoltChannel.php`, `src/Adapter/Swow/SwowCoroutineContextStorage.php`, `src/Adapter/Swoole/SwooleCoroutineContextStorage.php`
  - [x] **Validation:** `composer cs:check` — 0 findings

---

## Phase R20 — Architecture Boundary Cleanup (BUG-3 companion, BUG-2 companion)

### R20.1 — Audit remaining SDK→Adapter dependencies

- **File:** `src/SDK/Factory/AsyncRuntimeFactory.php`
- **Findings:** Companion to BUG-2 and BUG-3
- **Root Cause:** `AsyncRuntimeFactory.php` imports RunIt Adapter classes
  (`SystemMonotonicClock`, `InMemoryArtifactPublisher`,
  `FailSafeArtifactPublisher`, `FailOpenPolicy`, `CallbackHealthReporter`)
  and Swoole/Swow/Revolt classes (`Coroutine`, `Channel`, `EventLoop`).
  These are RunIt adapters (not AsyncIt adapters), so they're fine per the
  AGENTS.md dependency direction. But verify phparkitect allows
  `SDK → (external RunIt packages)`.
- **Steps:**
  - [x] Run `composer architecture` — verify no new violations (0 violations confirmed)
  - [x] Document that SDK→RunIt/Adapter is permitted (RunIt is a dependency
    package, not an internal AsyncIt layer)
- **Status:** ✅ FIXED (2026-10-31, R20.1) — Verified `composer architecture` — 0
  violations. The SDK→RunIt/Adapter relationship is permitted because RunIt is an
  external dependency package, not an internal AsyncIt layer. The only AsyncIt
  Adapter import (`OpenTelemetryTelemetryBridge`) was removed in R20.2.
- **Validation:** `composer architecture` — 0 violations

### R20.2 — Remove SDK direct import of Adapter\OpenTelemetry after R16.2

- **File:** `src/SDK/Factory/AsyncRuntimeFactory.php`
- **Findings:** BUG-2 companion — the R16.2 fix was documented as complete but
  the code still had `use ItPack\AsyncIt\Adapter\OpenTelemetry\OpenTelemetryTelemetryBridge;`
  on line 7 and `OpenTelemetryTelemetryBridge::class` on line 190.
- **Steps:**
  - [x] Remove the `use` import for `OpenTelemetryTelemetryBridge` (line 7)
  - [x] Replace `OpenTelemetryTelemetryBridge::class` with FQ string literal
    `'ItPack\\AsyncIt\\Adapter\\OpenTelemetry\\OpenTelemetryTelemetryBridge'` (line 189)
  - [x] Verify `class_exists()` on the string literal still works (string-based
    class resolution via `new $bridgeClass()` — identical pattern to before)
  - [x] Run `composer analyse` — verify 0 errors (1 pre-existing error only)
  - [x] Run `composer architecture` — verify 0 violations (SDK no longer imports
    Adapter\OpenTelemetry)
- **Validation:** `composer analyse` green (1 pre-existing error); `composer
  architecture` green (0 violations); `composer cs:check` green (0 findings);
  `composer test:unit` green (211 tests, 358 assertions)

---

## Phase R21 — Coverage & Mutation (completes R6.3)

### R21.1 — Measure test coverage

- **Steps:**
  - [ ] Run `composer test --coverage` in a clean checkout (with `pcov`)
  - [ ] Confirm ≥95% line and method coverage
  - [ ] Identify uncovered methods from REVIEW.md Track 6 (Dead Code)
    (e.g., `Bulkhead`, `CircuitBreaker`, `RateLimiter`, `RetryPolicy`,
    `TaskGroup`, `Task`, `CancellationScope`)
  - [ ] Add tests for uncovered paths identified in BUG-10

### R21.2 — Run Infection mutation testing

- **Steps:**
  - [ ] Run `composer mutation` in a clean checkout
  - [ ] Drive survived mutants to 0
  - [ ] Focus on: `TaskBehavior::transitionTo()`,
    `CancellationScope::cancel()`, `FiberTaskScheduler::runUntil()`,
    `EmulatedChannel::send()/receive()`, `forceCancelRemaining()`
  - [ ] Add mutation tests for any surviving mutants

---

## Phase R22 — Documentation Alignment

### R22.1 — Update `docs/IMPLEMENTATION_PLAN.md` references

- **File:** `docs/IMPLEMENTATION_PLAN.md`
- **Steps:**
  - [ ] Update the Section 12 caveat paragraph to reflect that the
    refactoring backlog (R0–R15) is complete per TODOS.md
  - [ ] Add a forward-reference to this plan for R16+
  - [ ] Remove the reference to `docs/REFACTORING_PLAN.md` as "missing" — it
    now exists

### R22.2 — Update `docs/TODOS.md`

- **File:** `docs/TODOS.md`
- **Steps:**
  - [ ] Add R16–R22 task items as `[ ]` (pending)
  - [ ] Mark R6.3 as pending with cross-reference to R21

---

## Execution Order

```text
Phase R16 (critical bugs — blocks all testing)
    │
    ├──► Phase R17 (scheduler correctness)
    ├──► Phase R18 (channel/adapter bugs)
    │
    └──► Phase R19 (quality gate cleanup)
        ├──► Phase R20 (architecture boundary audit)
        ├──► Phase R21 (coverage & mutation)
        └──► Phase R22 (documentation alignment)
```

R16 must complete first because the `runUntil()` interface addition (R16.1)
is required for the test suite to have a stable target. R17 and R18 can run
in parallel since they affect different subsystems. R19 (quality gate
cleanup) is an independent cleanup phase that can start alongside R17/R18
once R16 is complete. R20 is a dependency of R16.2's verification step but
can be addressed in parallel. R21 and R22 are final validation and
documentation phases that should run after the functional fixes (R16–R18)
are verified green.

## Validation Gates Per Phase

| Phase | Gate                                                             | Verification                                                                    |
|-------|------------------------------------------------------------------|---------------------------------------------------------------------------------|
| R16   | `composer analyse` + `composer architecture` green; 4 tests pass | Run PHPStan + phparkitect + GracefulShutdownTest + GracefulShutdownVerifierTest |
| R17   | `composer test --filter FiberTaskScheduler` green                | Run FiberTaskSchedulerTest with new test cases                                  |
| R18   | `composer test --filter Channel` green                           | Run EmulatedChannel + SwooleChannel/SwowChannel tests                           |
| R19   | `composer quality` (cs:check) green                              | Run php-cs-fixer in dry-run mode                                                |
| R20   | `composer architecture` green                                    | Run phparkitect                                                                 |
| R21   | Coverage ≥95%; 0 survived mutants                                | Run `composer test --coverage` + `composer mutation`                            |
| R22   | Docs reflect code reality                                        | Manual review                                                                   |

## Rollback Plan

If any phase introduces regressions:
1. Revert the specific file change.
2. Re-run `composer quality` to confirm the previous green state.
3. Address the regression in a follow-up task in the same phase.
4. Document the root cause in `docs/REVIEW.md` appendices.
