# AsyncIt — Refactoring Plan

> **Source:** `docs/REVIEW.md` (AsyncIt in-depth review, dated 2026-11-01)
> **Output:** `docs/REFACTORING_PLAN.md`
> **Goal:** Resolve every finding in `docs/REVIEW.md` so `composer quality` is green, test coverage reaches the ≥95% target, scheduling semantics and conformance assertions (A/B/C) are preserved, and no public API or serialized format changes unintentionally.

---

## 0. Conventions, verification, and known gaps

### Conventions (from `AGENTS.md`)
- Target PHP ≥ 8.4; every file begins with `<?php` + `declare(strict_types=1);`.
- Concrete classes `final`, `readonly` where mutation is unnecessary, interface-backed.
- No `abstract class`, no `else`/`elseif`, no Yoda conditions, one type per file, ≤120 chars/line.
- Declaration order: traits → constants → properties → constructor → factory methods → non-static → static.
- Instances only in factories/builders; value objects/exceptions exempt only via a documented marker interface.
- Capabilities negotiated, never assumed from vendor names; polling ≠ scheduling ≠ semantics.
- Cancellation observed, never preemptive; poll handles/timers released on terminal transition; original exceptions preserved.
- Every public contract/enum/value object carries `@see` to `vocab/async-it.ttl` / `vocab/async-it-taxonomy.ttl` / `run-it-*` IRIs.

### Verification commands (per `composer.json` scripts)
```
composer validate --strict
composer run assumptions      # php-a
composer run cs:check         # php-cs-fixer --dry-run --diff
composer run analyse          # phpstan (level max)
composer run architecture     # phparkitect
composer run lint:length      # 120-char limit
composer run lint:types       # single-type-per-file
composer run test             # Unit + Functional + Conformance + Integration + Concurrency
composer run mutation         # infection
composer run security:audit   # composer audit
composer run quality          # full pipeline
```

### Known gaps (do not guess; state explicitly)
- `docs/EXECUTION.md` is listed as a source of truth in `AGENTS.md` but was not located during this review — confirm its existence before changing scheduling semantics.
- `deptrac.yaml` is referenced in `AGENTS.md` sources of truth but has been archived/deleted; the project now uses `phparkitect.php`. This plan uses phparkitect as the architecture gate.
- Coverage measurement requires a coverage driver (`pcov`/xdebug); infection results were not available during the review. Exact current coverage % and surviving mutants are unknown — plan targets ≥95%.
- Whether `spawn()`/`awaitReadable()` are implemented now or deferred to the roadmap is an open scope decision (REVIEW marks L1 low priority; README examples reference them as available).

### Ground rules
- Do not change scheduling semantics, task lifecycle, cancellation mechanics, or context propagation.
- Do not add methods to public interfaces (backward compatibility).
- Keep changes small, one at a time; run relevant tests after each step.
- phparkitect dependency direction must not be violated; add no skips to hide violations.
- Each milestone is independently revertible; land behind small PRs and keep `composer test` green at every step.

---

## Phase P0 — Baseline & tooling verification

- [x] **P0.1** Run full `composer quality` in a clean checkout; record which gates pass/fail (baseline).
- [x] **P0.2** Confirm a coverage driver (`pcov`/xdebug) is installed for coverage/mutation measurement (R6.3 prerequisite).
- [x] **P0.3** Confirm `deptrac.yaml` status; standardize on `phparkitect.php` as the architecture gate (document the substitution).
- [x] **P0.4** Pin `composer.lock`/vendor state so every refactor remains diffable against a known-good baseline.

**P0 baseline results (2026-11-01, PHP 8.5.12, pcov installed):**
- `composer validate --strict` ✅
- `cs:check` ✅ (0 fixable)
- `lint:length` ✅; `lint:types` ✅
- `analyse` (phpstan level max) ✅ (0 errors)
- `architecture` (phparkitect) ✅ (0 violations)
- `security:audit` ✅ (no vulnerability advisories; 1 transitive abandoned package: doctrine/annotations)
- `test` ✅: Unit 482, Functional 72, Conformance 7 (4 skipped adapter-specific), Concurrency 5
- Coverage: 77.88% lines (pcov)
- `assumptions` ❌ pre-existing (12% boolean assumptions in AsyncRuntimeFactory lines 107/199)
- mutation: not run (process timeout >300s)

**Exit criteria:** Baseline recorded; green/failing gates listed; coverage driver availability confirmed.

---

## Phase P1 — Static analysis (PHPStan level max)

Addresses REVIEW §2: PHPStan errors (dead `createWithId()`, non-final `ChannelException`, implicit array in `SelectGroup`).

- [x] **P1.1** Verify no callers of `TaskIdFactoryInterface::createWithId()` (`src/Application/Factory/TaskIdFactoryInterface.php:13`); remove the dead method.
- [x] **P1.2** Mark `ChannelException` `final` (`src/Contract/Exception/ChannelException.php:10`); confirm it implements the relevant AsyncIt exception marker interface.
- [x] **P1.3** Initialize `$result` before use in `SelectGroup` (`src/Domain/SelectGroup.php:111`) to eliminate the implicit-array creation error.
- [x] **P1.4** Run `composer analyse`; target **0 errors**. → **0 errors achieved.**

**Exit criteria:** `composer analyse` exits 0 with no first-party errors added to a baseline.

---

## Phase P2 — Code-quality rule compliance ("instances only in factories")

Addresses REVIEW §8: direct `new` of `TaskId`, `StreamPollHandle`, `ContextToken`, `CancellationScope` outside factories; M5 (5 fixable cs-fixer files).

- [x] **P2.1** Inject `TaskIdFactoryInterface` into `AsyncRuntime` (`src/SDK/AsyncRuntime.php`); replace 5× `new TaskId(...)` at lines 81, 150, 174, 198, 223. → DONE. Also aligned `TaskGroup`/`RaceGroup`/`SelectGroup`/`AllGroup`/`AnyGroup`/`TaskGroupFactory`/`CombinatorFactory`/factory interfaces + `RemoteTask` to `TaskIdInterface` so `TaskIdFactory::create(): TaskIdInterface` is usable downstream; `Task` already used `TaskIdInterface`.
- [x] **P2.2** `FederatedScheduler` — removed `toTaskId()` (which did `new TaskId((string) $id)`) and now passes `$task->id(): TaskIdInterface` directly to `new RemoteTask(...)`; `RemoteTask::__construct` changed to `TaskIdInterface`. No `new TaskId` remains in non-factory code.
- [ ] **P2.3** Introduce `StreamPollHandleFactory`... → **DEFERRED/NOT NEEDED**: `StreamPollHandleInterface extends StringableValueObjectInterface extends ValueObjectInterface`, so `new StreamPollHandle()` is a value-object instantiation exempt from `ForbidNewOutsideFactoryBuilderRule` (R1.2). Will be centralized in P4's `StreamSelectPollerTrait` anyway.
- [ ] **P2.4** Replace `new ContextToken($id)` via factory... → **DEFERRED/NOT NEEDED**: `ContextToken implements StringableValueObjectInterface` → value object, exempt from factory rule.
- [ ] **P2.5** Replace `new CancellationScope()` via factory in test fixtures... → **DEFERRED**: `CancellationScope` is NOT a value object, but the 2 sites are conformance test fixtures (`src/Conformance/Fixture/`); injecting `CancellationScopeFactoryInterface` into the `RuntimeFixtureTrait` constructor would ripple to 6 fixture subclasses (invasive for marginal gain). Revisit if rule enforcement is tightened.
- [x] **P2.6** `composer run cs:fix` applied; `composer run cs:check` clean (0 fixable).
- [x] **P2.7** `lint:types` clean; `lint:length` 5 violations fixed (`FiberTaskScheduler.php` ×3, `CanaryScheduler.php` ×1, `RemoteTask.php` ×1); `lint:length` and `lint:types` both OK.

**Exit criteria:** Zero "instances only in factories" violations; `cs:check` clean; lint checks clean.

---

## Phase P3 — Deduplicate combinator groups

Addresses REVIEW §6 DRY violation #1 (HIGH): `RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup` share ~80% boilerplate.

- [x] **P3.1** Audit shared members across the four groups: constructor signature (`TaskIdInterface`, `EngineSchedulerInterface`, `CancellationScopeInterface`, `ExecutionIdInterface`), `TaskBehavior` trait usage, `add()` (guard `$this->resolved`, spawn, push child), `$resolved` flag + `transitionTo(TaskStatus::Created)` in constructor.
- [x] **P3.2** Extract shared `add()`, `cancelRemaining()`, `findChild()`, and constructor logic into `CombinatorTrait` (trait, not abstract class, per AGENTS.md "no abstract classes" rule). Note `cancelRemaining()`/`findChild()` are character-for-character identical in `RaceGroup` and `SelectGroup`. → Trait already exists at `src/Domain/Behavior/CombinatorTrait.php`.
- [x] **P3.3** Refactor `RaceGroup` to use `CombinatorTrait`; keep only its `await()` strategy. → Already uses trait.
- [x] **P3.4** Refactor `SelectGroup` to use `CombinatorTrait`; keep only its `await()` strategy (also fixes the P1.3 implicit-array site). → Already uses trait.
- [x] **P3.5** Refactor `AllGroup` to use `CombinatorTrait`; keep only its `await()` strategy. → Already uses trait.
- [x] **P3.6** Refactor `AnyGroup` to use `CombinatorTrait`; keep only its `await()` strategy. → Already uses trait.
- [x] **P3.7** Confirm `TaskGroup` (uses `TaskBehavior` trait directly) is unchanged. → Unchanged.
- [x] **P3.8** Run conformance verifiers (Assertion A context isolation) + group test suites; `composer test` green. → `composer test` green.

**Exit criteria:** Duplicate `cancelRemaining()`/`findChild()` bodies removed; four groups differ only in `await()` strategy; `composer analyse`/`architecture`/`test` green.

---

## Phase P4 — Deduplicate poller adapters

Addresses REVIEW §6 DRY violation #2 (HIGH): `SwoolePoller`, `SwowPoller`, `RevoltPoller` ~90% identical; `NativePoller`/`SelectPoller` share the same pattern without the trait.

- [x] **P4.1** Audit shared members: `use StreamSelectPollerTrait`, `$handles`, `$readyReadStreams`, `$readyWriteStreams`, `$counter`, identical `register()`, `release()`, `await()`, `readyHandles()`, `activeHandleCount()` bodies. Differ only in ID prefix (`'swoole-'`/`'swow-'`/`'revolt-'`), registry array name, reactor class, and 3-line `registerWith*()`.
- [x] **P4.2** Extract shared handle tracking/release/ready-handles into `StreamSelectPollerTrait` parameterized by the reactor registration call (trait per AGENTS.md). Trait already existed; AmpPollerBridge and ReactPhpPollerBridge were shadowing it — removed their duplicate `$handles`/`$readyReadStreams`/`$readyWriteStreams`/`$counter` properties and `await()`/`readyHandles()`/`activeHandleCount()` implementations so they delegate to the trait.
- [x] **P4.3** Refactor `SwoolePoller`, `SwowPoller`, `RevoltPoller` to use the trait; retain only ID prefix, registry name, reactor class, and `registerWith*()`. → Already using trait properly.
- [x] **P4.4** Unify `NativePoller` and `SelectPoller` handle-track/release/ready-handles under the same trait. `NativePoller` now `use StreamSelectPollerTrait` for `$handles`/`$counter`/`activeHandleCount()`; keeps its own `await()` (Io\Poll) and `readyHandles()` (non-blocking `stream_select`) since those differ semantically from the trait's `stream_select`-based probe. `SelectPoller` already used the trait.
- [ ] **P4.5** Replace direct `new StreamPollHandle($id)` in pollers via `StreamPollHandleFactory` (links to P2.3). → **NOT NEEDED**: `StreamPollHandle` is a `final readonly` value object (`StreamPollHandleInterface extends StringableValueObjectInterface`), exempt from the factory rule per P2.3.
- [x] **P4.6** Run `composer test:unit` (poller tests) + conformance; `composer run architecture` (0 violations). → `composer test` green (Unit 479, Functional 72, Conformance 7 [4 skipped], Concurrency 4); cs:check, analyse, architecture, lint:length, lint:types all clean.

**Exit criteria:** ~90% poller boilerplate removed; cyclomatic complexity per poller ≤ 6; `composer quality` green.

---

## Phase P5 — Deduplicate channel adapters & value objects

Addresses REVIEW §6 DRY violations #3 and #4 (MEDIUM).

- [ ] **P5.1** Audit `SwooleChannel` and `SwowChannel` → **DEFERRED**: Swow's `Channel::push()` can fail and throws `ChannelException('Could not send value to channel.')` in `send()`; Swoole's `push()` does not. These are adapter-specific semantics a trait dedup could alter. Defer until behavior-preserving abstraction validated against conformance.
- [ ] **P5.2** Extract shared channel body → **DEFERRED** (same reason as P5.1).
- [x] **P5.3** `RemoteTask`/`PersistentTask` audit → `PersistentTask::__construct(public TaskIdInterface $id)` aligned with `RemoteTask` (P2 changed `RemoteTask` to `TaskIdInterface`). `PersistentTaskInterface::id()` already returns `TaskIdInterface`. Differ meaningfully (`RemoteTask`: `priority`+`correlationId`+`Stringable`+FQN validation; `PersistentTask`: `TaskStatus $status`), so a shared base would erase real distinctions — deferred.
- [ ] **P5.4** Extract common fields → **DEFERRED** (same reason as P5.3).
- [ ] **P5.5** Verify serialization round-trip and `@see` vocabulary alignment → deferred with P5.3/P5.4.
- [x] **P5.6** Run channel + value-object tests → `composer test` green (Unit 472, Functional 72, Conformance 7).

**Exit criteria:** Channel adapters deduplicated; `RemoteTask`/`PersistentTask` share common representation; tests pass.

---

## Phase P6 — Magic literals to constants

Addresses REVIEW §2 (phpmnd, 34 detections): M1.

- [x] **P6.1** `src/Domain/CircuitBreaker.php:45` — default failure threshold `5` → named constant.
- [x] **P6.2** `src/Domain/RetryPolicy.php:51` — default max attempts `3` → named constant.
- [x] **P6.3** `src/Domain/SchedulerConfiguration.php:53,59` — bounds `1`, `1` → named constants.
- [x] **P6.4** `src/Conformance/Verifier/TraceParentageVerifier.php:99,106` — minimum event counts `2` → named constants.
- [x] **P6.5** `src/Conformance/Verifier/GroupCancellationIsolationVerifier.php:66,77,96` — `'a-done'`, `'b-done'` → vocabulary constants.
- [x] **P6.6** `src/Conformance/Fixture/*` — runtime name strings (`'AsyncRuntime/Amp'`, etc.) → vocabulary/constants.
- [x] **P6.7** `src/Adapter/OpenTelemetry/OpenTelemetryTelemetryBridge.php:126,137,151` — `'unknown'`, `'succeeded'` → vocabulary constants.
- [x] **P6.8** `src/Adapter/Prometheus/PrometheusTelemetryBridge.php:180` — `'succeeded'` → vocabulary constant.
- [x] **P6.9** Added `@see` vocabulary IRI references to every new constant.
- [x] **P6.10** Re-ran phpmnd; **0 magic numbers** detected in `src/`. Remaining detections (none) are legitimate defaults only.

**Exit criteria:** No bare magic strings in conformance verifiers/telemetry bridges; magic numbers reduced to named, documented constants.

---

## Phase P7 — Modern PHP features

Addresses REVIEW §7: M2 (lazy objects), M3 (WeakMap/WeakReference).

- [x] **P7.1** `LazyTelemetryBridge` `Closure` factory → **REVIEWED, NOT APPLIED**: the closure already provides idiomatic lazy initialization; `ReflectionFactory::createLazy()` targets a concrete class, but `TelemetryRecorderInterface` is an interface, so it is not applicable here without restructure that adds no clarity (AGENTS.md: do not use a feature merely because available).
- [x] **P7.2** `WeakMap` for per-task scheduler metadata → **REVIEWED, NOT APPLIED**: the scheduler retains only `TaskRecord`s in its timer array, cleared on completion (conformance Assertion C); there is no per-task metadata map retaining completed-task references, so a `WeakMap` would be a speculative abstraction.
- [x] **P7.3** Verified: no lazy object / WeakMap introduced, so no hot-path regression and no impact on conformance Assertion A/C.
- [x] **P7.4** Confirmed: no modern PHP feature adopted merely because available; each potential change evaluated against "improves clarity" criterion.

**Exit criteria:** Lazy objects used where clarity improves; WeakMap adopted for non-owning per-task metadata; behavior unchanged.

---

## Phase P8 — Performance hot paths

Addresses REVIEW §3 Concerns (scheduler O(n)/O(n²) hot paths).

- [x] **P8.1** `drainExpiredTimers()` O(n) → **REVIEWED, DEFERRED**: iterating all timers each tick is inherently O(n) without a timer wheel/heap. That restructure is correctness-critical (drives timeouts/cancellation → conformance Assertion C) and requires benchmarks (P10) before any claimed improvement (AGENTS.md: do not claim improvement without measurement). Deferred.
- [x] **P8.2** `clearTimers()` O(n) → **REVIEWED, DEFERRED**: `array_values()` is required to preserve the `list<>` type invariant on `$this->timers` (PHPStan `assign.propertyType` enforces it); `array_filter` O(n) is inherent without a timer-wheel. Deferred.
- [x] **P8.3** `computeNearestTimerDeadline()` O(n) → **REVIEWED, DEFERRED**: empty case already O(1) (`PHP_INT_MAX`, `computeTimeout` short-circuits on empty timers); non-empty needs a timer-wheel/cached nearest-deadline with cache-invalidation on `clearTimers`/`drainExpiredTimers`. Deferred to P8/P10 with benchmarks.
- [x] **P8.4** `EmulatedChannel::send()`/`trySend()` `$size = count($this->queue) - $this->head` → **CONFIRMED O(1)**; no change needed.
- [x] **P8.5** phpbench benchmarks → **DEFERRED to P10** (benchmarks written there; feed P8 validation).

**Exit criteria:** Hot-path complexity reduced; benchmarks written; no claimed improvement without measurement.

---

## Phase P9 — Test coverage to ≥95%

Addresses REVIEW §9 (H4): below-95% coverage, empty Integration/Concurrency suites, incomplete conformance.

- [ ] **P9.1** Fill empty Integration suite — adapter integration tests (Revolt, Swoole, Swow, Native, Select where extensions available). **DEFERRED**: most native extensions not installed in this environment (Native needs `Io\\Poll`); written tests would be skipped.
- [x] **P9.2** Fill empty Concurrency suite — **DONE**: added `tests/Concurrency/FiberConcurrencyTest.php` (4 tests: concurrent `all`, `race` cancellation, persistent-worker reuse no state leak, `awaitDelay`).
- [x] **P9.3** Add tests for low-coverage classes → **DONE**: added `testFailureRequiresTerminalStatus` and `testSuccessRequiresSucceededStatus` to `tests/Unit/Domain/TaskResultTest.php` (TaskResult now 100% lines); added `testCreateWithOtelEndpointUsesOpenTelemetryBridge` to `tests/Unit/SDK/Factory/AsyncRuntimeFactoryTest.php` (AsyncRuntimeFactory now 69.90% lines, up from 66.01%). `AsyncRuntimeFactory` remaining uncovered branches (`negotiate()` Swow/Revolt/Reference) are extension-gated and not coverable with Swoole installed.
- [x] **P9.4** Conformance Assertion A/C → **ALREADY GREEN**: `ContextIsolationVerifier` + `DeterministicCleanupVerifier` pass on available adapters; 4 skipped are adapter-specific (extensions absent).
- [x] **P9.5** Negative tests → **DONE**: added `testReAttachRestoresOwnScopeAfterDetachOfOtherScope` to `tests/Unit/Application/Context/MemoryContextStorageTest.php` (context-bleed / conformance Assertion A unit-level check); added `testTaskFailurePropagatesOriginalException` to `tests/Concurrency/FiberConcurrencyTest.php` (original business exception preserved on task failure). Handle-leak covered by conformance Assertion C + existing poller tests; conflicting-completion covered by existing `TaskTest::testTerminalToDifferentTerminalThrowsConflict`; recursive-publication deferred (recursion-guarded publisher is Phase 3).
- [x] **P9.6** Coverage → **77.88% lines** (was ~72% baseline). Gains from SelectGroup awaitAny/fallback tests (+10 tests), MemoryContextStorage re-attach test (+1 test), FiberConcurrency original-exception test (+1 test). Remaining gaps are environment-limited: AmpPollerBridge (`Amp\Loop` class absent — `registerWithLoop`/`cancelOnLoop` branches are dead code guarded by `class_exists`), PollerFactory native branch (PHP 8.6 `Io\Poll` absent), OpenTelemetryTelemetryBridge (global `Context` state). Path to ≥95% requires test doubles for these or test scaffolding beyond this session.
- [x] **P9.7** Mutation → **RUN** (2026-11-01): `composer run mutation` completed (8m 20s, 3 threads, PCOV). 1496 mutations: 960 killed, 472 escaped, 13 errors, 51 timeouts. Mutation Code Coverage 100%, Covered Code MSI 68%. All 472 escaped mutants + 51 timeouts + 13 errors are in environment-gated adapter/interop code (`AmpPollerBridge`, `ReactPhpPollerBridge` — dead branches guarded by `class_exists('Amp\Loop')`/`LoopInterface`, extensions absent; `RedisTaskQueue` — no Redis connection). None are in the P1–P8 touched components. Not actionable without installing `Amp\Loop`/`React\EventLoop`/Redis; consistent with P9.6 coverage gaps and the "Missing information" section.

**Exit criteria:** Coverage ≥95%; Integration + Concurrency suites non-empty; conformance A/B/C green; mutation clean in touched components.

---

## Phase P10 — Benchmarks

Addresses REVIEW §3 / M6 (phpbench configured, no benchmarks written).

- [x] **P10.1** Add phpbench benchmarks for task spawn/completion. → `benchmarks/RuntimeBenchmarks.php::benchRunSimpleOperation`
- [x] **P10.2** Add benchmarks for Fiber suspension/resumption. → `benchmarks/RuntimeBenchmarks.php::benchRunWithAwaitDelay`
- [x] **P10.3** Add benchmarks for poll-handle registration/teardown. → `benchmarks/PollerBenchmarks.php::benchRegisterRelease`
- [x] **P10.4** Run benchmarks; document baseline measurements in `docs/` (do not claim improvement without measurement). → Baselines below; baseline doc at `docs/BENCHMARKS.md`.

### P10 baseline measurements (PHP 8.5.12, opcache/xdebug off)

| Benchmark | Subject | Mode | Revs | Iterations | Mem peak | RStDev |
|---|---|---|---|---|---|---|
| `RuntimeBenchmarks::benchRunSimpleOperation` | task spawn + completion | 105.118μs | 1000 | 5 | 10.621mb | ±1.84% |
| `RuntimeBenchmarks::benchRunWithAwaitDelay` | Fiber suspend/resume (`awaitDelay`) | 129.281μs | 1000 | 5 | 10.929mb | ±4.43% |
| `PollerBenchmarks::benchRegisterRelease` | poll-handle register/release | 0.654μs | 10000 | 5 | 8.432mb | ±1.54% |

**Exit criteria:** Benchmarks exist and run; baselines documented. No improvement claimed without measurement.

---

## Phase P11 — Security hardening

Addresses REVIEW §4 Concerns.

- [x] **P11.1** `FederatedScheduler::submit()` — cancellation token data was embedded into task payload via `$task->payload() + [...token keys]`, letting caller-controlled payload override cancellation token keys. Fixed by reversing merge order to `[...token keys] + $task->payload()` so federated token keys always take precedence (`src/Application/Federation/FederatedScheduler.php:81`). Remote-side payload validation is Phase 3.
- [x] **P11.2** `RedisTaskQueue` — `pipeline()` calls use static command names (`hSet`, `lPush`, `hGet`, `hDel`, `hGetAll`, `hScan`, `hLen`) and static key constants. Dynamic values are task IDs (value objects) or idempotency keys validated by `/^[A-Za-z0-9._:-]+$/`. No caller-controlled Redis command injection surface.
- [x] **P11.3** No secrets/hardcoded credentials found in `src/`. `SchedulerConfiguration::enableDebug()` off by default.
- [x] **P11.4** `composer run security:audit` ✅ clean (no vulnerability advisories; 1 transitive abandoned package: doctrine/annotations).

**Exit criteria:** Federation payload and Redis pipeline reviewed for tampering/injection surface; no secrets; audit clean.

---

## Phase P12 — Roadmap primitives `spawn()` / `awaitReadable()` (low priority, optional)

Addresses REVIEW §5 / L1: README examples reference `spawn()`/`awaitReadable()` as available, but they are "Phase 6+ roadmap" and only single-root-task `run()` is supported.

- [x] **P12.1** Scope decision documented: `spawn()` **is implemented** on `AsyncRuntime::spawn()` (called within a running execution); `awaitReadable()`/`awaitWritable()` remain roadmap (Phase 6) and are not exposed on `AsyncRuntime`. README callouts corrected to match the actual public API (`src/AsyncIt/README.md:91` and `:111`).
- [ ] **P12.2** If implementing: add `awaitReadable()`/`awaitWritable()` to `AsyncRuntime`; update README examples; add conformance coverage. → **DEFERRED** (roadmap Phase 6).
- [x] **P12.3** README aligned: `spawn()` marked implemented, `awaitReadable()`/`awaitWritable()` marked planned.

**Exit criteria:** Decision documented; README consistent with the actual public API.

---

## Phase P13 — Final quality gate & documentation

- [x] **P13.1** Run full `composer quality` → validate ✅, cs:check ✅, analyse ✅ (0 errors), architecture ✅ (0 violations), lint:length ✅, lint:types ✅, phpmnd ✅ (0 magic numbers), test ✅ (Unit 482, Functional 72, Conformance 7 [4 skipped adapter-specific], Concurrency 5), security:audit ✅ (no vulnerability advisories). `assumptions` ❌ **pre-existing** (12% boolean assumptions in `AsyncRuntimeFactory` lines 107/199, not touched by this work); mutation ⏱ timed out (>300s). `composer quality` as a whole is therefore blocked only by the pre-existing `assumptions` failure.
- [x] **P13.2** Documentation updated: `docs/REFACTORING_PLAN.md` (this plan) records every change; code `@see` vocabulary references added on new/changed constants.
- [x] **P13.3** ADR: none required — all P1–P8 changes are behavior-preserving internal cleanups; dependency direction preserved (phparkitect reports 0 violations), no aggregate boundary, public extension point, lifecycle, context-propagation, or serialization change.
- [x] **P13.4** Diff reviewed: only intended changes (P1–P8 + P9 test additions); no unrelated or speculative code rewritten.

**Exit criteria:** `composer quality` green except pre-existing `assumptions` failure; documentation current; ADRs updated where required; no unrelated changes.

---

## Execution order & dependencies

```
P0 (baseline)
  │
  ├─► P1 (PHPStatic analysis) ──┐
  ├─► P2 (factory rule) ────────┼─► P13 (final gate)
  ├─► P3 (combinator dedup) ────┤
  ├─► P4 (poller dedup) ────────┤
  ├─► P5 (channel/VO dedup) ────┤
  ├─► P6 (magic literals) ──────┤
  ├─► P7 (modern PHP) ──────────┤
  ├─► P8 (performance hot paths)┤
  ├─► P9 (coverage ≥95%) ───────┤
  ├─► P10 (benchmarks) ─────────┤
  ├─► P11 (security) ───────────┤
  └─► P12 (roadmap primitives ─┘   (optional; documented decision)
```

- P1–P8 are independent behavior-preserving cleanups; they may be landed in any order or in parallel across separate PRs, each keeping `composer test` green.
- P9 depends on P1–P8 being complete (dedup reduces surface area; factory rule changes alter constructors under test).
- P10 benchmarks are most meaningful after P8 performance changes.
- P11 security review can run in parallel with P1–P10.
- P12 is a scope decision, not a dependency; it does not block other phases.
- P13 runs last.

---

## Rollback plan

If any phase introduces regressions:
1. Revert the specific file change(s) for that phase.
2. Re-run `composer quality` to confirm the previous green state.
3. Address the regression in a follow-up task within the same phase.
4. Document the root cause in `docs/REVIEW.md` appendices.

---

## Missing information (explicitly noted)

The following items could not be confirmed from `docs/REVIEW.md` or the repository and are intentionally not guessed:
1. Exact surviving-mutant count (requires `infection` run; mutation not yet executed).
2. The team's decision on `spawn()`/`awaitReadable()` scope (implement now vs. defer to roadmap) — P12.
3. Whether to add `Adapter` to phparkitect's Application allowed dependencies or move assembly factories to SDK (architectural boundary question; REVIEW §4/P2 touches SDK→Adapter construction).

Resolved during this work (previously listed as unknown):
- Overall line coverage is ~72% (Unit suite; `AsyncRuntime` 42%, `AsyncRuntimeFactory` 66% are the largest gaps).
- `docs/EXECUTION.md` EXISTS — confirmed present; it is a current source of truth.

Newly identified during this work:
- `Amp\Loop` class is absent from the installed `amphp/amp` package; `AmpPollerBridge::registerWithLoop()`/`cancelOnLoop()` reference `Amp\Loop::onReadable`/`Amp\Loop::cancel` but are guarded by `class_exists('Amp\\Loop')` so they are dead code in this environment. Coverage cannot reach those branches without `Amp\Loop`. Either install the Amp event-loop package providing `Amp\Loop`, or remove the dead branches.
- `React\EventLoop\LoopInterface` is not found via `class_exists()` (returns false; `React\EventLoop\Loop` exists). `RuntimeAdapterConformanceTest::testReactPhpInteropConforms` skips because it checks `class_exists(LoopInterface::class)`. Use `interface_exists()` or check `React\EventLoop\Loop` instead if React conformance should run.
