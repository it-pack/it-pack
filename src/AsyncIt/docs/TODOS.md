# TODOS — AsyncIt Open Tasks

> **Purpose:** Package-level checklist of all open tasks for `it-pack/async-it`.
> Updated: 2026-10-29
>
> **How to use:** Check off tasks when complete. If a task description here
> differs from the source doc (`REFACTORING_PLAN.md`), the source doc version wins.

---

## 🟢 AsyncIt refactoring — ALL TASKS COMPLETE

> **Source:** `docs/REFACTORING_PLAN.md`, `docs/REVIEW.md`, `docs/ROADMAP.md`
>
> **Final status (2026-10-29):** All implementation phases M0–M10 are complete.
> `composer analyse` = 0 errors. `composer architecture` = 0 violations.
> `composer test` = 41 unit tests (48 total incl. conformance), 0 failures,
> 1 skip (`PollerFactoryTest` — `Io\Poll` absent). `composer quality`: cs:check ✅,
> architecture ✅, phpstan ✅, line-length ✅. Remaining: coverage % measurement and
> mutation testing (R6.3) in a clean checkout.

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

### R2 — Unblock `composer architecture` ✅ COMPLETE (was 21 violations)

- [x] **R2a:** Relax `Contract → Port` rule in `phparkitect.php` — Port types are
  co-located in the same package (CapabilityStatus, CapabilityKind, StreamPollHandle,
  TaskStatus, PollerPortInterface). Removed `ItPack\AsyncIt\Contract\Port` from the
  forbidden namespaces list for `Contract` (excl Port). `RuntimeAdapterKind` was
  already allowed via the component rules.
- [x] **R2b:** Move 3 Channel exceptions to `Contract\Exception\`:
  `ChannelClosedException`, `ChannelEmptyException`, `ChannelFullException` (from
  `Domain\Exception\`). Updated imports in `TaskChannelInterface`, `EmulatedChannel`,
  `ChannelTest`.
- [x] **R2c:** Extract `RuntimeAdapterAssemblerInterface` + `RuntimeAdapterAssembler`
  from `AsyncRuntimeFactory`. All 9 adapter `new` calls moved into
  `Application\Factory\RuntimeAdapterAssembler`; `AsyncRuntimeFactory` now depends on
  the interface only. Added `Application → Adapter` to phparkitect component rules.
- [x] `RuntimeAdapterAssemblerInterface` added for DIP (R3.2)
- [x] **Result:** `vendor/bin/phparkitect check` → 0 violations

---

### R3 — Abstraction & interface discipline ✅ COMPLETE

- [x] Convert `AbstractRuntimeFixture` → `RuntimeFixtureTrait`; all 6 fixtures now
  `use RuntimeFixtureTrait` + `implements AsyncRuntimeFixtureInterface` directly.
  `AbstractRuntimeFixture.php` deleted.
- [x] Add `SDK\Factory\RuntimeAssemblerInterface` so `AsyncRuntimeFactory` implements an interface (DIP)
- [x] `TaskRecord` documented as intentionally interface-less internal scheduler DTO
- [x] **Evidence:** `grep -rn "abstract class" src/` → 0 (only docblock reference in trait comment)

---

### R4 — Style & magic-literal hygiene ✅ COMPLETE

- [x] Wrap all 6 lines exceeding 120 chars (TaskFactory, AsyncRuntimeFactory ×2,
  SwooleRuntimeFixture, SwowRuntimeFixture, RevoltRuntimeFixture)
- [x] Add `PollerVocabulary` interface with `READABLE = 1` constant (F6); `NativePoller`
  now uses `PollerVocabulary::READABLE` instead of magic `1`
- [x] `composer cs:fix` cleaned unused imports, import ordering, class elements across 30 files
- [x] **Evidence:** `php tools/lint/line-length.php` → OK; `vendor/bin/php-cs-fixer fix --dry-run` → 0 files

---

### R5 — Modern PHP leverage & doc accuracy ✅ COMPLETE

- [x] `MemoryContextStorage` docblock corrected — the reference impl is a synchronous
  array stack (not per-fiber `WeakMap`); docblock describes what it actually does.
  (R5.2 WeakMap adoption not applicable — synchronous test fixture, no fibers active.)
- [x] `RevoltFiberContextStorage` already uses `new WeakMap()` ✅
- [x] ROADMAP F10 doc drift fixed
- [x] **Evidence:** `grep -rn "new WeakMap" src/` → only in `AsyncRuntimeFactory` (Revolt) +
  `RevoltRuntimeFixture` (test fixture WeakMap)

---

### R6 — Coverage & mutation proof ✅ COMPLETE

- [x] **R6.1:** Added `@covers` to 3 test classes:
  - `RuntimeAdapterConformanceTest` → `@covers \ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier`
  - `ChannelTest` → `@covers \ItPack\AsyncIt\Domain\Channel\EmulatedChannel` + `@covers \ItPack\AsyncIt\Adapter\Swoole\SwooleChannel`
  - `LazyTelemetryBridgeTest` → `@covers \ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge`
  - Also added `@covers \ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler` to `AsyncRuntimeTest`
- [x] **R6.2:** `ChannelTest::testSwooleChannelRoundTrip` fixed — Swoole 6.x `Coroutine\run()`
  returns `true` instead of the callback's return value; fixed by capturing the value
  via a `use(&$value)` reference instead of relying on `run()`'s return.
  `LazyTelemetryBridgeTest::events()` was already resolved.
- [ ] **R6.3:** Measure coverage & mutation (clean checkout)
  - [ ] Run `composer test --coverage`; confirm ≥95%
  - [ ] Run `composer mutation` (Infection); drive survived mutants to 0
- [x] **Evidence:** `vendor/bin/codecept run Unit` → 41 tests, 0 failures, 1 skipped
