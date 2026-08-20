# CommandIt — ROADMAP

> In-depth, step-by-step implementation roadmap for CommandIt.
> Each milestone is independently shippable and verifiable. It operationalizes
> `docs/IMPLEMENTATION_PLAN.md` while honoring every rule in `AGENTS.md`
> (architecture boundaries, domain invariants, PHP standards, security, testing, quality gates).

Conventions used below:

- **DoD** = Definition of Done (acceptance gate for a milestone).
- **QG** = Quality Gate commands that must pass before a milestone is merged.
- Tags like `[Domain]`, `[Contract]`, `[Core]`, `[Adapter]`, `[SDK]`, `[Port]` mark the layer a task touches.
- All PHP targets `>= 8.4`; concrete classes `final` + `readonly` where eligible; interfaces at boundaries.

---

## Milestone 0 — Project skeleton & quality gates

**Goal:** Establish the package scaffold, tooling, and architectural guardrails before any behavior exists.

### Tasks

- [ ] **M0.1** Update `composer.json`:
  - `"php": "^8.4"` in `require`.
  - Add `require-dev`: `just-bases/php-unit-test-base`, `just-bases/php-qa-base`, `just-bases/php-obs-dev-base`, `phpstan/phpstan`, `phpmetrics/*` as needed, `codeception/*`, `infection/infection`, `friendsofphp/php-cs-fixer`, `qossmic/deptrac-shim`.
  - `autoload-dev`: `{"Tests\\": "tests/"}`.
  - `suggest`: `symfony/console` ("CLI projection adapter"), and optional PSR packages.
  - Add `scripts` mirroring RunIt: `test`, `test:unit`, `test:functional`, `test:integration`, `analyse`, `architecture`, `cs:check`, `cs:fix`, `mutation`, `security:audit`, `quality`.
- [ ] **M0.2** Add `phpstan.neon.dist` (highest level; no first-party baseline).
- [ ] **M0.3** Add `deptrac.yaml` encoding layers: `Domain`, `Contract`, `Application/Core`, `SDK`, `Adapter`, `Port`. Forbid `Domain → Adapter`, `Domain → SDK`, `Core → SDK`, `Core → Adapter`, and **any** `→ RunIt`/`→ Symfony`.
- [ ] **M0.4** Add `php-cs-fixer.dist.php` matching family style (`final`, `readonly`, `declare(strict_types=1)`, no global state).
- [ ] **M0.5** Add `infection.json5` (min MSI pinned, raised per milestone).
- [ ] **M0.6** Create empty `tests/{Unit,Functional,Integration}` with a passing smoke test; create `tests/_support` fixtures.
- [ ] **M0.7** Add `docs/architecture/MODEL.md` stub (filled progressively).

### DoD

`composer validate --strict`, `cs:check`, `analyse`, `architecture`, and the smoke test all pass on an otherwise empty source tree.

### QG

```bash
composer validate --strict
composer cs:check
composer analyse
composer architecture
composer test:unit
```

---

## Milestone 1 — Value objects & behaviors (foundation)

**Goal:** Build the reusable, stateless primitives every domain artifact depends on. No execution logic yet.

### Tasks

- [ ] **M1.1 `[ValueObject]`** `ValidatedString` — extensible validated string with normalization + format validation. Backed by `Behavior/Domain/ValidatedStringValueBehavior`.
- [ ] **M1.2 `[ValueObject]`** `CommandName`, `ArgumentName`, `OptionName` — each wraps `ValidatedString` via property-hook `set` (normalize/validate) and `get` (canonical form). Use the `ValidatedStringValueBehavior` trait (DRY).
- [ ] **M1.3 `[Behavior]`** `NamedConstructorBehavior` — shared `from*` named constructors (stateless).
- [ ] **M1.4 `[Behavior]`** `TimestampedBehavior` — projects a `createdAt`/`startedAt` onto `Countable`/timing (mirror RunIt's trait shape).
- [ ] **M1.5 `[Behavior]`** `ExecutionOwnedBehavior` — links an artifact to its execution id (stateless, no hidden deps).
- [ ] **M1.6 `[Behavior]`** `CommandNamedBehavior` — exposes `commandName()` projection.
- [ ] **M1.7 `[Contract]`** `Exception/CommandExceptionInterface` (marker) + `InvalidCommandException` implementing it.

### DoD

All value objects are `final readonly`, validated immutably, and covered by unit tests asserting:
rejection of invalid names, idempotent normalization, no mutation after construction.

### QG

```bash
composer test:unit
composer analyse
composer cs:check
```

---

## Milestone 2 — Domain model (the core vocabulary)

**Goal:** Define the immutable domain artifacts and the closed-state enums. This is the heart of CommandIt.

### Tasks

- [ ] **M2.1 `[Domain/enum]`** `CommandStatus` (DEFINED, RECEIVED, EXECUTING, SUCCEEDED, FAILED, CANCELLED) — closed internal state.
- [ ] **M2.2 `[Domain/enum]`** `CommandResultStatus` (SUCCEEDED, FAILED).
- [ ] **M2.3 `[Domain/enum]`** `InputKind` (ARGUMENT, OPTION) — closed.
- [ ] **M2.4 `[Contract/Command]`** `CommandInterface` — immutable intent; `name(): CommandName`, `input(): CommandInputInterface`, `payload(): mixed`.
- [ ] **M2.5 `[Contract/Command]`** `ArgumentInterface`, `OptionInterface` — name, kind, required flag, type coercion contract.
- [ ] **M2.6 `[Contract/Command]`** `CommandInputInterface` — immutable resolved input (arguments + options), typed getters, no mutation.
- [ ] **M2.7 `[Contract/Command]`** `CommandResultInterface` — `status(): CommandResultStatus`, `payload(): mixed`, `execution(): ?CommandExecution`.
- [ ] **M2.8 `[Contract/Command]`** `CommandDefinitionInterface` — metadata: `name()`, `description()`, `arguments()`, `options()`, `handlerKey()`, and a **virtual `signature` property** (property hook) computed from name/args/options.
- [ ] **M2.9 `[Domain]`** `Argument`, `Option`, `CommandInput` — `final readonly` implementations; `CommandInput` uses `set` hooks for per-value type coercion.
- [ ] **M2.10 `[Domain]`** `Command` — `final readonly` value object implementing `CommandInterface` (built via SDK later).
- [ ] **M2.11 `[Domain]`** `CommandResult` — `final readonly`; `succeed()`/`fail()` named constructors.
- [ ] **M2.12 `[Domain]`** `CommandExecution` — **self-contained, RunIt-shaped** record (id, definition, status, startedAt, endedAt?, result?). `complete()` returns a terminal copy. Implements a local `ExecutionArtifactInterface` (defined in `Contract` so RunIt can align later).

### DoD

Domain invariants from `AGENTS.md` hold and are asserted by tests:
- a command is immutable and maps to exactly one execution;
- terminal `CommandExecution` cannot re-transition;
- results/input are immutable;
- names are validated extensible strings, statuses are closed enums.

### QG

```bash
composer test:unit
composer analyse
composer architecture
composer cs:check
```

---

## Milestone 3 — Ports (external-effect seams) + default adapters

**Goal:** Define all external-effect contracts and ship RunIt-agnostic defaults. This is the integration seam.

### Tasks

- [ ] **M3.1 `[Contract/Port]`** `ClockInterface` (monotonic) + `IdentifierGeneratorInterface`.
- [ ] **M3.2 `[Contract/Port]`** `SerializerInterface` — explicit, deterministic; arbitrary-object serialization prohibited.
- [ ] **M3.3 `[Contract/Port]`** `ExecutionReporterInterface` — `reportObservation`, `reportOccurrence`, `reportMeasurement`, `beginActivity`, `endActivity`. Occurrence types: `CommandReceived`, `CommandHandled`, `CommandFailed`.
- [ ] **M3.4 `[Contract/Port]`** `ExecutionContextProviderInterface` — ambient/per-execution `CommandContext`.
- [ ] **M3.5 `[Contract/Port]`** `CommandExecutionStorageInterface` — retain/persist the execution record (default no-op).
- [ ] **M3.6 `[Contract/Port]`** `RuntimeContextDetectorInterface` — `currentFiber(): ?Fiber`, `isCoroutineRuntime(): bool`.
- [ ] **M3.7 `[Adapter/Reporting]`** `NullExecutionReporter` (no-op) and `CollectingExecutionReporter` (in-memory, bounded buffer; for tests/CLI).
- [ ] **M3.8 `[Adapter/Context]`** `AmbientContextStorage` — request-local default context provider.
- [ ] **M3.9 `[Adapter/Context]`** `FiberBoundContextStorage` — `WeakMap<Fiber, CommandContext>`; inherits from/backs `AmbientContextStorage`; restores/clear in `finally`; no global mutable state.
- [ ] **M3.10 `[Adapter/Lazy]`** `LazyHandlerProxy` — wraps a handler **factory** with `GhostObject::newLazyGhost`; instantiates on first `handle()`. Default handler resolver strategy (see M4).
- [ ] **M3.11 `[Port]`** Default `Clock`/`IdentifierGenerator` implementations (UUID/monotonic) for SDK/tests.

### DoD

All ports are interfaces with no RunIt/Symfony imports. `FiberBoundContextStorage` tested for isolation
and no cross-Fiber leakage. `LazyHandlerProxy` tested: handler not constructed until first call; constructed
exactly once.

### QG

```bash
composer test:unit
composer test:functional   # lazy + fiber isolation functional checks
composer architecture
composer analyse
```

---

## Milestone 4 — Application core (orchestration)

**Goal:** Implement the command bus / executor that runs the lifecycle and preserves domain invariants.

### Tasks

- [ ] **M4.1 `[Contract/Command]`** `CommandHandlerInterface` — `handles(): string`, `handle(CommandInterface): CommandResultInterface`.
- [ ] **M4.2 `[Contract/Command]`** `CommandBusInterface` — `dispatch(CommandInterface): CommandResultInterface`.
- [ ] **M4.3 `[Contract/Command]`** `CommandMiddlewareInterface` — `process(CommandInterface, Next): CommandResultInterface`.
- [ ] **M4.4 `[Contract/Application]`** `HandlerResolverInterface` — resolves (lazily) a handler for a command; throws `HandlerNotFoundException`. Default = `LazyHandlerProxy`.
- [ ] **M4.5 `[Contract/Application]`** `CommandRegistryInterface` — name/class → definition + handler reference.
- [ ] **M4.6 `[Contract/Application]`** `CommandExecutorInterface` — the orchestrator interface.
- [ ] **M4.7 `[Contract/Application]`** `CommandExecutionCoordinatorInterface` — owns the lifecycle state machine (validate → received → executing → terminal) and occurrence emission.
- [ ] **M4.8 `[Core]`** `CommandRegistry` — register/find definitions; discovery support.
- [ ] **M4.9 `[Core]`** `HandlerResolver` — uses `LazyHandlerProxy` by default; DI-aware strategy pluggable.
- [ ] **M4.10 `[Core]`** `MiddlewareChain` — composes middlewares + terminal handler; explicit order.
- [ ] **M4.11 `[Core]`** `CommandExecutionCoordinator` — enforces transitions, records `CommandReceived`/`CommandHandled`/`CommandFailed`, measures duration (monotonic clock), restores context in `finally`.
- [ ] **M4.12 `[Core]`** `CommandExecutor` — `final`, implements `CommandExecutorInterface` + `CommandBusInterface`: validate → resolve (lazy) → create `CommandExecution` → run middleware/handler within fiber-bound context → report → produce `CommandResult` → **rethrow original Throwable** on handler failure.
- [ ] **M4.13 `[Exception]`** `HandlerNotFoundException`, `CommandExecutionException` (marker + concrete).

### DoD

- Dispatch success produces `SUCCEEDED` result + `CommandHandled` occurrence.
- Handler `Throwable` → `FAILED` occurrence + **original** exception rethrown (reporter failure must not replace it).
- Invalid command rejected before execution begins.
- Terminal executions cannot re-transition; idempotent completion.
- No global mutable context; context restored in `finally`.

### QG

```bash
composer test:unit
composer test:functional
composer architecture
composer analyse
composer mutation      # raise MSI; prioritize lifecycle/failure branches
```

---

## Milestone 5 — SDK (ergonomic definition & dispatch)

**Goal:** Make defining and dispatching commands terse and misuse-resistant.

### Tasks

- [ ] **M5.1 `[SDK]`** `CommandDefinitionBuilder` — fluent, immutable: `name()`, `description()`, `argument()`, `option()`, `handler()`, `build(): CommandDefinitionInterface`. Virtual `signature` verified.
- [ ] **M5.2 `[SDK]`** `CommandBuilder` — fluent construction of a `Command` from a definition + input.
- [ ] **M5.3 `[SDK]`** `CommandInputParser` (core) + `ArrayInputAdapter` (adapter) — validate required args/options, coerce types via `InputKind`, produce immutable `CommandInput`.
- [ ] **M5.4 `[SDK]`** `CommandIt` facade — assembles registry + executor + default ports; `register(definition)`, `dispatch(command)`, `bus()`. No hidden global container.
- [ ] **M5.5 `[SDK]`** Documentation examples in `README.md` and `docs/architecture/MODEL.md` using current public APIs and `declare(strict_types=1)`.

### DoD

A consumer can define + dispatch a command in a few lines with **no** Symfony/RunIt dependency. Builders are
immutable; `build()` returns a validated, `readonly` definition.

### QG

```bash
composer test:unit
composer test:functional
composer cs:check
composer analyse
```

---

## Milestone 6 — CLI adapter (Symfony Console projection)

**Goal:** Project CommandIt commands onto a CLI without coupling core to Symfony.

### Tasks

- [ ] **M6.1 `[Adapter/CLI]`** `ArgvInputParser` — `$argv` → `CommandInput` for a definition (validation + coercion).
- [ ] **M6.2 `[Adapter/SymfonyConsole]`** `ConsoleCommandAdapter` — `CommandDefinition` → Symfony `Command`; `execute()` dispatches via `CommandBus`, writes `CommandResult` to `OutputInterface`. Symfony is `suggest` only.
- [ ] **M6.3 `[Adapter/SymfonyConsole]`** `ConsoleApplicationBridge` — registers many definitions into a Symfony `Application`; `--list` from `CommandRegistry`.
- [ ] **M6.4** Ensure `deptrac` forbids `Core/Domain → symfony/console`; adapter layer only.
- [ ] **M6.5** No execution-context leakage across CLI invocations (persistent-worker safe).

### DoD

End-to-end CLI test: argv → parse → dispatch → result rendered to output. Core source has zero Symfony imports
(verified by DepTrac + grep).

### QG

```bash
composer test:integration
composer architecture
composer analyse
```

---

## Milestone 7 — Cross-cutting: security, performance, self-instrumentation

**Goal:** Enforce `AGENTS.md` security/perf/recursion rules before 1.0.

### Tasks

- [ ] **M7.1 `[Security]`** Redaction policy in `CollectingExecutionReporter`/reporter port: never export secrets; redact sensitive input keys; bound attribute size/cardinality. Negative tests for redaction.
- [ ] **M7.2 `[Security]`** Prohibition of arbitrary-object serialization honored by `SerializerInterface`.
- [ ] **M7.3 `[Perf]`** Benchmarks (phpbench) for dispatch + handler resolution; lazy resolution verified to skip construction for invalid commands.
- [ ] **M7.4 `[Self-instrumentation]`** Publication recursion guard + separate health reporter path; bootstrap path uninstrumented.
- [ ] **M7.5 `[Perf]`** Bounded buffers/queues + defined overflow behavior in `CollectingExecutionReporter`.
- [ ] **M7.6** Fiber/coroutine isolation tests in persistent-worker simulation (no cross-request context bleed).

### DoD

Security-sensitive behavior has negative tests. Benchmarks exist for hot paths. Recursion guard prevents
self-observation loops.

### QG

```bash
composer test:integration
composer mutation
composer architecture
composer security:audit
```

---

## Milestone 8 — Documentation, ADR & release hardening

**Goal:** Finalize public-facing docs and stability guarantees.

### Tasks

- [ ] **M8.1** Expand `docs/architecture/MODEL.md` (command lifecycle diagram, vocabulary mapping, RunIt integration seam).
- [ ] **M8.2** Add ADR(s) for: (a) RunIt integration via ports, (b) lazy handler resolution, (c) fiber-bound context.
- [ ] **M8.3** `@see` vocabulary IRI references on public contracts per "Vocabulary Alignment".
- [ ] **M8.4** Finalize `README.md` with executable examples and install (incl. `suggest` adapters).
- [ ] **M8.5** Raise mutation MSI to project target; ensure `composer quality` green.
- [ ] **M8.6** Backward-compatibility check configured (`dantleech/what-changed` already allowed in family config).

### DoD

`composer quality` (validate → cs → analyse → architecture → test → audit) passes. Docs reflect public behavior.
ADRs capture the three load-bearing decisions.

### QG

```bash
composer quality
```

---

## Cross-cutting rules enforced every milestone

- No `Domain → Adapter/SDK/Symfony/RunIt` dependency (DepTrac gate per milestone).
- Concrete classes `final`; eligible classes `readonly`; every concrete class implements an interface.
- User-defined names = validated strings; closed internal state = enums.
- Original business exceptions preserved; reporter/adapter failures never replace them.
- Context stored per-Fiber via `WeakMap`, never in global mutable state.
- Tests at the narrowest level; mutation coverage for critical branches.
- No speculative abstractions; no broad static-analysis ignores; no baseline regeneration to pass CI.

## Dependency order (why this sequence)

```
M0 (scaffold)
  → M1 (value objects/behavior) → M2 (domain model)
      → M3 (ports + defaults) → M4 (core orchestration)
          → M5 (SDK) → M6 (CLI adapter)
              → M7 (security/perf/self-instrument) → M8 (docs/ADR/release)
```

Each arrow is a hard prerequisite: later milestones depend only on completed earlier interfaces, so any
milestone can be shipped and tested in isolation.
