# AsyncIt — In-Depth Code Review

> **Scope:** `src/AsyncIt` (ItPack `it-pack/async-it`), reviewed against `AGENTS.md`,
> `docs/ROADMAP.md`, the RunIt verifiers it reuses, and the project's own quality
> gates (`composer quality` → `cs:check`, `analyse`, `architecture`, `lint:*`,
> `test`, `mutation`).
>
> **Method:** static inspection of all 92 `src/` files and 15 test classes,
> execution of the runnable unit/conformance suites via Codeception, and the
> project's `php-cs-fixer --dry-run` and custom `lint:length` checks. Deptrac was
> executed.
>
> **Assumptions & limitations**
> - PHPStan (`composer analyse`) and Infection (`composer mutation`) could **not**
>   be executed in this environment because of the pre-existing `rector` /
>   `nikic/php-parser` autoload conflict documented in earlier phases, and no
>   code-coverage driver is available. Coverage ≥95% and mutation-clean are
>   therefore *asserted by the project but not measured here*; findings about
>   them are based on structure and `@covers` scoping, not a coverage report.
> - Findings cite `file:line` against the tree as reviewed (post Phase 5).
> - "Interface-backed" follows the project rule *"every concrete class `final`
>   and implements an appropriate interface"*; value objects and exceptions are
>   judged against that same rule and against the marker-interface convention.

---

## 1. Verdict at a glance

| Dimension | Rating | One-line basis |
|---|---|---|
| Hexagonal architecture & boundaries | **Strong** | Domain depends only on Contract/Port; clean layering except pre-existing SDK→Adapter leak. |
| Ports / Adapters / Factories (DI) | **Strong** | Collaborators injected; `PollerFactory` selects backends by capability. One rule conflict (F1). |
| Modern PHP leverage | **Good** | `final`+`readonly`, Fibers, property hooks, but **WeakMap never instantiated** (F8). |
| Coding style & magic-literal hygiene | **Good w/ gaps** | No Yoda, no `elseif`; one `else` (F2), 2 long lines (F5), one magic number (F6). |
| Interface backing / no abstract classes | **Partial** | All classes `final`; but an `abstract class` exists (F3) and several classes lack interfaces (F4). |
| Security | **Strong** | LIFO context detach in `finally`, deterministic handle release, no globals. |
| Performance | **Good** | Monotonic clocks, no hot-path reflection, portable `stream_select` probe. |
| DX & documentation | **Strong** | Thorough ROADMAP/AGENTS; vocabulary `@see` alignment; minor doc drift (F10). |
| Tests / coverage / mutation | **Good w/ gaps** | A/C conformance green on Swoole; 3 tests lack `@covers` (F9); coverage/mutation unmeasured here. |
| **Quality-gate readiness** | **RED** | `else`, line-length, and the `new`-rule conflict block `composer quality`. |

---

## 2. Architecture & hexagonal boundaries

The hexagon holds: `Contract` + `Contract/Port` (ports), `Domain` (pure model),
`Application` (scheduler, reference poller/context/publisher, factories),
`SDK` (composition root / `AsyncRuntime`), `Adapter/*` (runtime bindings), and
`Conformance` (verifier harness). Dependency direction is enforced by Deptrac.

- **Domain boundary is clean.** `grep` for `Application|SDK|Adapter|Conformance`
  inside `src/Domain/` returns nothing. `Task`, `CancellationScope`, `EmulatedChannel`
  depend only on Contract/Port and RunIt port *interfaces* — exactly as required.
- **Ports are small and stable.** `PollerPortInterface`, `ContextStorageInterface`
  (RunIt), `SchedulerInterface`/`EngineSchedulerInterface`, `TaskChannelInterface`,
  `TelemetryBridgeInterface`, `RuntimeDiscoveryInterface` are focused (ISP).
- **Pre-existing Deptrac debt (F11, Major).** `AsyncRuntimeFactory`
  (`src/SDK/Factory/AsyncRuntimeFactory.php`) `new`s Swoole/Swow/Revolt adapter
  classes directly, so SDK depends on `Adapter` — which the Deptrac ruleset
  forbids (SDK may depend on Contract/Port/Domain/Application only). Deptrac
  reports **21 violations, all `AsyncRuntimeFactory → Adapter`**. This is Phase 4
  debt; it does not involve any Phase 5 file but must be resolved before
  `composer architecture` is green. *Recommendation:* introduce an
  `Application\Factory\RuntimeAdapterAssembler` (or extend `PollerFactory`/
  `RuntimeDiscoveryFactory`) that owns adapter construction, so `AsyncRuntimeFactory`
  depends on an `Application` factory, not on `Adapter` concretes.

---

## 3. Ports, adapters, factories & DI

- **Factory-only instantiation largely holds.** All collaborators (task, fiber,
  scope, cancellation scope, poller, clock, storage, publisher, channel,
  telemetry) reach the scheduler via injected factories — good DIP.
- **`PollerFactory` (Phase 5) is the right shape.** `create(CapabilityDescriptor)`
  selects `NativePoller` vs `SelectPoller` from `PollerFactory::nativePollAvailable()`
  **and** the descriptor's `EdgeTriggeredPolling` status; `AsyncRuntimeFactory`
  now advertises `EdgeTriggeredPolling = Supported` precisely when `Io\Poll` is
  present. Capability-driven, not runtime-probed.
- **Critical rule conflict (F1).** The custom `ForbidNewOutsideFactoryBuilderRule`
  forbids `new` of *any named type* outside `*\Factory\*` / `*\Builder\*` /
  `Conformance`. But value objects and exceptions are constructed elsewhere:
  - `StreamPollHandle` is `new`-ed in `SelectPoller`, `NativePoller`, and every
    `*Poller` adapter (`src/Adapter/{Swoole,Swow,Revolt}/...Poller.php`,
    `src/Adapter/Interop/{ReactPhp,Amp}/...Bridge.php`,
    `src/Application/Poller/{Select,Native}Poller.php`).
  - Context tokens (`SwooleContextToken`, `SwowContextToken`, `RevoltContextToken`)
    are `new`-ed inside the corresponding context-storage adapters.
  - Domain/adapter exceptions are `new`-ed in `Domain/Task.php`,
    `Domain/Channel/EmulatedChannel.php`, and the storage adapters.
  Because the call sites are in `Adapter`/`Domain` (not Factory/Builder/Conformance),
  **the rule would fail `composer analyse` on these lines.** This is the single
  most important finding: the instantiation policy as *encoded* is stricter than
  the codebase's *practice*. *Recommendation:* refine the rule to exempt
  (a) `Throwable` / the `AsyncItExceptionInterface` marker, and (b) value objects
  via a `ValueObjectInterface` marker (or an exempt namespace such as
  `Contract\Port` value objects). This keeps the intent (no ad-hoc `new` of
  *services/collaborators*) while permitting idiomatic value-object/exception
  construction.

---

## 4. Modern PHP leverage

- **`final` + `readonly`:** every concrete class is `final` (verified — zero
  non-`final` classes). 9 `readonly` classes (value objects / descriptors).
- **Fibers:** `Application/Factory/FiberFactory.php` builds the `Fiber`; the
  scheduler suspends/resumes. Correct, capability-neutral model.
- **Property hooks:** used in `TaskId`, `TaskStatus` (controlled individuals),
  `SchedulerFailureArtifact`, `ConformanceResult`, `ArrayTelemetryRecorder` —
  good use for validation/virtual projections.
- **WeakMap — documented but not used (F8, Minor).** `AGENTS.md` and the
  `MemoryContextStorage` docblock mention `WeakMap<Fiber,Context>`, but
  `grep "new WeakMap"` finds **no instantiation** in either
  `MemoryContextStorage` (uses `private array $stack` / `$scopes`) or
  `RevoltFiberContextStorage`. The comment is misleading and a genuine
  non-owning per-fiber mapping opportunity is missed. *Recommendation:* adopt
  `new WeakMap()` for per-fiber/per-coroutine slots (esp. `RevoltFiberContextStorage`
  and a per-fiber slot in `MemoryContextStorage`), and correct the docblock.
- **Lazy objects:** none via the Reflection API. `LazyTelemetryBridge` is a
  hand-written lazy proxy (materialises the recorder on first `record()`), which
  is acceptable and matches the "lazy bridge" intent; adopting
  `ReflectionClass::newLazyGhost` is an optional future enhancement (AGENTS M4).

---

## 5. Coding standards & magic-literal hygiene

- **No Yoda style** — `grep` for `literal === $var` returns nothing.
- **No `elseif`**; exactly **one `else`** (F2, Critical for `analyse`):
  `src/Application/Scheduler/FiberTaskScheduler.php:112`
  ```php
  if ($fiber->isStarted()) {
      $fiber->resume();
  } else {
      $fiber->start();
  }
  ```
  *Fix:* `$fiber->isStarted() ? $fiber->resume() : $fiber->start();` (or early
  guard). Removes the only `ForbidElseRule` violation.
- **Line length (F5, Major for `lint:length`):** two lines exceed 120 chars:
  - `src/Application/Factory/TaskFactory.php:26` (127)
  - `src/SDK/Factory/AsyncRuntimeFactory.php:168` (138 — the `match` arm
    `RuntimeAdapterKind::Reference => (new PollerFactory())->create($descriptor),`)
    should be wrapped one-argument-per-line.
- **Magic number (F6, Minor):** `NativePoller` calls
  `($register)($stream, 1)` where `1` means `Io\Poll::READABLE`. Because the class
  avoids a hard `Io\Poll` symbol (correct for analysability), the literal is
  unavoidable *as written* — but it should be a named constant:
  `private const READABLE = 1;` with a `@see` to the `Io\Poll` event constant.
- **Vocabularies live in interfaces:** `CapabilityVocabulary`,
  `CapabilityStatus`, `TaskStatusVocabulary`, `ViolationVocabulary`,
  `RuntimeAdapterKind`, `CapabilityKind` are interface/enum vocabulary homes —
  compliant. The only vocabulary gap is F6 (no `PollerEvent` constant for the
  READABLE bit).
- **Imports at top:** project classes are imported; runtime extension classes
  (`\Swoole\Event`, `\Revolt\EventLoop`, `\Swow\Coroutine`, `\Io\Poll`,
  `\React\EventLoop\LoopInterface`, `\Amp\Loop`) are referenced via fully-qualified
  names inline (F7, Minor) — a deliberate, defensible choice for *optional* runtime
  types that may be absent, but technically a deviation from "import at top".
  Acceptable; optionally `use` them behind `class_exists` guards.
- **One type per file:** verified — no file declares more than one class/
  interface/trait/enum.
- **Declaration order:** sampled (`NativePoller`, `TaskId`, `AsyncRuntimeFactory`)
  — constructor → methods, public before private, factory methods before
  non-static; compliant. (The `php-cs-fixer --dry-run` diff only touched anonymous
  `new class ()` parenthesis style in tests, which are exempt from the
  `ForbidNewOutsideFactoryBuilderRule` and are cosmetic.)

---

## 6. Interface backing & the "no abstract classes" rule

- **`abstract class` present (F3, Major for the convention):** `AbstractRuntimeFixture`
  (`src/Conformance/Fixture/AbstractRuntimeFixture.php:33`) is extended by 6
  fixtures (Swoole/Swow/Revolt/Native/ReactPhp/Amp). AGENTS is explicit: *"No
  abstract classes. Replace any 'base' with a trait + composition."* *Fix:*
  convert `AbstractRuntimeFixture` into a `RuntimeFixtureTrait` (or a composed
  concrete `RuntimeFixtureBase` injected via the fixture constructor), eliminating
  the only abstract class.
- **Classes without an interface (F4, Major):** concrete classes that do not
  `implements` an interface include `TaskId` (value object), `TaskRecord`
  (internal scheduler record), and `AsyncRuntimeFactory` (composition root).
  Exceptions are correctly marker-backed (`AsyncItExceptionInterface`) — good.
  *Recommendation:* (a) decide explicitly which value objects are exempt and
  document that; (b) give `AsyncRuntimeFactory` an interface (e.g.
  `RuntimeAssemblerInterface`) to honour DIP and ease testing; `TaskRecord` may
  stay internal but should be noted as intentionally interface-less.

---

## 7. Security, performance, DX

- **Security — strong.** Context is detached in `finally` on every resume
  (Assertion A); poll handles are released on terminal transition (Assertion C);
  no global/static request state; classification is delegated to RunIt. The
  dynamic dispatch in `NativePoller`/`*Bridge` operates only on injected
  `object`s and is guarded by `method_exists`/`class_exists` — safe.
- **Performance — good.** Monotonic clocks only; no reflection on the hot path
  (`method_exists` checks run at registration, not per `await`); no eager
  serialization; bounded handle maps. The `stream_select` readiness probe in
  `SelectPoller`/`*Poller` is O(n) per tick — acceptable for the reference model
  and identical across backends.
- **DX — strong.** ROADMAP, AGENTS, and ADRs are thorough; vocabulary `@see`
  alignment is present on ports and enums. *Doc drift (F10, Minor):* ROADMAP
  Phase 1 notes claim `LifecycleMatrixTrait` and `ThrowableNormalizationTrait`
  exist, but **no trait files exist in `src/`** (cross-cutting lifecycle logic
  is inline in `Task.php`). Correct the roadmap or extract the traits.

---

## 8. Tests, coverage & mutation

- **Conformance:** `RuntimeAdapterConformanceTest` runs A+C on Swoole (passes
  here) and skips Native/ReactPHP/Amp where their host runtime is absent — correct
  gating. `ExecutionEngineVerifier` (RunIt) passes through `AsyncRuntime`.
- **Unit:** Phase 5 unit tests (`PollerFactoryTest`, `NativePollerTest`,
  `ReactPhpPollerBridgeTest`, `AmpPollerBridgeTest`) all pass; bridge/poller
  behaviour is validated against fakes without requiring the host runtime.
- **`@covers` scoping (F9, Minor):** 12/15 test classes declare `@covers`; the
  three missing are `RuntimeAdapterConformanceTest`, `ChannelTest`,
  `LazyTelemetryBridgeTest`. Add `@covers` to prevent coverage leakage onto
  unrelated code (per the ≥95% / no-leak gate).
- **Coverage & mutation — unverified here.** `infection.json5` exists (mutation
  is configured), but neither `composer test --coverage` nor `composer mutation`
  could be executed in this environment. The ≥95% and Infection-clean gates are
  **asserted by the project, not measured in this review.**
- **Pre-existing unrelated test failures** (not introduced by Phase 5, files
  untouched): `LazyTelemetryBridgeTest::events()` (calls an undefined
  `events()` method) and `ChannelTest::testSwooleChannelRoundTrip` (Swoole channel
  outside a coroutine returns `true`, not the value). These should be triaged
  separately.

---

## 9. Findings register

| ID | Area | Severity | Evidence | Recommendation |
|----|------|----------|----------|----------------|
| F1 | Factories / DI | **Critical** | `StreamPollHandle`, `*ContextToken`, exceptions `new`-ed in Adapter/Domain; conflicts with `ForbidNewOutsideFactoryBuilderRule` | Refine rule to exempt exceptions + value objects (marker interface / namespace) |
| F2 | Style | **Critical** | `FiberTaskScheduler.php:112` `else` | Replace with ternary/early-return (ForbidElseRule) |
| F3 | Abstraction | **Major** | `AbstractRuntimeFixture.php:33` + 6 `extends` | Convert to trait / composed concrete helper |
| F4 | Interfaces | **Major** | `TaskId`, `TaskRecord`, `AsyncRuntimeFactory` lack interfaces | Add interfaces / `RuntimeAssemblerInterface`; document value-object exemptions |
| F11 | Deptrac | **Major** | 21 violations `AsyncRuntimeFactory → Adapter` | Move adapter construction into an `Application\Factory` assembler |
| F5 | Style | **Major (gate)** | `TaskFactory.php:26`, `AsyncRuntimeFactory.php:168` >120 chars | Wrap arguments one-per-line |
| F6 | Magic literal | **Minor** | `NativePoller` `($register)($stream, 1)` | `private const READABLE = 1;` + `@see` |
| F7 | Imports | **Minor** | Runtime classes referenced FQN-inline in adapters | Optionally `use` behind `class_exists` |
| F8 | Modern PHP | **Minor** | `WeakMap` documented but never `new`-ed | Adopt `new WeakMap()` for per-fiber slots; fix doc |
| F9 | Tests | **Minor** | 3/15 test files lack `@covers` | Add `@covers` to prevent leakage |
| F10 | Docs | **Minor** | ROADMAP cites non-existent traits | Correct roadmap or extract traits |
| F12 | Magic literal | **Minor** | Handle id prefixes (`'native-'`, …) | Promote to constants if reused |

---

## 10. Prioritized recommendations

1. **Unblock `composer analyse` (F1, F2).** Refine `ForbidNewOutsideFactoryBuilderRule`
   to exempt value objects and exceptions, and remove the single `else`. These two
   are the only hard blockers to the analysis gate (besides the env conflict).
2. **Unblock `composer architecture` (F11).** Extract adapter construction out of
   `AsyncRuntimeFactory` into an `Application\Factory` assembler; resolves all 21
   Deptrac violations.
3. **Honour "no abstract classes" (F3) and interface backing (F4).** Trait-ify
   `AbstractRuntimeFixture`; give `AsyncRuntimeFactory` an interface; explicitly
   decide value-object exemption policy.
4. **Tidy style (F5, F6, F9).** Wrap the two long lines, name the `READABLE`
   constant, add `@covers` to the 3 tests.
5. **Leverage WeakMap (F8) and correct doc drift (F10).** Adopt `new WeakMap()` for
   non-owning per-fiber mappings; align ROADMAP with reality.
6. **Measure, don't assert (coverage/mutation).** Run `composer test --coverage`
   and `composer mutation` in a clean checkout (post rector conflict) and publish
   the numbers; triage the two pre-existing unit-test failures.

---

## 11. Assumptions

- The review tree is the committed post-Phase-5 state; `git` working tree was not
  modified by this review (documentation only).
- "Interface-backed" is judged by the project's own stated rule, not a universal
  OOP absolute; value objects/exceptions are discussed as nuances, not blanket
  failures.
- PHPStan/Infection/coverage output could not be produced here; conclusions about
  those gates are structural, not measured.
- Runtime-specific adapters (Swoole/Swow/Revolt) and the new Native/Interop
  pollers reference optional runtime types via guarded dynamic dispatch by design;
  this is treated as intentional and acceptable.
