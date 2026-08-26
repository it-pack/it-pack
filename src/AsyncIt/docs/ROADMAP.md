# AsyncIt Roadmap

> **Status:** Proposed, review-ready.
> **Scope:** Step-by-step milestones and tasks to implement AsyncIt and its ecosystem, with quality gates baked into every phase.
> **Inputs reviewed:** `docs/BRAINSTORM.md`, `docs/IMPLEMENTATION_PLAN.md`, `README.md`, `AGENTS.md`, `docs/EXECUTION.md`, `docs/background/*` (ADR-AI-001, ADR-ITP-002), and RunIt `docs/conformance.md`, `docs/architecture/{context-propagation,lifecycle,hexagonal-architecture,publication,self-instrumentation,security}.md`, `docs/concepts/time-and-sequencing.md`, and ADRs 005 / 009 / 010 / 012 / 013 / 014 / 015 / 017 / 018 / 019 / 023 / 024.

---

## 0. Review checklist (conceptual)

Before and during implementation, the following dimensions are reviewed at every milestone. Items are conceptual, not implementation-level:

- **Architecture & boundaries** — hexagonal ports/adapters, `Polling ≠ Scheduling ≠ Semantics`, domain depends only on Contracts/Ports, AsyncIt as RunIt's `ExecutionEngineInterface` infrastructure port (ADR-ITP-002).
- **Coding discipline & modern PHP** — `final` + `readonly` value objects, enums for closed state sets, traits/composition over abstract classes, factories/builders for all instantiation, no magic literals, no `else`/`elseif`, no Yoda, PSR-4 one-type-per-file, declaration ordering.
- **Tests, conformance & mutation** — `AsyncRuntimeVerifier` (A/B/C) green across all adapters, ≥95% coverage with no cross-test leakage, Infection mutation testing, RunIt verifier reuse.
- **Security & resource safety** — fiber-safe context isolation (no bleed, deny-by-default), deterministic `StreamPollHandle`/timer cleanup, recursion-guarded publication, no global/static request state.
- **Performance, DX & SOLID/DRY** — monotonic clocks on hot paths, low complexity, capability-driven discovery, small focused interfaces, shared behavior via traits, documentation that demonstrates AsyncIt vocabulary.

---

## 1. Assumptions & scope

- **No source code exists yet** (`src/` contains no PHP files). This document reviews the *plans* and converts them into a constraint-bearing roadmap; the constraints become acceptance gates rather than a post-hoc audit.
- All code-level rules below are enforced through `php-cs-fixer` rules, `phpstan` (incl. custom rules), `deptrac`, and CI coverage/mutation gates — not by manual inspection alone.
- Where RunIt already defines a port, contract, verifier, or ADR, AsyncIt **reuses** it rather than forking (see §2).

---

## 2. Constraint → ADR manifest

| Constraint | Manifesting ADR / source |
|---|---|
| Mechanical split; AsyncIt as infrastructure port; capability negotiation | `ADR-ITP-002` |
| Observed cooperative cancellation; `ConflictingCompletionException`; handle unwiring | `ADR-AI-001` |
| Ambient context is adapter-backed; no global mutable state | RunIt `ADR-005` |
| Fiber/Swoole adapters restore/clear context in `finally`; no worker leakage | RunIt `ADR-013` |
| Publication recursion guards; failure reporter never re-enters failed publisher | RunIt `ADR-014` |
| Lifecycle matrix owned by the aggregate (`canTransitionTo()`); idempotent completion | RunIt `ADR-023`, `ADR-024` |
| Monotonic time; wall/mono separation; immutable sequences | RunIt `ADR-009`, `ADR-019` |
| Sensitive values deny-by-default | RunIt `ADR-012` |
| Every concrete class `final` and interface-backed | RunIt `ADR-015` |
| Opaque, pluggable identifier generation | RunIt `ADR-017` |
| Extensible validated strings over closed enums for user names/kinds | RunIt `ADR-018` |
| Core has no mandatory integration (RunIt optional at the port boundary) | RunIt `ADR-010` |

---

## 3. Quality-gate catalog (enforced at every milestone)

Each rule lists its enforcement mechanism and the first milestone it is required.

### Architecture & hexagonal (DepTrac)
- Domain → `{Contract, Port}` only; Application → `+ Domain`; Adapters → `+ Domain, Port`; Conformance → everything. **(M0, Deptrac)**
- No `new` of adapter/concrete types outside `Factory`/`Builder` namespaces; classes receive factories via DI. **(M1, custom PHPStan rule + review)**
- Vocabulary constants/enums live in interfaces; consumer code references the interface constant, never a literal. **(M1, PHPStan + CS)**

### Modern PHP leverage
- `readonly` for all value objects (descriptors, identities, statuses carrying data). **(M1)**
- Enums for closed internal state sets (`TaskStatus`, `CapabilityStatus`, `ViolationSeverity`, nesting mode). **(M1)**
- Fibers for task suspension (8.1+); `WeakMap` for per-Fiber context slots / non-owning metadata; `WeakReference` only where lifetime is non-critical. **(M2)**
- Lazy objects for expensive optional adapters (native poller, bridges) instantiated only when the capability descriptor selects them. **(M4)**
- Property hooks for validated value objects (e.g., `ContextToken`, `TaskId`); virtual properties for intentional projections (e.g., elapsed time). **(M1)**
- Traits harmonize with interfaces for cross-cutting behavior (lifecycle-matrix guard, throwable normalization); no stateful traits, no hidden deps. **(M1)**
- **No abstract classes.** Replace any "base" with a trait + composition; interfaces define the contract. **(M1, Deptrac/PHPStan + review)**

### Coding style (PHP-CS-Fixer + PHPStan)
- One type (class/interface/trait/enum) per file — PSR-4. **(M0)**
- No line exceeds 120 characters; multi-line parameters/arguments one per line when needed. **(M0)**
- Class declaration order: `trait use` → `constants` → `properties` (+ hooks) → `constructor` → `factory methods` → `non-static methods (public before protected/private)` → `static methods (public before protected/private)`. **(M0)**
- All referenced types/functions imported at top; no fully-qualified inline references. **(M0)**
- No `else` / `elseif` — use early return / guard clauses. **(M0, custom fixer)**
- No Yoda conditions (`if (true === $x)` is forbidden; write `if ($x === true)`). **(M0, custom fixer)**
- No magic strings/numbers — constants or enums; vocabularies live in interfaces. **(M1)**
- Every concrete class `final`; implements an interface; exceptions implement a marker interface. **(M1, per RunIt ADR-015)**

### Testing, conformance & mutation
- `AsyncRuntimeVerifier` (A: context isolation; B: monotonic precision via `MonotonicClockVerifier`; C: deterministic cleanup) green on reference runtime **and** every adapter. **(M2–M5)**
- Reused RunIt verifiers: `ExecutionEngineVerifier`, `ContextStorageVerifier`, `MonotonicClockVerifier`, `ArtifactPublisherVerifier`. **(M3, M5)**
- Coverage ≥95% per verifier/component; `@covers` strictly scoped so tests do **not** leak coverage onto unrelated code; fixtures isolated under `tests/Support/Fixture/`. **(M2+)**
- Negative-test siblings break one contract at a time and assert the expected violation id + `error` severity. **(M2+)**
- Mutation testing via Infection; every survived mutant is a defect. **(M2+)**

### Security & resource safety
- Context detached at the exact yield point, re-attached under `finally`; zero bleed proven by Assertion A. **(M2, M4)**
- Every `StreamPollHandle` and timer released on terminal transition; Assertion C fails on a single leak. **(M2)**
- Publication recursion guard; failure routed to a separate health reporter (ADR-014). **(M3)**
- No global/static request-specific state; deny-by-default secret handling delegated to RunIt context model. **(M1+)**

### Performance, DX & SOLID/DRY
- Monotonic `hrtime()` only on hot paths; no wall-clock, no eager serialization, no reflection in steady state. **(M2)**
- Bounded queues/channels/payloads; capability-selected policy over runtime probing. **(M2)**
- Small focused interfaces (ISP); domain depends on abstractions (DIP); adapters substitutable (LSP); behavior extended via new ports, not modification (OCP); one responsibility per class (SRP).
- Shared guards/mappers in traits to avoid duplication (DRY); no speculative extension points without a concrete consumer. **(M1+)**

---

## 4. Milestones

Phases map to the implementation plan. Each milestone lists goal, tasks, and acceptance gates.

---

### Phase 0 — Foundations (M0–M2 tail)

**Status:** *Nothing implemented yet. This phase establishes the guardrails so every later line is reviewable.*

**Goal:** tooling, architecture enforcement, and the conformance skeleton.

**Tasks**
- [ ] Confirm `composer.json` scripts (`analyse`, `architecture`, `test`, `coding-style`, `mutation`) exist; add missing ones.
- [ ] Fix `deptrac.yaml` adapter layers to AsyncIt's runtimes (`Adapter/(Revolt|Swoole|Swow|Native|Select)/.*`); remove the copied RunIt adapter names.
- [ ] Add `php-cs-fixer` rules for: 120-char limit, one-type-per-file, declaration order, no `else`/`elseif`, no Yoda, import ordering, `final`/`readonly` encouragement.
- [ ] Add a custom PHPStan rule forbidding `new` outside `*\Factory\*` / `*\Builder\*` namespaces.
- [ ] Create `src/Conformance/` skeleton mirroring RunIt: `Contract/PortFixtureInterface`, `Result/{ConformanceResult,ConformanceViolation,ViolationSeverity}`.
- [ ] Add `@see run-it#*` vocabulary references where AsyncIt maps RunIt terms.

**Gates:** `composer architecture` clean; CS + PHPStan baseline green; `src/Conformance/` compiles; no domain code yet.

---

### Phase 1 — Minimal contracts & ports (M3)

**Goal:** freeze the contract surface; stand up the verifier scaffold as *failing* tests; prove the rules compile.

**Tasks**
- [ ] Define vocabulary interfaces (constants/enums live here), e.g. `CapabilityVocabulary`, `TaskStatusVocabulary`, `ViolationVocabulary`.
- [ ] `TaskStatus` enum (closed matrix) with `canTransitionTo()`; `TaskStatusInterface` exposes it.
- [ ] `Task` domain entity (`final`, `readonly` where possible): `TaskId`, owning execution id, `CancellationScope` ref; `complete()` enforces the matrix (illegal → `InvalidLifecycleTransitionException`; conflicting terminal → `ConflictingCompletionException`). Idempotent same-status repeat handled by the coordinator.
- [ ] `Scheduler` interface; `PollerPort` interface + `StreamPollHandle` value object; `CancellationScopeInterface`; `CapabilityDescriptor` (`readonly`); `RuntimeDiscovery` interface.
- [ ] Reuse RunIt port *interfaces*: `MonotonicClockInterface`, `ContextStorageInterface`, `ArtifactPublisherInterface`, `SequenceAllocatorInterface`, `IdentifierGeneratorInterface`, `ExecutionEngineInterface`.
- [ ] Marker exception interface + `ConflictingCompletionException`, `InvalidLifecycleTransitionException`, `StreamPollHandleLeakException`.
- [ ] Factory interfaces: `TaskFactory`, `PollerFactory`, `CancellationScopeFactory`, `RuntimeDiscoveryFactory` — all in `*\Factory\*`, injected via DI.
- [ ] `AsyncRuntimeVerifier` suite wired: reused RunIt verifiers + Assertion A (`ContextIsolationVerifier`) and Assertion C (`DeterministicCleanupVerifier`), all returning `ConformanceResult`.
- [ ] Negative-test siblings for A and C asserting expected violation ids.

**Modern PHP notes:** `readonly` value objects; enums for state; property hooks on `ContextToken`/`TaskId`; traits for the lifecycle-matrix guard (`LifecycleMatrixTrait`) and throwable normalization (`ThrowableNormalizationTrait`) — no abstract classes.

**Gates:** all contracts compile; Deptrac clean; verifier suite runs and is **red** (no impls); no `else`/Yoda/magic literals in any file; every concrete type `final` + interface-backed; one type per file.

---

### Phase 2 — Reference runtime (M4)

**Goal:** make the verifier suite **green** on a deterministic, `SelectPoller`-backed runtime — the semantic contract frozen in code.

**Tasks**
- [ ] `FiberTaskScheduler` (`final`, implements `Scheduler`): receives `TaskFactory`, `PollerPort`, `MonotonicClockInterface`, `ContextStorageInterface`, `CancellationScopeFactory` via DI — no `new` inside.
- [ ] Reactor loop: drain ready tasks → fire expired timers (monotonic) → `poller->wait(nearestDeadline)` → repeat until idle.
- [ ] Around `Fiber::suspend()`: `ContextStorageInterface::detach()` before; `reattach(token)` under `finally` after resume (Assertion A).
- [ ] On terminal transition: drain the task's `StreamPollHandle`s and timers (Assertion C).
- [ ] On throw: publish via recursion-guarded `ArtifactPublisherInterface`, release handles/timers, rethrow the **exact** original `Throwable`.
- [ ] `SelectPoller` (Adapter/Common) implements `PollerPort` via `stream_select()`; documented weaker traits; never pretends to be `Io\Poll`.
- [ ] `MemoryContextStorage` implements RunIt `ContextStorageInterface` (LIFO `ContextToken`, `WeakMap<Fiber,Context>` slot).
- [ ] `NullArtifactPublisher` implements RunIt `ArtifactPublisherInterface` (fail-open no-op).
- [ ] `DeterministicMonotonicClock` fixture for duration assertions without sleeping.
- [ ] `RuntimeDiscoveryFactory` selects the reference assembly from a `CapabilityDescriptor`.

**Performance/DX:** monotonic only on hot path; bounded pending-await registry; capability-driven discovery so consumers never name a runtime.

**Gates:** `composer test` + `architecture` + `analyse` green; Assertion A/C and reachable RunIt verifiers (`MonotonicClockVerifier`, `ContextStorageVerifier`, `ArtifactPublisherVerifier`) green; ≥95% coverage, no leakage; Infection clean on the scheduler/registry.

---

### Phase 3 — RunIt integration (M5)

**Goal:** the formal gate — `AsyncRuntime` passes RunIt's `ExecutionEngineVerifier`.

**Tasks**
- [ ] Swap `MemoryContextStorage`/`NullArtifactPublisher` for RunIt first-party adapters (`FailSafeArtifactPublisher`, `onPublicationFailure` health callback, recursion guard — ADR-014).
- [ ] Use RunIt `SystemMonotonicClock`.
- [ ] `AsyncRuntime` SDK class implements `ExecutionEngineInterface`; `AsyncRuntime::discover()` returns it with the negotiated descriptor.
- [ ] Validate detach/reattach under real per-fiber/per-coroutine context storage.

**Gates:** `ExecutionEngineVerifier` green; all reused RunIt verifiers green through real RunIt adapters; Assertion A/C still green.

---

### Phase 4 — Core runtime adapters (M6–M8)

**Goal:** Revolt, Swoole, Swow each pass the *same* suite; scheduling semantics unchanged.

**Tasks (per adapter)**
- [ ] `PollerPort` implementation over the runtime's watcher/loop.
- [ ] Context-storage adapter: per-coroutine (Swoole/Swow) or per-Fiber (Revolt) slot; cleared/restored in `finally` (ADR-013).
- [ ] `RuntimeDiscoveryFactory` branch (via `CapabilityDescriptor`, never `extension_loaded`) selecting native channels where `Supported`.
- [ ] `TaskChannel` backed by native channels when available; emulated otherwise under the same interface.
- [ ] Lazy-loaded optional bridges (e.g., telemetry) via lazy objects.

**Gates (per adapter):** full `AsyncRuntimeVerifier` green; Assertion A (isolation) and C (no leaked watchers) green; one leaked descriptor or one bleed = failure; Deptrac confirms adapter→Domain/Port only.

---

### Phase 5 — Native poller & interop (M9–M10)

**Goal:** `Io\Poll` slides into the same `PollerPort` slot; userland loops can host tasks.

**Tasks**
- [ ] `NativePoller` (Adapter/External) over PHP 8.6 `Io\Poll`; edge-triggered; same `PollerPort` contract.
- [ ] ReactPHP/Amp interop `PollerPort` bridge; documented as interop, not unification.
- [ ] `PollerFactory` selects `NativePoller` vs `SelectPoller` from capability/PHP version.

**Gates:** `NativePoller` passes poller contract + Assertion C; interop passes A/C under a ReactPHP loop; all prior gates remain green.

---

## 5. Status updates (major milestones)

- **After Phase 0:** Tooling and architecture guards are in place; the package is buildable and enforceable, but no behavior exists yet. Next: contract freeze (M3). Blocker: none.
- **After Phase 2:** The reference runtime proves AsyncIt's semantics and turns the verifier suite green. Next: RunIt integration (M5). Blocker: none; RunIt port *interfaces* are consumable.
- **After Phase 4:** Every major runtime adapter conforms to the same `AsyncRuntimeVerifier`. Next: native `Io\Poll` and interop. Blocker: PHP 8.6 `Io\Poll` availability for `NativePoller` (use the OCI dev lab per the poll-api brainstorm).

---

## 6. Convention patterns (illustrative, one type per file)

These sketches manifest the rules; each would live in its own file.

```php
// src/Contract/Port/CapabilityVocabulary.php
declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

interface CapabilityVocabulary
{
    public const STATUS_SUPPORTED = 'supported';
    public const STATUS_EMULATED = 'emulated';
    public const STATUS_ADAPTER_ONLY = 'adapter_only';
    public const STATUS_UNAVAILABLE = 'unavailable';
}
```

```php
// src/Domain/CapabilityStatus.php
declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\Port\CapabilityVocabulary;

enum CapabilityStatus: string
{
    case Supported = CapabilityVocabulary::STATUS_SUPPORTED;
    case Emulated = CapabilityVocabulary::STATUS_EMULATED;
    case AdapterOnly = CapabilityVocabulary::STATUS_ADAPTER_ONLY;
    case Unavailable = CapabilityVocabulary::STATUS_UNAVAILABLE;
}
```

```php
// src/Application/Factory/TaskFactory.php
declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Task;
use ItPack\AsyncIt\Domain\TaskId;

final class TaskFactory
{
    public function create(callable $operation): TaskInterface
    {
        return new Task(
            TaskId::generate(),
            new Fiber($operation),
        );
    }
}
```

```php
// src/Application/Scheduler/FiberTaskScheduler.php (excerpt — declaration order, no else, DI factories)
declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Contract\Port\PollerPort;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

final class FiberTaskScheduler implements SchedulerInterface
{
    public function __construct(
        private readonly TaskFactory $taskFactory,
        private readonly PollerPort $poller,
    ) {
    }

    public function spawn(callable $operation): TaskInterface
    {
        return $this->taskFactory->create($operation);
    }

    private function resumeTask(TaskInterface $task): void
    {
        if ($task->isTerminal()) {
            return;
        }

        $task->resume();
    }
}
```

Note the absence of `else`, the early-return guard, `final` + interface, injected factory, and the declaration order (constants/properties → constructor → public → private).

---

## 7. Definition of done (package-level)

- `composer test`, `composer analyse`, `composer architecture`, `composer coding-style`, `composer mutation` green on PHP 8.4+.
- `AsyncRuntimeVerifier` green across reference runtime **and** every adapter (A/B/C).
- `AsyncRuntime` passes RunIt's `ExecutionEngineVerifier`.
- Coverage ≥95%, no cross-test leakage, Infection clean.
- Every concrete class `final`, interface-backed, one type per file; no abstract classes; no `else`/Yoda/magic literals; vocabularies in interfaces; factories/builder-only instantiation via DI.
- `examples/conformance/third-party-adapter.php` runs unmodified for each shipped adapter.
- Docs (`README.md`, `AGENTS.md`, `BRAINSTORM.md`, `IMPLEMENTATION_PLAN.md`, this roadmap) reflect the built reality and reference `run-it#*` vocabulary.

---

## 8. Final status

The roadmap converts the brainstorm and implementation plan into constraint-bearing milestones: each phase carries explicit architecture, security, performance, DX, and test/conformance gates, and the §3 catalog enforces the modern-PHP, factory-only, no-abstract-class, no-else/Yoda/magic-literal, and PSR-4 rules via `php-cs-fixer`, PHPStan, Deptrac, and mutation testing. Next step is Phase 0 (tooling + Deptrac fix + conformance skeleton), which has no blockers.
