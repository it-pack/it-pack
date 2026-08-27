# AsyncIt — Refactoring & Optimization Plan

> **Source:** `docs/REVIEW.md` (in-depth review, post Phase 5).
> **Goal:** resolve every finding (F1–F12) so `composer quality`
> (`cs:check`, `analyse`, `architecture`, `lint:*`, `test`, `mutation`) is green
> in a clean checkout, while preserving the verified behavior (conformance
> Assertion A/C, RunIt `ExecutionEngineVerifier`, capability-driven polling).
>
> **Convention note:** follow `AGENTS.md` — `final`+`readonly` where possible,
> factories/builders for instantiation, ports/interfaces at boundaries, no
> `else`/`elseif`, no Yoda, no magic literals, one type per file, ≤120 chars,
> declaration order, and `@see` vocabulary alignment. Keep changes small and
> reviewable; do not alter scheduling semantics.
>
> **Verification caveat (from REVIEW.md):** PHPStan, Infection, and code coverage
> cannot run in the current environment (pre-existing `rector`/`php-parser`
> autoload conflict; no coverage driver). Each milestone therefore lists a
> *standalone* verification (syntax + Codeception run + `php-cs-fixer --dry-run`)
> that can be executed here, and a *clean-checkout* gate (`composer quality`) to
> confirm once the environment conflict is resolved.

---

## 0. Checklist (conceptual)

- **Unblock static analysis** — remove the `else`, and reconcile the instantiation rule with value-object/exception construction so `composer analyse` passes.
- **Unblock architecture** — remove `SDK → Adapter` dependencies so Deptrac is clean.
- **Honour abstraction rules** — eliminate the sole `abstract class`; back concrete classes (incl. the composition root) with interfaces or an explicit exemption policy.
- **Tighten style & literals** — wrap long lines, name the `Io\Poll` readiness constant, scope test coverage.
- **Leverage modern PHP & fix doc drift** — adopt `WeakMap` for non-owning per-fiber mappings; align ROADMAP with reality.
- **Prove quality** — run coverage + mutation in a clean checkout and triage pre-existing test failures.

---

## 1. Milestones & dependencies

```
R0  Prep / baseline (optional, unblocks tooling in CI)
 │
R1  Unblock `composer analyse`          ← F2 (else), F1 (rule conflict)
 │
R2  Unblock `composer architecture`     ← F11 (SDK→Adapter)
 │
R3  Abstraction & interface discipline  ← F3 (abstract class), F4 (interface backing)
 │
R4  Style & magic-literal hygiene      ← F5 (line length), F6 (magic number),
 │                                        F7 (FQN imports), F12 (id prefixes)
 │
R5  Modern PHP & doc accuracy          ← F8 (WeakMap), F10 (trait/doc drift)
 │
R6  Coverage & mutation proof           ← F9 (@covers), measure coverage/mutation,
                                          triage pre-existing failures
```

R1–R4 are independent of each other except that R1/F1 (rule refinement) should
land before R3/R4 produce analysis noise. R6 is last because it needs a
coverage-capable environment.

---

## 2. R0 — Baseline & tooling (optional) — ✅ DONE

**Goal:** make `composer analyse`/`architecture` executable in CI by removing the
`rector`/`php-parser` autoload conflict that currently blocks verification.

**Tasks**
- [x] Reproduce the `rector`/`nikic/php-parser` redeclaration locally and isolate it to a `require-dev` conflict (do not modify `src/`).
- [x] Add a CI job that runs `composer validate`, `cs:check`, `analyse`, `architecture`, `lint:length`, `lint:types`, `test` on a PHP 8.4+ matrix.
- [x] Confirm a coverage driver (pcov/xdebug) is available for R6.

**Resolution (verified 2026-08-27):** the original blocker no longer
reproduces. `composer analyse`, `composer architecture`, `composer lint:*`,
`composer cs:check`, and `composer test:unit` all execute in this checkout.
Root cause of the *prior* "rector/php-parser" symptom: `rector/rector` bundles
its own **isolated** copy at `vendor/rector/rector/vendor/nikic/php-parser`,
which is **not** on the main Composer autoload path, so it never collides with
the root `vendor/nikic/php-parser`. No `require-dev` change is required.
`pcov` is installed (PHP 8.5.9), so R6 coverage/mutation is feasible.

**CI job added:** `.github/workflows/quality.yml` at the monorepo root
(`it-pack`). It checks out the monorepo (RunIt resolves via the `../RunIt` path
repository to `src/RunIt`), runs `codecept build`, then the full `composer
quality` sequence on a PHP `8.4`/`8.5` matrix. No `src/` changes were made.

**Gates:** `composer quality` (minus `mutation`) is runnable end-to-end; CI
codifies it. (2 pre-existing unit-test failures in `test:unit` are scoped to R6,
not R0.)

---

## 3. R1 — Unblock `composer analyse`  (F2, F1)

**Goal:** `composer analyse` exits 0. Two blockers: the single `else` and the
`ForbidNewOutsideFactoryBuilderRule` conflict with value-object/exception `new`.

### Task R1.1 — Remove the only `else` (F2)
- **File:** `src/Application/Scheduler/FiberTaskScheduler.php:112`
- Replace:
  ```php
  if ($fiber->isStarted()) {
      $fiber->resume();
  } else {
      $fiber->start();
  }
  ```
  with a guard/ternary (no `else`):
  ```php
  if ($fiber->isStarted()) {
      $fiber->resume();
      return;
  }

  $fiber->start();
  ```
  (or `$fiber->isStarted() ? $fiber->resume() : $fiber->start();`). Keep the
  subsequent `if ($fiber->isTerminated())` block unchanged.
- **Verify:** `php -l` on the file; `vendor/bin/php-cs-fixer fix --dry-run` shows
  no `else`; `vendor/bin/codecept run Unit tests/Unit/Application/Scheduler/FiberTaskSchedulerTest.php` still green.

### Task R1.2 — Reconcile the instantiation rule (F1)
The rule (`tools/phpstan/asyncit-rules/src/Rule/ForbidNewOutsideFactoryBuilderRule.php`)
forbids `new` of named types outside `*\Factory\*` / `*\Builder\*` / `Conformance`.
But value objects and exceptions are constructed in `Adapter`/`Domain`:
- `StreamPollHandle` — `SelectPoller`, `NativePoller`, every `*Poller` adapter, both interop bridges.
- `*ContextToken` (`SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`) — in the storage adapters.
- Exceptions — `Domain/Task.php`, `Domain/Channel/EmulatedChannel.php`, storage adapters.

Two compatible fixes (choose one, document the decision):

- **Option A (recommended) — exempt value objects + exceptions in the rule.**
  Introduce a marker interface `ItPack\AsyncIt\Contract\ValueObjectInterface` and
  have `StreamPollHandle`, the three context tokens, `TaskId`, and other value
  objects implement it. Then extend `ForbidNewOutsideFactoryBuilderRule` to skip
  `new` of any type that implements `ValueObjectInterface` **or** `Throwable`
  (or `AsyncItExceptionInterface`). This preserves the rule's intent (no ad-hoc
  `new` of *services/collaborators*) while permitting idiomatic value/exception
  construction.
- **Option B — inject value-object factories.** Add `StreamPollHandleFactory`
  (and token factories) built in `Application\Factory` and injected into the
  pollers/storages. Heavier; changes many constructor signatures.

- **File:** `tools/phpstan/asyncit-rules/src/Rule/ForbidNewOutsideFactoryBuilderRule.php`
  (extend `processNode` to read the constructed class's interfaces; skip when the
  type implements `ValueObjectInterface` or `Throwable`).
- **Files (if Option A):** add `Contract/ValueObjectInterface.php`; add
  `implements ValueObjectInterface` to `Contract/Port/StreamPollHandle.php`,
  `Adapter/{Swoole,Swow,Revolt}/*ContextToken.php`, `Contract/TaskId.php`.
- **Verify:** `php -l` on changed files; in a clean checkout
  `composer analyse` is green (no `ForbidElseRule` / `ForbidNewOutsideFactoryBuilderRule`
  errors for the value-object/exception sites).

**Gates (R1):** `cs:check` clean; `composer analyse` green in clean checkout;
`FiberTaskSchedulerTest` + Phase 5 unit tests still pass.

---

## 4. R2 — Unblock `composer architecture`  (F11)

**Goal:** Deptrac reports 0 violations. All 21 are
`SDK\Factory\AsyncRuntimeFactory → Adapter\*` (it `new`s Swoole/Swow/Revolt
storage/channel/poller classes directly).

### Task R2.1 — Extract an adapter assembler
- Add `Application\Factory\RuntimeAdapterAssemblerInterface` (method
  `assemble(RuntimeAdapterKind $kind, CapabilityDescriptor $descriptor): RuntimeAssembly`
  returning the poller/storage/channel tuple) and a
  `Application\Factory\RuntimeAdapterAssembler` that `new`s the adapter classes
  (living in `Application\Factory`, so allowed).
- Refactor `AsyncRuntimeFactory` (`src/SDK/Factory/AsyncRuntimeFactory.php`) to
  depend on `RuntimeAdapterAssemblerInterface` (injected or pulled from a factory)
  instead of `new`-ing `SwoolePoller`/`SwowPoller`/`RevoltPoller`,
  `*CoroutineContextStorage`, `*Channel`, and `\Swoole\Coroutine\Channel` /
  `\Swow\Coroutine\Channel`.
- Keep the capability/version negotiation (`nativePollAvailable()`,
  `EdgeTriggeredPolling` advertisement) in `AsyncRuntimeFactory`; move only the
  *construction* of adapter concretes into the assembler.

**Gates (R2):** `composer architecture` → 0 violations; `RuntimeAdapterConformanceTest`
(Swoole/Revolt/Swow) and `AsyncRuntimeTest` still pass.

---

## 5. R3 — Abstraction & interface discipline  (F3, F4)

**Goal:** no `abstract class`; every concrete class is interface-backed (or
explicitly exempted and documented).

### Task R3.1 — Remove `AbstractRuntimeFixture` (F3)
- **File:** `src/Conformance/Fixture/AbstractRuntimeFixture.php`
- Convert the abstract class into a trait `RuntimeFixtureTrait` holding the
  wiring (`scheduler()`, `poller()`, `contextStorage()`, `clock()`,
  `publisher()`, `activePollHandleCount()`, `makeExecutionScope()`), and have the
  6 fixtures (`SwooleRuntimeFixture`, `SwowRuntimeFixture`, `RevoltRuntimeFixture`,
  `NativeRuntimeFixture`, `ReactPhpRuntimeFixture`, `AmpRuntimeFixture`) `use` it
  and `implement AsyncRuntimeFixtureInterface` directly. (They already `extend
  AbstractRuntimeFixture`; swap `extends` → `use` + `implements`.)
- Delete `AbstractRuntimeFixture.php`.

### Task R3.2 — Back the composition root with an interface (F4)
- Add `SDK\Factory\RuntimeAssemblerInterface` (e.g. `discover(): AsyncRuntime` /
  `create(): AsyncRuntime`) and have `AsyncRuntimeFactory` implement it. This
  honours DIP and eases testing/inversion.

### Task R3.3 — Value-object exemption policy (F4, pairs with R1.2)
- If R1.2 Option A was chosen, `TaskId`/`StreamPollHandle`/tokens already
  implement `ValueObjectInterface` — document that value objects are exempt from
  the "interface-backed" rule via this marker.
- `TaskRecord` (`Application/Scheduler/TaskRecord.php`) is an internal scheduler
  DTO; either give it an interface or add a one-line note in `AGENTS.md` that
  internal scheduler records are intentionally interface-less. Prefer the note to
  avoid over-abstraction.

**Gates (R3):** `grep -rn "abstract class" src/` → none; `AsyncRuntimeFactory`
implements an interface; `composer analyse`/`architecture` still green.

---

## 6. R4 — Style & magic-literal hygiene  (F5, F6, F7, F12)

**Goal:** `cs:check` + `lint:length` clean; no magic literals in new/changed code.

### Task R4.1 — Wrap the two long lines (F5)
- `src/Application/Factory/TaskFactory.php:26` (127 chars) — wrap the `return new Task(...)` arguments.
- `src/SDK/Factory/AsyncRuntimeFactory.php:168` (138 chars) — split the `match`
  arm:
  ```php
  return match ($kind) {
      RuntimeAdapterKind::Swoole => new SwoolePoller(),
      RuntimeAdapterKind::Swow => new SwowPoller(),
      RuntimeAdapterKind::Revolt => new RevoltPoller(),
      RuntimeAdapterKind::Reference => (new PollerFactory())->create($descriptor),
  };
  ```
  (each arm already one-per-line; ensure no arm exceeds 120).

### Task R4.2 — Name the `Io\Poll` readiness bit (F6)
- **File:** `src/Application/Poller/NativePoller.php`
- Add `private const READABLE = 1;` with `@see https://w3id.org/itpack/asyncit#Poller`
  (and a note that `1` maps to `Io\Poll::READABLE`); replace
  `($register)($stream, 1)` with `($register)($stream, self::READABLE)`.

### Task R4.3 — Import optional runtime types (F7, optional)
- In the adapters, replace inline FQN (`\Swoole\Event`, `\Revolt\EventLoop`,
  `\Swow\Coroutine`, `\Io\Poll`, `\React\EventLoop\LoopInterface`, `\Amp\Loop`)
  with `use` statements behind `class_exists` where the symbol is referenced at
  class scope. This is cosmetic (the dynamic dispatch already avoids hard
  dependencies); skip if it adds noise.

### Task R4.4 — Promote handle-id prefixes to constants (F12, optional)
- The `'select-'`, `'native-'`, `'swoole-'`, `'swow-'`, `'revolt-'`, `'react-'`,
  `'amp-'` prefixes in the pollers/bridges may stay as literals (they are
  internal debug ids), but if reused across files, hoist to a
  `Contract\Port\StreamPollHandleVocabulary` interface constant set. Low priority.

**Gates (R4):** `composer lint:length` clean; `php -l` clean; `php-cs-fixer fix --dry-run` clean.

---

## 7. R5 — Modern PHP leverage & doc accuracy  (F8, F10)

**Goal:** actually use `WeakMap` for non-owning per-fiber mappings; align docs
with code.

### Task R5.1 — Adopt `WeakMap` for per-fiber/per-coroutine slots (F8)
- `src/Application/Context/MemoryContextStorage.php`: add an optional
  `WeakMap<Fiber, array>` per-fiber slot (in addition to the LIFO stack) so a
  fiber's most recent scope is reachable without owning the fiber — matches the
  existing docblock claim.
- `src/Adapter/Revolt/RevoltFiberContextStorage.php`: replace the per-fiber
  `array` keyed by `spl_object_id`/`WeakReference` with a `WeakMap<Fiber, object{...}>`
  so detached fibers are collected automatically (non-owning, per AGENTS M2).
- Keep the `finally` detach and the process-level LIFO fallback for the
  non-coroutine conformance path.

### Task R5.2 — Fix ROADMAP doc drift (F10)
- `docs/ROADMAP.md` Phase 1 notes claim `LifecycleMatrixTrait` and
  `ThrowableNormalizationTrait` exist. Either:
  - extract those traits from the inline logic in `Domain/Task.php` (and reuse in
    any future stateful entity), or
  - correct the ROADMAP text to state the lifecycle matrix is currently inline in
    `Task` (no trait yet).
- Add a `docs/REVIEW.md` → `docs/REFACTORING_PLAN.md` cross-link if not present.

**Gates (R5):** `WeakMap` instantiated where claimed; `MemoryContextStorage`/
`RevoltFiberContextStorage` unit tests (LIFO + detached-fiber collection) pass;
ROADMAP text matches the tree.

---

## 8. R6 — Coverage & mutation proof  (F9, + measurement)

**Goal:** ≥95% coverage with no leakage, Infection clean; pre-existing test
failures triaged.

### Task R6.1 — Scope the unscoped tests (F9)
- Add `@covers` to the 3 test classes missing it:
  - `tests/Conformance/RuntimeAdapterConformanceTest.php` → `@covers \ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier` (and the Swoole/Revolt/Swow/Native/ReactPhp/Amp fixtures as applicable).
  - `tests/Unit/Channel/ChannelTest.php` → `@covers \ItPack\AsyncIt\Domain\Channel\EmulatedChannel` (+ the Swoole/Revolt channels exercised).
  - `tests/Unit/SDK/LazyTelemetryBridgeTest.php` → `@covers \ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge`.
- Ensure `@covers` is strictly scoped so coverage does not leak onto unrelated code.

### Task R6.2 — Triage the two pre-existing unit failures (not from Phase 5)
- `LazyTelemetryBridgeTest::events()` calls an undefined `events()` method.
  Either add `events()` to `LazyTelemetryBridge` (if it is the intended accessor)
  or fix the test to read recorded events via the real API.
- `ChannelTest::testSwooleChannelRoundTrip` asserts a value pushed into a
  `Swoole\Coroutine\Channel` outside a coroutine returns that value; it currently
  gets `true`. Gate this test behind a real Swoole coroutine context (or fix the
  channel usage) so it is meaningful rather than always-failing.

### Task R6.3 — Measure coverage & mutation (clean checkout)
- Run `composer test --coverage` (pcov/xdebug) and publish the percentage;
  confirm ≥95% and that no test leaks coverage beyond its `@covers`.
- Run `composer mutation` (Infection, already configured via `infection.json5`)
  and drive mutants to 0 survived in the refactored areas (scheduler lifecycle,
  cancellation/timeout, context restore, handle cleanup, capability selection,
  validators).

**Gates (R6):** `composer test` green including the two triaged tests;
`composer mutation` clean for the touched components; coverage report ≥95% with
scoped `@covers`.

---

## 9. Status updates (milestones)

- **After R0:** the tooling blocker is gone (rector's bundled `php-parser` is
  isolated, not a collision); `composer quality` runs end-to-end and is
  codified in `.github/workflows/quality.yml`. Next: unblock `composer analyse` (R1).
- **After R1:** `composer analyse` is green — the `else` is gone and the
  instantiation rule permits value-object/exception construction. Next: architecture.
- **After R2:** Deptrac reports 0 violations; `AsyncRuntimeFactory` no longer
  depends on `Adapter`. Next: abstraction rules.
- **After R3:** no `abstract class`; the composition root and value objects are
  interface-backed (or explicitly exempted). Next: style.
- **After R4:** `cs:check` + `lint:*` clean; no magic literals in changed code.
  Next: modern-PHP leverage & docs.
- **After R5:** `WeakMap` is actually used for non-owning per-fiber slots; ROADMAP
  matches the tree. Next: coverage/mutation proof.
- **After R6:** ≥95% coverage met, Infection clean, and the two pre-existing test
  failures are resolved. **Refactoring complete.**

---

## 10. Risk & rollback notes

- **R1.2 Option A** changes a custom PHPStan rule; if the marker interface is
  missed on a value object, that object's `new` will again be flagged — keep the
  `ValueObjectInterface` list exhaustive.
- **R2** moves construction only; no scheduling-semantics change, so conformance
  A/C and `ExecutionEngineVerifier` must remain green after the move.
- **R5.1** changes context-storage internals; re-run `ContextStorageVerifier`
  (RunIt) and Assertion A after adopting `WeakMap`.
- Each milestone is independently revertible; land behind small PRs and keep
  `composer test` green at every step.
