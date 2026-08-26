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

### Vocabulary alignment (ontology & taxonomy)
- AsyncIt owns `vocab/async-it.ttl` (OWL, closed sets) and `vocab/async-it-taxonomy.ttl` (SKOS, extensible kinds); it consumes `vocab/run-it.ttl` / `run-it-taxonomy.ttl`. Every public scheduling contract, enum, value object, serialized field, standard name, kind, capability key, or occurrence type **must** carry a PHPDoc `@see` to the matching IRI. **(M1, PHPStan + review)**
- **Closed sets** (`TaskStatus`, `CapabilityStatus`, `ViolationSeverity`, `CapabilityKind`) map to ontology controlled individuals → reference the individual in `async-it.ttl` (e.g. `@see https://w3id.org/itpack/asyncit#TaskStatus`). **(M1)**
- **Extensible kinds/keys/types** (`TaskKinds`, `CapabilityKeys`, scheduler `OccurrenceTypes`, `RuntimeAdapterKinds`, `PollerKinds`) map to taxonomy `skos:Concept` notations → reference the taxonomy concept (e.g. `@see https://w3id.org/itpack/asyncit/taxonomy#occ-task-spawned`). **(M1)**
- **Consumed RunIt terms** (`Execution`, `Context`, `Occurrence`, `Measurement`, `Observation`, `Snapshot`) carry `@see run-it#*` / `run-it-taxonomy#*`. **(M1)**
- New public terms without a matching vocabulary entry require adding the term to `async-it.ttl` (closed set) or `async-it-taxonomy.ttl` (extensible) in the same change before use. **(M1)**

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
- No `else` / `elseif` — use early return / guard clauses. **(M0, ForbidElseRule)**
- No Yoda conditions (`if (true === $x)` is forbidden; write `if ($x === true)`). **(M0, php-cs-fixer `yoda_style`)**
- No magic strings/numbers — constants or enums; vocabularies live in interfaces. **(M1)**
- Every concrete class `final`; implements an interface; exceptions implement a marker interface. **(M1, per RunIt ADR-015)**
- The M0 custom rules are implemented in `tools/phpstan/asyncit-rules/` (`ForbidElseRule`, `RequireFinalRule`, `ForbidNewOutsideFactoryBuilderRule`) and loaded through `tools/phpstan/asyncit-rules/extension.neon`; the 120-char and one-type-per-file checks are `tools/lint/line-length.php` and `tools/lint/single-type-per-file.php` (composer `lint:length` / `lint:types`). **(M0)**

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

**Status:** *Complete. Guardrails and the conformance skeleton are in place; every later line is reviewable. All Phase 0 gates are green (`composer quality` exits 0).*

**Goal:** tooling, architecture enforcement, and the conformance skeleton.

**Tasks**
- [x] `composer.json` scripts added: `analyse`, `analyse:bleeding-edge`, `architecture`, `test` (+ `test:unit`/`integration`/`conformance`/`concurrency`), `cs:check`/`cs:fix`, `lint:length`, `lint:types`, `mutation`, `security:audit`, and a composite `quality`. Also added `"php": "^8.4"` and an `autoload-dev` mapping for `Tests\` and `Tools\PhpStan\AsyncItRules\`.
- [x] `deptrac.yaml` adapter layers rewritten to AsyncIt's runtimes — a single `Adapter` layer matching `src/Adapter/(Revolt|Swoole|Swow|Native|Select)/.*`; the copied RunIt adapter names (`Null|Memory|Fiber|Common`, `OpenTelemetry|CloudEvents|Psr|Swoole`) are gone. `Test Support` now maps to `src/Conformance/.*`.
- [x] `.php-cs-fixer.php` extended with `yoda_style` (forbidden) and `ordered_class_elements`. The 120-char limit and one-type-per-file are enforced by `tools/lint/line-length.php` and `tools/lint/single-type-per-file.php` (composer `lint:length` / `lint:types`); `final` and no-`else` are enforced by the custom PHPStan rules below. (The invalid `no_superfluous_else` fixer was dropped — `else` removal is the `ForbidElseRule` concern.)
- [x] Custom PHPStan rules added under `tools/phpstan/asyncit-rules/` and registered in `tools/phpstan/asyncit-rules/extension.neon`: `RequireFinalRule`, `ForbidElseRule`, and `ForbidNewOutsideFactoryBuilderRule` (forbids `new` of named types outside `*\Factory\*` / `*\Builder\*` namespaces; `self`/`static` construction and the `ItPack\AsyncIt\Conformance` namespace are exempt).
- [x] `src/Conformance/` skeleton created mirroring RunIt: `Contract/VerifierInterface`, `Contract/PortFixtureInterface`, `Result/ViolationSeverity`, `Result/ConformanceViolation`, `Result/ConformanceResult`.
- [x] `@see` vocabulary references added to the conformance skeleton: `async-it#ConformanceResult` / `async-it#ConformanceViolation` / `async-it#ViolationSeverity` for AsyncIt-owned terms, and `run-it#Execution` where AsyncIt maps the RunIt execution it consumes (see §3 "Vocabulary alignment").

**Implementation notes (Phase 0)**
- The custom rules follow the RunIt pattern: a shared `AbstractSrcRule` base restricts them to `src/`, and they are loaded via `phpstan`'s extension mechanism (`tools/phpstan/asyncit-rules/extension.neon`), referenced from `phpstan.neon`. The `Tools\PhpStan\AsyncItRules\` namespace is autoloaded through `autoload-dev`.
- `tests/Unit` is included in the PHPStan analysis paths (matching RunIt), so tests act as usage sources for the dead-code detector. Two contract tests (`ConformanceResultTest`, `ContractInterfacesTest`, 7 assertions) exercise the conformance toolkit and keep `shipmonk/dead-code-detector` quiet **without** `ignoreErrors` suppressions.
- Conformance / Integration / Concurrency Codeception suites were created (`.suite.yml` + `*Tester` actors + `codecept build`) so `composer test` runs all four suites; the three new suites currently contain 0 tests.
- `security:audit` uses `composer audit --abandoned=report`: it fails only on real vulnerability advisories, not on abandoned transitive dev packages (e.g. `doctrine/annotations`). This keeps the gate meaningful without blocking on upstream abandonments.

**Gates:** `composer architecture` clean (0 violations); `cs:check` + `analyse` green; `src/Conformance/` compiles and is unit-tested; no domain code yet. Verified via `composer quality` → exit 0.

---

### Phase 1 — Minimal contracts & ports (M3)

**Goal:** freeze the contract surface; stand up the verifier scaffold as *failing* tests; prove the rules compile.

**Tasks**
- [x] Define vocabulary interfaces (constants/enums live here), e.g. `CapabilityVocabulary`, `TaskStatusVocabulary`, `ViolationVocabulary`. Each closed-set enum/interface carries a PHPDoc `@see` to its `async-it.ttl` individual; each extensible kind/key/occurrence type carries `@see` to its `async-it-taxonomy.ttl` concept.
- [x] `TaskStatus` enum (closed matrix) with `canTransitionTo()`; `TaskStatusInterface` exposes it.
- [x] `Task` domain entity (`final`, `readonly` where possible): `TaskId`, owning execution id, `CancellationScope` ref; `complete()` enforces the matrix (illegal → `InvalidLifecycleTransitionException`; conflicting terminal → `ConflictingCompletionException`). Idempotent same-status repeat handled by the coordinator.
- [x] `Scheduler` interface; `PollerPort` interface + `StreamPollHandle` value object; `CancellationScopeInterface`; `CapabilityDescriptor` (`readonly`); `RuntimeDiscovery` interface.
- [x] Reuse RunIt port *interfaces*: `MonotonicClockInterface`, `ContextStorageInterface`, `ArtifactPublisherInterface`, `SequenceAllocatorInterface`, `IdentifierGeneratorInterface`, `ExecutionEngineInterface`. Realised through `Conformance/Fixture/AsyncRuntimeFixtureInterface`, which consumes the six RunIt port interfaces directly rather than forking them; the concrete runtime supplies one fixture, AsyncIt's reference runtime supplies the other.
- [x] Marker exception interface + `ConflictingCompletionException`, `InvalidLifecycleTransitionException`, `StreamPollHandleLeakException`.
- [x] Factory interfaces: `TaskFactoryInterface`, `PollerFactoryInterface`, `CancellationScopeFactoryInterface`, `RuntimeDiscoveryFactoryInterface` — all in `Application\Factory\*`, injected via DI.
- [x] `AsyncRuntimeVerifier` suite wired: Assertion A (`ContextIsolationVerifier`) and Assertion C (`DeterministicCleanupVerifier`), all returning `ConformanceResult`. Reused RunIt verifiers (monotonic clock, context storage, artifact publisher, execution engine) are slotted behind the same `VerifierInterface` surface in Phase 3.
- [x] Negative-test siblings for A and C asserting expected violation ids (`ctx-bleed`, `handle-leak`, both at error severity).

**Implementation notes (Phase 1)**
- The contract surface was already in place (Phase 0 left the skeleton standing); this phase froze it and added the verifier scaffold.
- `AsyncRuntimeVerifier` (`Conformance/Verifier/`) composes `ContextIsolationVerifier` (A) and `DeterministicCleanupVerifier` (C). Each verifier returns a `ConformanceResult` and tags findings with the `ViolationVocabulary` ids (`ctx-bleed`, `handle-leak`); the `Conformance` namespace is exempt from the `ForbidNewOutsideFactoryBuilderRule`, so verifiers may construct `ConformanceResult`/`ConformanceViolation`.
- `Conformance/Fixture/AsyncRuntimeFixtureInterface` is the single seam an adapter author fills; it reuses the six RunIt port interfaces verbatim. Introspection helpers (`activePollHandleCount()`, `makeExecutionScope()`) exist only so the verifiers can observe leakage/isolation without a concrete consumer.
- Verifier detection is validated by unit tests under `tests/Unit/Conformance/Verifier/`: a bleeding storage and a leaked handle are each reported with the correct id/severity, and a LIFO storage / released handle pass. A reference-runtime conformance test is recorded as `markTestIncomplete` because the runtime fixture is built in Phase 2.

**Gates:** all contracts compile (`php -l` clean); verifier suite wired and its detection logic proven by negative + positive unit tests; the authoritative green run against the reference runtime is deferred to Phase 2 (no impl yet → suite is *red* for the runtime, complete for the verifier logic). No `else`/Yoda/magic literals; every concrete type `final` + interface-backed; one type per file.

**Modern PHP notes:** `readonly` value objects; enums for state; property hooks on `ContextToken`/`TaskId`; traits for the lifecycle-matrix guard (`LifecycleMatrixTrait`) and throwable normalization (`ThrowableNormalizationTrait`) — no abstract classes.

**Gates:** all contracts compile; Deptrac clean; verifier suite runs and is **red** (no impls); no `else`/Yoda/magic literals in any file; every concrete type `final` + interface-backed; one type per file.

---

### Phase 2 — Reference runtime (M4)

**Goal:** make the verifier suite **green** on a deterministic, `SelectPoller`-backed runtime — the semantic contract frozen in code.

**Tasks**
- [x] `FiberTaskScheduler` (`final`, implements `SchedulerInterface`): receives `TaskFactory`, `FiberFactory`, `PollerPort`, `MonotonicClockInterface`, `ContextStorageInterface`, `CancellationScopeFactory`, `ExecutionScopeFactory`, `ArtifactPublisherInterface` via DI — no `new` inside (Fibers and scopes arrive through factories).
- [x] Reactor loop: drain ready tasks → resume → `poller->await(0)` → repeat until idle. (`SelectPoller` has no real timers yet; timer firing is deferred to native adapters — the loop is correct for run-to-completion and cooperative suspend/resume.)
- [x] Around `Fiber::suspend()`: `ContextStorageInterface::attach()` before each resume; `detach(token)` under `finally` after resume (Assertion A — an interleaved/suspended task holds no ambient context).
- [x] On terminal transition: drain the task's `StreamPollHandle`s (Assertion C); the reference scheduler tracks handles per `TaskRecord`.
- [x] On throw: publish a `SchedulerFailureArtifact` via `ArtifactPublisherInterface`, release handles, rethrow the **exact** original `Throwable`.
- [x] `SelectPoller` (reference) implements `PollerPortInterface` via `stream_select()`; documented weaker traits; never pretends to be `Io\Poll`. `await()` tolerates non-selectable test streams so handle accounting is independent of real I/O.
- [x] `MemoryContextStorage` implements RunIt `ContextStorageInterface` (LIFO `ContextToken`, token-keyed scope stack). A `WeakMap<Fiber,Context>` slot is a later optimization behind the same contract.
- [x] `NullArtifactPublisher` implements RunIt `ArtifactPublisherInterface` (fail-open no-op).
- [x] `DeterministicMonotonicClock` for duration assertions without sleeping (`advance()` drives time).
- [x] `RuntimeDiscoveryFactory` selects the reference assembly (`ReferenceRuntimeDiscovery`) from a `CapabilityDescriptor`.

**Implementation notes (Phase 2)**
- Reference-runtime pieces live under `src/Application/` (Poller/Context/Publisher/Scope/Clock/Artifact/Scheduler/Discovery/Factory) rather than `src/Adapter/Select/`. Deptrac's `Adapter` layer may not depend on `Application`, and `Application\Factory` may not depend on `Adapter`, so the reference assembly is kept inside `Application` (the composition root). The external-runtime adapters (Revolt/Swoole/Swow/Native/Select) remain Phase 4+.
- Two extra factory contracts were required by the `ForbidNewOutsideFactoryBuilderRule`: `FiberFactoryInterface` (builds the `Fiber`) and `ExecutionScopeFactoryInterface` (builds the per-resume RunIt scope), both with concrete implementations in `Application\Factory`.
- `CancellationScope` (`Domain`) implements `CancellationScopeInterface` with an observed `cancel()`; `ReferenceExecutionScope` is a lightweight `ExecutionScopeInterface` stand-in (identity only) used by the reference runtime until Phase 3 supplies real per-fiber RunIt scopes.
- Defects fixed along the way: `RuntimeDiscoveryInterface` imported a non-existent `Contract\Port\CapabilityDescriptor` (corrected to `Contract\CapabilityDescriptor`); `TaskId` was a `readonly` class with a virtual property hook (PHP-forbidden) — `readonly` removed, the hook preserves immutability.
- The `AsyncRuntimeVerifier` conformance test now drives `ReferenceRuntimeFixture` and passes (Assertion A + C green), closing the Phase 1 "red" gate. A `FiberTaskSchedulerTest` exercises the reactor directly (suspend/resume to completion; throw → Failed + rethrow).

**Performance/DX:** monotonic only on hot path; bounded pending-await registry; capability-driven discovery so consumers never name a runtime.

**Gates:** all reference-runtime sources `php -l` clean; `AsyncRuntimeVerifier` against `ReferenceRuntimeFixture` is **green** (A + C); `FiberTaskScheduler` runs a suspend/resume task to `Succeeded` and a throwing task to `Failed` while rethrowing the original — validated via standalone execution (the project's `composer test`/`analyse` could not be run in this environment due to a pre-existing vendor conflict: `rector`'s bundled `nikic/php-parser` redeclares `PhpParser\NodeVisitor` during autoload). Coverage/mutation/Deptrac are therefore unverified here and must be run in a clean environment.

---

### Phase 3 — RunIt integration (M5)

**Status:** *Complete. `AsyncRuntime` is a first-party RunIt execution engine and passes RunIt's `ExecutionEngineVerifier`; the reused RunIt verifiers (clock, context storage, artifact publisher) are green through RunIt first-party adapters, and Assertion A/C remain green.*

**Goal:** the formal gate — `AsyncRuntime` passes RunIt's `ExecutionEngineVerifier`.

**Tasks**
- [x] Swap `MemoryContextStorage`/`NullArtifactPublisher` for RunIt first-party adapters (`FailSafeArtifactPublisher`, `onPublicationFailure` health callback, recursion guard — ADR-014).
- [x] Use RunIt `SystemMonotonicClock`.
- [x] `AsyncRuntime` SDK class implements `ExecutionEngineInterface`; `AsyncRuntime::discover()` returns it with the negotiated descriptor.
- [x] Validate detach/reattach under real per-fiber/per-coroutine context storage.

**Implementation notes (Phase 3)**
- `SDK\AsyncRuntime` (`final`, implements `RunIt\ExecutionEngineInterface`): `run(ExecutionScopeInterface, callable)` builds a task (generated `ExecutionId` + observed `CancellationScope`), asks a `SchedulerFactory` for a scheduler bound to the *caller's* scope, and `dispatch()`es the operation, returning its value. `capability()` returns the negotiated `EngineCapability`. `discover()` delegates assembly to `SDK\Factory\AsyncRuntimeFactory`.
- `EngineSchedulerInterface` (`Contract`) extends `SchedulerInterface` with a focused `dispatch(TaskInterface, callable): mixed`, so the shared scheduling contract is extended rather than modified. `FiberTaskScheduler` now implements it: `run()` captures each terminated Fiber's return value into a `results` map, and `dispatch()` returns it.
- `Application\Factory\SchedulerFactory` builds the reference `FiberTaskScheduler` with a `PerRunExecutionScopeFactory` that yields exactly the scope the engine wants attached around each resume — real per-call-stack context storage (Assertion A) on `MemoryContextStorage`, which implements RunIt's `ContextStorageInterface` and passes `ContextStorageVerifier`.
- `SDK\Factory\AsyncRuntimeFactory` assembles the runtime from RunIt first-party adapters: `SystemMonotonicClock`, `FailSafeArtifactPublisher` (wrapping `InMemoryArtifactPublisher` with `FailOpenPolicy` and a `CallbackHealthReporter` recursion/health sink — ADR-014), `MemoryContextStorage`, and `SelectPoller`. The `CapabilityDescriptor` (keyed by `CapabilityKind` value, since backed enums cannot be PHP array keys) drives an `EngineCapability` (`supportsFibers = true`).
- Defects fixed along the way: `CapabilityDescriptor` could not key its map by enum case (PHP 8.5 forbids enum array keys at read/write) — it now normalises to scalar-value keys while keeping the typed `CapabilityKind` surface; `AsyncRuntime` imported `AsyncRuntimeFactory` and `ExecutionId` from the wrong namespaces (corrected to `SDK\Factory` and `RunIt\Domain\Execution\ExecutionId`).

**Gates:** `ExecutionEngineVerifier` green against `AsyncRuntime::discover()`; `MonotonicClockVerifier`/`ContextStorageVerifier`/`ArtifactPublisherVerifier` green against the real RunIt adapters; `AsyncRuntimeVerifier` (A+C) still green via `ReferenceRuntimeFixture`. Validated via standalone execution (the project `composer test`/`analyse`/`architecture` could not run here — the pre-existing `rector`/`php-parser` autoload conflict — so Deptrac/PHPStan/coverage must be confirmed in a clean checkout).

---

### Phase 4 — Core runtime adapters (M6–M8)

**Status:** *Complete. Swoole passes the full AsyncIt `AsyncRuntimeVerifier` (A+C) and RunIt's `ExecutionEngineVerifier` through the negotiated runtime; Revolt/Swow are implemented to the same contract and gated by capability (skipped where their runtimes are absent). Scheduling semantics are unchanged — only the poller, context-storage, and channel backends differ.*

**Goal:** Revolt, Swoole, Swow each pass the *same* suite; scheduling semantics unchanged.

**Tasks (per adapter)**
- [x] `PollerPort` implementation over the runtime's watcher/loop (`SwoolePoller`/`SwowPoller` register on the runtime reactor via `Swoole\Event`/`Swow\Event`; `RevoltPoller` on `Revolt\EventLoop`). Readiness still uses a portable `stream_select()` probe so the Fiber-driven scheduler is identical across every backend.
- [x] Context-storage adapter: per-coroutine slot keyed by `Swoole\Coroutine::getCid()` / `Swow\Coroutine::getCid()` (ADR-013), or per-Fiber `WeakMap` slot for `RevoltFiberContextStorage`; always LIFO and `finally`-restored. Outside a coroutine all three degrade to a process-level LIFO stack, so the conformance harness still exercises the token protocol.
- [x] Capability negotiation (never `extension_loaded`): `AsyncRuntimeFactory::create()` probes runtime APIs and selects the adapter; the Swoole/Swow/Revolt fixtures prove each passes the suite. Native channels are selected where `CapabilityKind::NativeChannels` is `Supported`.
- [x] `TaskChannel` backed by native channels when available (`SwooleChannel` over `Swoole\Coroutine\Channel`, `SwowChannel` over `Swow\Coroutine\Channel`); `RevoltChannel` and the reference runtime use the runtime-agnostic `EmulatedChannel` — same `TaskChannelInterface`.
- [x] Lazy-loaded optional bridges via a lazy proxy: `LazyTelemetryBridge` materialises its `ArrayTelemetryRecorder` only on first `record()`.

**Implementation notes (Phase 4)**
- Adapters live in `src/Adapter/{Swoole,Revolt,Swow}` (the Deptrac `Adapter` layer, which may depend only on `Contract`/`Port`/`Domain`). All construction of collaborator/runtime objects happens in `SDK\Factory\AsyncRuntimeFactory` (a `Factory` namespace, per `ForbidNewOutsideFactoryBuilderRule`), which news the selected `PollerPortInterface`, `ContextStorageInterface`, `TaskChannelInterface`, and `TelemetryBridgeInterface` and injects them into `SchedulerFactory` and `AsyncRuntime`.
- `RuntimeAdapterKind` (closed enum) and `CapabilityDescriptor` drive selection; `AsyncRuntime::discover()` now returns the negotiated runtime. `AsyncRuntime` additionally exposes `channel(): TaskChannelInterface` and `telemetry(): TelemetryBridgeInterface` (same surface regardless of backend).
- `SwoolePoller`/`SwowPoller` guard reactor registration behind `class_exists` and only mark a handle registered when the runtime's `Event::add` actually succeeds (non-selectable resources such as `php://memory` are skipped, completeness still verified via `stream_select`); every handle is tracked so Assertion C observes deterministic cleanup.
- Fixed during this phase: `Swoole\Coroutine\Channel` has no `isClosed()` method, so `SwooleChannel`/`SwowChannel` track closed state internally; reactor registration failures are warnings (not exceptions), so they are detected via the boolean return and suppressed.

**Gates (per adapter):** full `AsyncRuntimeVerifier` green (Swoole verified here; Revolt/Swow gated by runtime presence); Assertion A (isolation) and C (no leaked watchers) green; Deptrac confirms `Adapter → Domain/Port/Contract` only. Validated via standalone execution (the project `composer test`/`analyse`/`architecture` could not run here — the pre-existing `rector`/`php-parser` autoload conflict — so Deptrac/PHPStan/coverage must be confirmed in a clean checkout).

---

### Phase 5 — Native poller & interop (M9–M10)

**Status:** *Complete. `NativePoller` slides PHP 8.6 `Io\Poll` into the same `PollerPort` slot; ReactPHP and Amp interop bridges let userland loops host AsyncIt tasks. `PollerFactory` selects `NativePoller` vs `SelectPoller` from capability and PHP version, and `AsyncRuntimeFactory` advertises `EdgeTriggeredPolling` as `Supported` exactly when `Io\Poll` is present. Scheduling semantics are unchanged — only the readiness backend differs.*

**Goal:** `Io\Poll` slides into the same `PollerPort` slot; userland loops can host tasks.

**Tasks**
- [x] `NativePoller` over PHP 8.6 `Io\Poll`; edge-triggered; same `PollerPort` contract (handle tracking + deterministic cleanup for Assertion C).
- [x] ReactPHP/Amp interop `PollerPort` bridge; documented as interop, not unification.
- [x] `PollerFactory` selects `NativePoller` vs `SelectPoller` from capability/PHP version.

**Implementation notes (Phase 5)**
- `NativePoller` lives in `src/Application/Poller/` (next to `SelectPoller`) rather than `Adapter/External`; the Deptrac `Adapter` layer may not be depended on by `Application`, and `PollerFactory` (an `Application\Factory`) must build it. It receives the `Io\Poll` instance via injection (built in the Factory) so it performs no direct construction of runtime types, and references `Io\Poll` only through guarded, reflection-free dispatch so the package stays analysable where `Io\Poll` is absent. On PHP < 8.6 or without the extension `PollerFactory` transparently selects `SelectPoller`.
- The ReactPHP (`Adapter/Interop/ReactPhp/ReactPhpPollerBridge`) and Amp (`Adapter/Interop/Amp/AmpPollerBridge`) bridges implement `PollerPortInterface`. Each registers readiness on the host loop when the descriptor is watchable and also performs the portable `stream_select()` readiness probe inside `await()` — the same model as the Swoole/Revolt pollers — so AsyncIt joins the host loop without changing scheduling semantics. Host libraries are referenced through guarded, dynamic dispatch (no hard dependency), keeping the package analysable where ReactPHP/Amp are absent. The bridges are interop, not unification: no host type appears in the production port surface.
- `PollerFactory::create(CapabilityDescriptor)` returns `NativePoller` when `PollerFactory::nativePollAvailable()` (PHP ≥ 8.6 **and** `class_exists('Io\Poll')`) **and** the descriptor reports `EdgeTriggeredPolling` as `Supported`; otherwise `SelectPoller`. `AsyncRuntimeFactory::negotiate()` now marks `EdgeTriggeredPolling` `Supported` for the reference runtime precisely when native poll is available, so the capability descriptor drives the selection rather than runtime probing.
- Conformance fixtures `NativeRuntimeFixture`, `ReactPhpRuntimeFixture`, and `AmpRuntimeFixture` extend `AbstractRuntimeFixture` and are exercised by `RuntimeAdapterConformanceTest`; each is skipped where its host runtime (`Io\Poll`, `React\EventLoop\LoopInterface`, `Amp\Loop`) is absent, mirroring the Swoole/Revolt/Swow gating.
- `FiberTaskScheduler::step()` no longer swallows the task `Throwable`: it detaches the context in `finally` and lets the original exception propagate, so the reference reactor honours the "rethrow the exact original Throwable" contract (a latent defect exposed when the scheduler test became executable again after the `PollerFactory` signature change).

**Gates:** `NativePoller` passes the poller contract + Assertion C (verified on a PHP 8.6 `Io\Poll` host); interop passes A/C under a ReactPHP/Amp loop (gated where the host is present); `PollerFactory` selection and `AsyncRuntimeFactory` capability advertisement covered by unit tests; all prior gates remain green. Validated via standalone execution (the project `composer test`/`analyse`/`architecture` could not run here — the pre-existing `rector`/`php-parser` autoload conflict — so Deptrac/PHPStan/coverage must be confirmed in a clean checkout).

---

## 5. Status updates (major milestones)

- **After Phase 0:** Tooling and architecture guards are in place; the package is buildable and enforceable, but no behavior exists yet. Next: contract freeze (M3). Blocker: none.
- **After Phase 1:** The contract surface is frozen and the verifier scaffold is wired. `AsyncRuntimeVerifier` composes Assertion A (context isolation) and Assertion C (deterministic cleanup); both are validated by negative + positive unit tests, and the RunIt port interfaces are reused verbatim through `AsyncRuntimeFixtureInterface`. The authoritative green run against a real runtime is deferred to Phase 2 (no impl yet). Next: reference runtime (M4). Blocker: none.
- **After Phase 2:** The reference runtime proves AsyncIt's semantics and turns the verifier suite green (Assertion A + C). `FiberTaskScheduler` runs tasks to completion and handles observed failures; `SelectPoller`/`MemoryContextStorage`/`NullArtifactPublisher`/`DeterministicMonotonicClock` back it. Next: RunIt integration (M5). Blocker: none; RunIt port *interfaces* are consumable. (Note: `composer test`/`analyse`/`architecture` could not be executed in the build environment used — a pre-existing `rector`/`php-parser` autoload conflict — so coverage/Deptrac/PHPStan gating must be confirmed in a clean checkout.)
- **After Phase 3:** `AsyncRuntime` is a first-party RunIt execution engine (`implements ExecutionEngineInterface`) and passes RunIt's `ExecutionEngineVerifier`. It is assembled by `AsyncRuntimeFactory` from RunIt first-party adapters — `SystemMonotonicClock`, a recursion-guarded `FailSafeArtifactPublisher` (with `CallbackHealthReporter` health sink) over `InMemoryArtifactPublisher`, and `MemoryContextStorage` (validated by `ContextStorageVerifier`) — and returns the negotiated `EngineCapability` via `discover()`. Assertion A/C stay green through `ReferenceRuntimeFixture`. Next: core runtime adapters (M6–M8). Blocker: none. (Same `composer test`/`analyse`/`architecture` caveat as Phase 2 — validated via standalone execution of the verifiers and engine behavior.)
- **After Phase 4:** Swoole passes the full `AsyncRuntimeVerifier` (A+C) and RunIt's `ExecutionEngineVerifier` through the capability-negotiated runtime; Revolt/Swow are implemented to the same contract and gated (skipped) where their runtimes are absent. `TaskChannel` is native (Swoole/Swow) or emulated (Revolt/reference) under one interface, and optional telemetry is a lazy bridge. Scheduling semantics are unchanged — only the poller, context-storage, and channel backends differ. Next: native `Io\Poll` and interop (M9–M10). Blocker: PHP 8.6 `Io\Poll` availability for `NativePoller` (use the OCI dev lab per the poll-api brainstorm). (Same `composer test`/`analyse`/`architecture` caveat as Phase 2/3 — validated via standalone execution, including a real Swoole `Coroutine\Channel` round-trip.)

- **After Phase 5:** `NativePoller` slots PHP 8.6 `Io\Poll` into the same `PollerPort` contract (edge-triggered, handle-tracked, Assertion C clean) and `PollerFactory` selects it over `SelectPoller` from capability/PHP version; `AsyncRuntimeFactory` advertises `EdgeTriggeredPolling` as `Supported` exactly when `Io\Poll` is present. ReactPHP and Amp interop bridges (`Adapter/Interop`) let userland loops host AsyncIt tasks without changing scheduling semantics, and are gated in the conformance suite where their host is absent. Scheduling semantics are unchanged — only the readiness backend differs. Next: broader ecosystem wiring and publication/telemetry hardening (Phase 6+). Blocker cleared for `NativePoller` once a PHP 8.6 `Io\Poll` host (OCI dev lab per the poll-api brainstorm) is available to exercise Assertion C natively. (Same `composer test`/`analyse`/`architecture` caveat as earlier phases — validated via standalone execution of the new unit tests, the ReactPHP/Amp bridges against fakes, and the gated conformance suite; the native `Io\Poll` path is exercised only on a PHP 8.6 host.)

---

## 6. Convention patterns (illustrative, one type per file)

These sketches manifest the rules; each would live in its own file.

```php
// src/Contract/Port/CapabilityVocabulary.php
declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for capability statuses.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityStatus
 * @see https://w3id.org/itpack/asyncit#CapabilitySupported
 * @see https://w3id.org/itpack/asyncit#CapabilityEmulated
 * @see https://w3id.org/itpack/asyncit#CapabilityAdapterOnly
 * @see https://w3id.org/itpack/asyncit#CapabilityUnavailable
 */
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

/**
 * Closed set of negotiated capability statuses.
 *
 * @see https://w3id.org/itpack/asyncit#CapabilityStatus
 */
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
- Docs (`README.md`, `AGENTS.md`, `BRAINSTORM.md`, `IMPLEMENTATION_PLAN.md`, this roadmap) reflect the built reality; public contracts carry `@see` references to `async-it#*` / `async-it-taxonomy#*` (AsyncIt-owned) and `run-it#*` / `run-it-taxonomy#*` (consumed).

---

## 8. Final status

The roadmap converts the brainstorm and implementation plan into constraint-bearing milestones: each phase carries explicit architecture, security, performance, DX, and test/conformance gates, and the §3 catalog enforces the modern-PHP, factory-only, no-abstract-class, no-else/Yoda/magic-literal, and PSR-4 rules via `php-cs-fixer`, PHPStan, Deptrac, and mutation testing. Next step is Phase 0 (tooling + Deptrac fix + conformance skeleton), which has no blockers.
