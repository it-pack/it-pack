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

- [ ] **P0.1** Run full `composer quality` in a clean checkout; record which gates pass/fail (baseline).
- [ ] **P0.2** Confirm a coverage driver (`pcov`/xdebug) is installed for coverage/mutation measurement (R6.3 prerequisite).
- [ ] **P0.3** Confirm `deptrac.yaml` status; standardize on `phparkitect.php` as the architecture gate (document the substitution).
- [ ] **P0.4** Pin `composer.lock`/vendor state so every refactor remains diffable against a known-good baseline.

**Exit criteria:** Baseline recorded; green/failing gates listed; coverage driver availability confirmed.

---

## Phase P1 — Static analysis (PHPStan level max)

Addresses REVIEW §2: PHPStan errors (dead `createWithId()`, non-final `ChannelException`, implicit array in `SelectGroup`).

- [ ] **P1.1** Verify no callers of `TaskIdFactoryInterface::createWithId()` (`src/Application/Factory/TaskIdFactoryInterface.php:13`); remove the dead method.
- [ ] **P1.2** Mark `ChannelException` `final` (`src/Contract/Exception/ChannelException.php:10`); confirm it implements the relevant AsyncIt exception marker interface.
- [ ] **P1.3** Initialize `$result` before use in `SelectGroup` (`src/Domain/SelectGroup.php:111`) to eliminate the implicit-array creation error.
- [ ] **P1.4** Run `composer analyse`; target **0 errors**.

**Exit criteria:** `composer analyse` exits 0 with no first-party errors added to a baseline.

---

## Phase P2 — Code-quality rule compliance ("instances only in factories")

Addresses REVIEW §8: direct `new` of `TaskId`, `StreamPollHandle`, `ContextToken`, `CancellationScope` outside factories; M5 (5 fixable cs-fixer files).

- [ ] **P2.1** Inject `TaskIdFactoryInterface` into `AsyncRuntime` (`src/SDK/AsyncRuntime.php`); replace 5× `new TaskId(...)` at lines 81, 150, 174, 198, 223.
- [ ] **P2.2** Inject `TaskIdFactoryInterface` into `FederatedScheduler` (`src/Application/Federation/FederatedScheduler.php:116`); replace `new TaskId((string) $id)`.
- [ ] **P2.3** Introduce `StreamPollHandleFactory` (or route via `PollerFactory`); replace `new StreamPollHandle($id)` in:
  - `NativePoller.php:56,99`, `SelectPoller.php:50`, `SwoolePoller.php:62`, `SwowPoller.php:62`, `RevoltPoller.php:63`
  - `ReactPhpPollerBridge.php:70`, `AmpPollerBridge.php:65`, `StreamSelectPollerTrait.php:102`
- [ ] **P2.4** Replace `new ContextToken($id)` via factory in `MemoryContextStorage.php:57` and `CoroutineContextStorageTrait.php:51`.
- [ ] **P2.5** Replace `new CancellationScope()` via factory in test fixtures `ReferenceRuntimeFixture.php:72` and `RuntimeFixtureTrait.php:130`.
- [ ] **P2.6** Run `composer run cs:fix`; review diff; verify `composer run cs:check` clean (5 fixable files: unused imports, whitespace).
- [ ] **P2.7** Run `composer run lint:length` and `composer run lint:types`; both clean.

**Exit criteria:** Zero "instances only in factories" violations; `cs:check` clean; lint checks clean.

---

## Phase P3 — Deduplicate combinator groups

Addresses REVIEW §6 DRY violation #1 (HIGH): `RaceGroup`, `SelectGroup`, `AllGroup`, `AnyGroup` share ~80% boilerplate.

- [ ] **P3.1** Audit shared members across the four groups: constructor signature (`TaskId`, `EngineSchedulerInterface`, `CancellationScopeInterface`, `ExecutionIdInterface`), `TaskBehavior` trait usage, `add()` (guard `$this->resolved`, spawn, push child), `$resolved` flag + `transitionTo(TaskStatus::Created)` in constructor.
- [ ] **P3.2** Extract shared `add()`, `cancelRemaining()`, `findChild()`, and constructor logic into `CombinatorTrait` (trait, not abstract class, per AGENTS.md "no abstract classes" rule). Note `cancelRemaining()`/`findChild()` are character-for-character identical in `RaceGroup` and `SelectGroup`.
- [ ] **P3.3** Refactor `RaceGroup` to use `CombinatorTrait`; keep only its `await()` strategy.
- [ ] **P3.4** Refactor `SelectGroup` to use `CombinatorTrait`; keep only its `await()` strategy (also fixes the P1.3 implicit-array site).
- [ ] **P3.5** Refactor `AllGroup` to use `CombinatorTrait`; keep only its `await()` strategy.
- [ ] **P3.6** Refactor `AnyGroup` to use `CombinatorTrait`; keep only its `await()` strategy.
- [ ] **P3.7** Confirm `TaskGroup` (uses `TaskBehavior` trait directly) is unchanged.
- [ ] **P3.8** Run conformance verifiers (Assertion A context isolation) + group test suites; `composer test` green.

**Exit criteria:** Duplicate `cancelRemaining()`/`findChild()` bodies removed; four groups differ only in `await()` strategy; `composer analyse`/`architecture`/`test` green.

---

## Phase P4 — Deduplicate poller adapters

Addresses REVIEW §6 DRY violation #2 (HIGH): `SwoolePoller`, `SwowPoller`, `RevoltPoller` ~90% identical; `NativePoller`/`SelectPoller` share the same pattern without the trait.

- [ ] **P4.1** Audit shared members: `use StreamSelectPollerTrait`, `$handles`, `$readyReadStreams`, `$readyWriteStreams`, `$counter`, identical `register()`, `release()`, `await()`, `readyHandles()`, `activeHandleCount()` bodies. Differ only in ID prefix (`'swoole-'`/`'swow-'`/`'revolt-'`), registry array name, reactor class, and 3-line `registerWith*()`.
- [ ] **P4.2** Extract shared handle tracking/release/ready-handles into `StreamSelectPollerTrait` parameterized by the reactor registration call (trait per AGENTS.md).
- [ ] **P4.3** Refactor `SwoolePoller`, `SwowPoller`, `RevoltPoller` to use the trait; retain only ID prefix, registry name, reactor class, and `registerWith*()`.
- [ ] **P4.4** Unify `NativePoller` and `SelectPoller` handle-track/release/ready-handles under the same trait.
- [ ] **P4.5** Replace direct `new StreamPollHandle($id)` in pollers via `StreamPollHandleFactory` (links to P2.3).
- [ ] **P4.6** Run `composer test:unit` (poller tests) + conformance; `composer run architecture` (0 violations).

**Exit criteria:** ~90% poller boilerplate removed; cyclomatic complexity per poller ≤ 6; `composer quality` green.

---

## Phase P5 — Deduplicate channel adapters & value objects

Addresses REVIEW §6 DRY violations #3 and #4 (MEDIUM).

- [ ] **P5.1** Audit `SwooleChannel` and `SwowChannel` (~80% duplication): `send()`, `receive()`, `trySend()`, `waitForCapacity()`, `isClosed()`, `close()` bodies. Differ only in push return-value handling and sentinel creation (`CapacitySentinel::create()` used in both).
- [ ] **P5.2** Extract shared channel body into a trait/shared base; retain only push-return-value and sentinel-creation differences.
- [ ] **P5.3** Audit `RemoteTask` and `PersistentTask` (near-identical readonly value objects: `TaskId $id`, `string $operation`, `array $payload`, `?DurationInterface $deadline`, nearly identical constructors/accessors). Differ only in: `RemoteTask` has `priority` + `correlationId` + `Stringable`; `PersistentTask` has `TaskStatus $status`.
- [ ] **P5.4** Extract common fields/constructor/accessors into a shared trait or value object; keep `RemoteTask`-specific and `PersistentTask`-specific members separate.
- [ ] **P5.5** Verify serialization round-trip and `@see` vocabulary alignment unchanged.
- [ ] **P5.6** Run channel + value-object tests; `composer test` green.

**Exit criteria:** Channel adapters deduplicated; `RemoteTask`/`PersistentTask` share common representation; tests pass.

---

## Phase P6 — Magic literals to constants

Addresses REVIEW §2 (phpmnd, 34 detections): M1.

- [ ] **P6.1** `src/Domain/CircuitBreaker.php:45` — default failure threshold `5` → named constant.
- [ ] **P6.2** `src/Domain/RetryPolicy.php:51` — default max attempts `3` → named constant.
- [ ] **P6.3** `src/Domain/SchedulerConfiguration.php:53,59` — bounds `1`, `1` → named constants.
- [ ] **P6.4** `src/Conformance/Verifier/TraceParentageVerifier.php:99,106` — minimum event counts `2` → named constants.
- [ ] **P6.5** `src/Conformance/Verifier/GroupCancellationIsolationVerifier.php:66,77,96` — `'a-done'`, `'b-done'` → vocabulary constants.
- [ ] **P6.6** `src/Conformance/Fixture/*` — runtime name strings (`'AsyncRuntime/Amp'`, etc.) → vocabulary/constants.
- [ ] **P6.7** `src/Adapter/OpenTelemetry/OpenTelemetryTelemetryBridge.php:126,137,151` — `'unknown'`, `'succeeded'` → vocabulary constants.
- [ ] **P6.8** `src/Adapter/Prometheus/PrometheusTelemetryBridge.php:180` — `'succeeded'` → vocabulary constant.
- [ ] **P6.9** Add `@see` vocabulary IRI references to every new constant.
- [ ] **P6.10** Re-run phpmnd; confirm remaining detections are legitimate defaults (thresholds, bounds) only.

**Exit criteria:** No bare magic strings in conformance verifiers/telemetry bridges; magic numbers reduced to named, documented constants.

---

## Phase P7 — Modern PHP features

Addresses REVIEW §7: M2 (lazy objects), M3 (WeakMap/WeakReference).

- [ ] **P7.1** Replace `LazyTelemetryBridge` `Closure` factory with PHP 8.4 `ReflectionFactory::createLazy()` for true lazy initialization (remove closure wrapper).
- [ ] **P7.2** Add `WeakMap` for per-task metadata in the scheduler to avoid retaining references to completed tasks; evaluate `FiberScopeSlotTrait` fallback property as a `WeakReference`.
- [ ] **P7.3** Verify lazy object + WeakMap introduce no hot-path regression and do not break conformance Assertion A/C.
- [ ] **P7.4** Confirm no feature is adopted merely because available (each change adds clarity).

**Exit criteria:** Lazy objects used where clarity improves; WeakMap adopted for non-owning per-task metadata; behavior unchanged.

---

## Phase P8 — Performance hot paths

Addresses REVIEW §3 Concerns (scheduler O(n)/O(n²) hot paths).

- [ ] **P8.1** Optimize `FiberTaskScheduler::drainExpiredTimers()` — avoid O(n) iteration of all timers every tick (nearest-deadline tracking / timer wheel).
- [ ] **P8.2** Optimize `clearTimers()` — avoid `array_filter` + `array_values` O(n) copy on every completion.
- [ ] **P8.3** Optimize `runUntil()` → `computeTimeout()` → `computeNearestTimerDeadline()` — O(n) scan on every poll iteration.
- [ ] **P8.4** Confirm `EmulatedChannel::send()`/`trySend()` `$size = count($this->queue) - $this->head` is O(1); add benchmarks.
- [ ] **P8.5** Add phpbench benchmarks for task spawn/completion, suspension/resumption, poll-handle registration/teardown (feeds P10).

**Exit criteria:** Hot-path complexity reduced; benchmarks written; no claimed improvement without measurement.

---

## Phase P9 — Test coverage to ≥95%

Addresses REVIEW §9 (H4): below-95% coverage, empty Integration/Concurrency suites, incomplete conformance.

- [ ] **P9.1** Fill empty Integration suite — adapter integration tests (Revolt, Swoole, Swow, Native, Select where extensions available).
- [ ] **P9.2** Fill empty Concurrency suite — fiber/coroutine isolation tests, persistent-worker leak tests.
- [ ] **P9.3** Add tests for low-coverage classes: `AsyncRuntime` (42.02% lines), `AsyncRuntimeFactory` (66.01%), `SelectGroup` (48%), `RaceGroup` (73.46%), `TaskGroup` destructor path, `FiberTaskScheduler`, `TaskResult` failure path.
- [ ] **P9.4** Add conformance tests for Assertion A (context isolation), B (temporal precision), C (deterministic cleanup) where currently skipped/incomplete (4 skipped).
- [ ] **P9.5** Add negative tests: context-bleed, handle-leak, conflicting completion (`ConflictingCompletionException`), recursive publication.
- [ ] **P9.6** Run `composer test --coverage`; confirm ≥95% lines/methods/class coverage; confirm no cross-suite coverage leakage (e.g. `AsyncRuntime` counted in multiple suites).
- [ ] **P9.7** Run `composer run mutation`; drive survived mutants to 0 in refactored areas (state transitions, cancellation/timeout, context restore, handle cleanup, capability selection, validators, adapter mappings).

**Exit criteria:** Coverage ≥95%; Integration + Concurrency suites non-empty; conformance A/B/C green; mutation clean in touched components.

---

## Phase P10 — Benchmarks

Addresses REVIEW §3 / M6 (phpbench configured, no benchmarks written).

- [ ] **P10.1** Add phpbench benchmarks for task spawn/completion.
- [ ] **P10.2** Add benchmarks for Fiber suspension/resumption.
- [ ] **P10.3** Add benchmarks for poll-handle registration/teardown.
- [ ] **P10.4** Run benchmarks; document baseline measurements in `docs/` (do not claim improvement without measurement).

**Exit criteria:** Benchmarks exist and run; baselines documented.

---

## Phase P11 — Security hardening

Addresses REVIEW §4 Concerns.

- [ ] **P11.1** `FederatedScheduler::submit()` — cancellation token data embedded into task payload under scalar keys crosses the federation boundary; add server-side validation of the payload on the remote side (or avoid scalar-key embedding).
- [ ] **P11.2** `RedisTaskQueue` — regex key validation exists (`/^[A-Za-z0-9._:-]+$/`); audit `pipeline()` dynamic calls for Redis command injection via caller-controlled keys; use parameterized/escaped command construction.
- [ ] **P11.3** Confirm no secrets/hardcoded credentials in code; debug surface (`SchedulerConfiguration::enableDebug()`) off by default.
- [ ] **P11.4** `composer run security:audit` (`composer audit --abandoned=report`) clean.

**Exit criteria:** Federation payload and Redis pipeline reviewed for tampering/injection surface; no secrets; audit clean.

---

## Phase P12 — Roadmap primitives `spawn()` / `awaitReadable()` (low priority, optional)

Addresses REVIEW §5 / L1: README examples reference `spawn()`/`awaitReadable()` as available, but they are "Phase 6+ roadmap" and only single-root-task `run()` is supported.

- [ ] **P12.1** Make a documented scope decision: implement `spawn()`/`awaitReadable()` now, or defer to the roadmap and align README. (REVIEW marks L1; do not guess — record the decision.)
- [ ] **P12.2** If implementing: add `spawn()`/`awaitReadable()` primitives to `AsyncRuntime`/scheduler; update README examples; add conformance coverage.
- [ ] **P12.3** If deferring: update README to mark `spawn()`/`awaitReadable()` as planned, not currently available; align all doc examples with the actual `run()`-only API.

**Exit criteria:** Decision documented; README consistent with the actual public API.

---

## Phase P13 — Final quality gate & documentation

- [ ] **P13.1** Run full `composer quality` (validate → assumptions → cs:check → analyse → architecture → lint:length → lint:types → test → mutation → security:audit); all green.
- [ ] **P13.2** Update documentation affected by public/API changes: README, `docs/EXECUTION.md` if touched, vocab `@see` alignment on every new/changed public term.
- [ ] **P13.3** Create/update an ADR under `docs/background/` if any change affects aggregate boundaries, dependency direction, public extension points, lifecycle semantics, failure guarantees, context propagation, package splitting, security defaults, or serialization compatibility.
- [ ] **P13.4** Review the full diff; remove accidental or speculative changes; confirm no unrelated code was rewritten.

**Exit criteria:** `composer quality` green; documentation current; ADRs updated where required; no unrelated changes.

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
1. Exact current test-coverage percentage and surviving-mutant count (requires `pcov`/infection run in a clean checkout).
2. Whether `docs/EXECUTION.md` exists and is current (referenced as a source of truth but not located).
3. The team's decision on `spawn()`/`awaitReadable()` scope (implement now vs. defer to roadmap).
4. Whether to add `Adapter` to phparkitect's Application allowed dependencies or move assembly factories to SDK (architectural boundary question; REVIEW §4/P2 touches SDK→Adapter construction).

These are recorded here so the plan remains honest about what is assumed versus verified.
