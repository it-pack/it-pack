# TODOS — AsyncIt Open Tasks

> **Purpose:** Package-level checklist of all open tasks for `it-pack/async-it`.
> Updated: 2026-10-29
>
> **How to use:** Check off tasks when complete. If a task description here
> differs from the source doc (`REFACTORING_PLAN.md`), the source doc version wins.

---

## 🟣 AsyncIt refactoring — post-implementation quality gates (R1–R6)

> **Source:** `docs/REFACTORING_PLAN.md`, `docs/REVIEW.md`, `docs/ROADMAP.md`
>
> **Status snapshot (2026-10-29):** All implementation phases M0–M10 are complete.
> PHPStan (`composer analyse`) reports 0 errors. phparkitect reports **21 violations**
> (12 Contract-internal `Contract → Port`/`Domain` + 9 `AsyncRuntimeFactory → Adapter\*`).
> `composer test` has 1 failure (`ChannelTest::testSwooleChannelRoundTrip`) + 1 skip
> (`PollerFactoryTest` — PHP 8.6 `Io\Poll` absent). `composer cs:check` reports 30 files
> needing fixes (unused imports, import ordering). `composer quality` is **not** green.
> R0 and R1 are complete; R2–R6 are open.

---

### R0 — Baseline & tooling ✅ COMPLETE

- [x] Tooling conflict (`rector`/`php-parser`) no longer reproduces; all QA tools execute on PHP 8.5.12
- [x] CI job `.github/workflows/quality.yml` codifies the full `composer quality` sequence
- [x] `pcov` available (PHP 8.5.12) for R6 coverage/mutation

---

### R1 — Unblock `composer analyse` ✅ COMPLETE

- [x] Remove the only `else` (`FiberTaskScheduler.php` → early-return guards)
- [x] Add `ValueObjectInterface` marker; rewrite `ForbidNewOutsideFactoryBuilderRule` to exempt VOs + `Throwable`
- [x] Recognize `*\Factory`/`*\Builder` namespace segments in the rule (bug fix)
- [x] Factory-inject `TaskRecord`, `WeakMap`, `FiberScopeSlot`
- [x] Mark `MemoryContextToken` / `SchedulerFailureArtifact` as value objects
- [x] Replace `object{stack,scopes}` shapes with mutable `FiberScopeSlot`
- [x] **Result:** `composer analyse` = 0 errors (down from 232)

---

### R2 — Unblock `composer architecture` (F11) — 21 violations open

> **Note:** The REVIEW.md F11 and REFACTORING_PLAN R2 originally characterized all
> 21 violations as `AsyncRuntimeFactory → Adapter\*`. The actual phparkitect
> output splits them differently:
> - **12 Contract-internal violations** — Contract classes depend on
>   `Contract\Port` (which the rules forbid) or on `Domain`:
>   - `CapabilityDescriptor` → `Contract\Port\CapabilityStatus/Kind` (4)
>   - `PollerPortInterface` → `Contract\Port\StreamPollHandle` (2)
>   - `TaskChannelInterface` → `Domain\Exception\Channel*` (3)
>   - `TaskInterface` → `Contract\Port\TaskStatus` (3)
> - **9 SDK→Adapter violations** — `AsyncRuntimeFactory` `new`s adapter concretes:
>   - SwoolePoller, SwowPoller, RevoltPoller, SwooleCoroutineContextStorage,
>     SwowCoroutineContextStorage, RevoltFiberContextStorage, SwooleChannel,
>     SwowChannel, RevoltChannel (9)

- [ ] R2a: Re-evaluate the `Contract → Contract\Port` rule — these depend on
  Port types that are co-located in the same package. Options: relax the rule,
  move the Port types to `Contract`, or add a documented exemption.
- [ ] R2b: Re-evaluate the `Contract → Domain\Exception` rule for
  `TaskChannelInterface` — depends on domain exceptions.
- [ ] R2c: Extract `Application\Factory\RuntimeAdapterAssemblerInterface` +
  `RuntimeAdapterAssembler`; move all adapter `new` from `AsyncRuntimeFactory`.
- [ ] `composer architecture` → 0 violations
- **Evidence:** `vendor/bin/phparkitect check` → 21 violations (12 Contract-internal, 9 SDK→Adapter)

---

### R3 — Abstraction & interface discipline (F3, F4) — open

- [ ] Convert `AbstractRuntimeFixture` (abstract class) → `RuntimeFixtureTrait` + interface; update 6 fixtures (`SwooleRuntimeFixture`, `SwowRuntimeFixture`, `RevoltRuntimeFixture`, `NativeRuntimeFixture`, `ReactPhpRuntimeFixture`, `AmpRuntimeFixture`)
- [ ] Add `SDK\Factory\RuntimeAssemblerInterface` so `AsyncRuntimeFactory` is interface-backed (DIP)
- [ ] Document `TaskRecord` as intentionally interface-less internal scheduler DTO
- **Evidence:** `grep -rn "abstract class" src/` → `AbstractRuntimeFixture.php:34`

---

### R4 — Style & magic-literal hygiene (F5, F6, F7, F12) — open

- [ ] Wrap 6 lines exceeding 120 chars:
  - `Application/Factory/TaskFactory.php:26` (127)
  - `SDK/Factory/AsyncRuntimeFactory.php:166` (138)
  - `SDK/Factory/AsyncRuntimeFactory.php:181` (129)
  - `Conformance/Fixture/SwooleRuntimeFixture.php:20` (132)
  - `Conformance/Fixture/SwowRuntimeFixture.php:20` (126)
  - `Conformance/Fixture/RevoltRuntimeFixture.php:23` (139)
- [ ] Name the `Io\Poll` readiness constant in `NativePoller` (`private const READABLE = 1;` with `@see`)
- [ ] Run `composer cs:fix` to clean up unused imports, import ordering, and class-element ordering across 30 files (php-cs-fixer)
- **Evidence:** `php tools/lint/line-length.php` → 6 violations; `vendor/bin/php-cs-fixer fix --dry-run` → 30 files need fixes

---

### R5 — Modern PHP leverage & doc accuracy (F8, F10) — partial

- [ ] Adopt `WeakMap` in `MemoryContextStorage` (docblock currently claims it's a "later optimization") OR correct the docblock. `RevoltFiberContextStorage` already uses `WeakMap` ✅
- [x] ROADMAP F10 doc drift fixed (Line 172: lifecycle logic is inline in `Task.php`, no `LifecycleMatrixTrait`/`ThrowableNormalizationTrait`)
- **Evidence:** `grep -rn "new WeakMap" src/` → only `AsyncRuntimeFactory.php:194` (for Revolt); `MemoryContextStorage` uses plain `array`

---

### R6 — Coverage & mutation proof (F9, + measurement) — open

- **R6.1 — Add `@covers` to 3 test classes:**
  - `tests/Conformance/RuntimeAdapterConformanceTest.php`
  - `tests/Unit/Channel/ChannelTest.php`
  - `tests/Unit/SDK/LazyTelemetryBridgeTest.php`
- **R6.2 — Fix remaining test failure:**
  - [ ] `ChannelTest::testSwooleChannelRoundTrip` — Swoole channel push returns `true` not `'hello'` outside a coroutine. Either gate behind `Swoole\Coroutine::run()` or remove if channels are always used inside coroutines by design.
  - [x] `LazyTelemetryBridgeTest::events()` — RESOLVED (accessor added to `LazyTelemetryBridge`)
- **R6.3 — Measure coverage & mutation (clean checkout):**
  - [ ] Run `composer test --coverage`; confirm ≥95%
  - [ ] Run `composer mutation` (Infection); drive survived mutants to 0
- **Evidence:** `vendor/bin/codecept run Unit` → 41 tests, 1 failure, 1 skipped; `grep -rn '@covers' tests/` → 3 files missing `@covers`
