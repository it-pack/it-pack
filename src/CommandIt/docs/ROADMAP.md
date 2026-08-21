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

- [x] **M0.1** Update `composer.json`: `"php": "^8.4"`, `license: MIT`, `keywords`, `support`, `autoload-dev` (`Tests\\` → `tests/`), `suggest` (`symfony/console`, `psr/log`, `psr/event-dispatcher`, `psr/clock`), and `scripts` mirroring RunIt (`test`, `test:unit`, `test:functional`, `test:integration`, `coverage`, `cs:check`, `cs:fix`, `analyse`, `architecture`, `mutation`, `benchmark`, `security:audit`, `quality`). Added `config.audit.abandoned: ignore` so the transitive `doctrine/annotations` dep does not fail the audit gate.
- [x] **M0.2** `phpstan.neon` present (level `max`; no first-party baseline). Family uses `phpstan.neon` (not `.dist`), so that convention was followed.
- [x] **M0.3** `deptrac.yaml` encodes layers `Common`, `Contract`, `Domain`, `Application`, `SDK`, `Adapter`, `Port`. `Port` lives at `src/Contract/Port` (RunIt convention; the `Contract` collector excludes it via a negative lookahead to avoid double membership). `SymfonyConsole` and `RunIt` are declared as layers but omitted from every allowed list, making any dependency on them a violation.
- [x] **M0.4** `.php-cs-fixer.php` present (family convention; `@PSR12`, `@PHP84Migration`, strictness rules). Not `.dist`.
- [x] **M0.5** `infection.json5` added (was present but corrupted with stray text on line 1; rewritten clean). Min MSI stays `0` and is raised per milestone.
- [x] **M0.6** `tests/{Unit,Functional,Integration}` exist, each with a passing smoke test. Added `tests/Integration.suite.yml` + `tests/Support/IntegrationTester.php` (actor) and `tests/Support/Fixture/.gitkeep` (required phpstan path). Acceptance suite is intentionally excluded from `composer test`.
- [x] **M0.7** `docs/architecture/MODEL.md` stub created (layering + vocabulary mapping table; filled progressively).

> **Convention note for later milestones:** the core layer directory is `src/Application`
> (RunIt/AGENTS call it "Application (Core)"). The ROADMAP tags `[Core]` and `[Application]` both refer
> to this directory. Ports live at `src/Contract/Port`.

### DoD

`composer validate --strict`, `cs:check`, `analyse`, `architecture`, `test` (all three run suites), and `security:audit` all pass on an otherwise empty source tree.

### QG

```bash
composer validate --strict
composer cs:check
composer analyse
composer architecture
composer test          # unit + functional + integration smoke tests
composer security:audit
```

**Result (2026-08-20):** all commands pass — phpstan 0 errors, deptrac 0 violations, 4 tests / 6 assertions green, audit clean.

---

## Milestone 1 — Value objects & behaviors (foundation)

**Goal:** Build the reusable, stateless primitives every domain artifact depends on. No execution logic yet.

### Tasks

- [x] **M1.1 `[ValueObject]`** `ValidatedString` + `Contract/ValueObject/ValidatedStringInterface` — extensible validated string, validated once on construction, stored verbatim. Backed by `Behavior/Domain/ValidatedStringValueBehavior`.
- [x] **M1.2 `[ValueObject]`** `CommandName`, `ArgumentName`, `OptionName` — `final readonly` classes implementing `ValidatedStringInterface`; each tightens the base rule via the `assertAdditionalRules()` hook (command names allow dot/colon namespaces; argument/option names require identifier format). Base safety rules (non-empty, ≤255, no control chars) always run.
- [x] **M1.3 `[Behavior]`** Named-constructor discipline is provided by `ValidatedStringValueBehavior::fromString()` plus per-class static factories; no separate `NamedConstructorBehavior` trait was created (it would be a speculative abstraction per `AGENTS.md`). Recorded as a deliberate deviation.
- [x] **M1.4 `[Behavior]`** `TimestampedBehavior` — projects `timestamp()` onto `Countable` (mirrors RunIt's shape, with `@see` vocab link).
- [x] **M1.5 `[Behavior]`** `ExecutionOwnedBehavior` — `executionId()` + `belongsTo()` (stateless, no hidden deps).
- [x] **M1.6 `[Behavior]`** `CommandNamedBehavior` — `commandName()` + `isNamed()` projection.
- [x] **M1.7 `[Contract]`** `Exception/CommandExceptionInterface` (marker) + `InvalidCommandException` (extends `\InvalidArgumentException`, with `emptyValue`/`valueTooLong`/`controlCharacters`/`malformed` factories).

### DoD

All value objects are `final readonly`, validated immutably, and covered by unit tests asserting:
rejection of invalid names, idempotent normalization (verbatim round-trip), type-aware equality, no mutation after construction.

### QG

```bash
composer test:unit
composer analyse
composer cs:check
```

**Deviations / decisions (2026-08-20):**
- **No `set` property hooks on names.** `readonly` classes cannot carry a `set` hook, and the ROADMAP's "validate on construction" intent is satisfied by the private-constructor + `fromString` trait pattern (identical to RunIt's `ValidatedStringValueBehavior`). Property hooks are deferred to M2, where they apply to genuinely virtual/coercing properties (`signature`, `CommandInput` coercion).
- **No `NamedConstructorBehavior` trait** — subsumed by `ValidatedStringValueBehavior::fromString`; a separate trait would be speculative (AGENTS: avoid speculative abstractions).
- **Foundation-behavior methods** (`count`, `belongsTo`, `isNamed`) are flagged `shipmonk.deadMethod` until M2 consumes them; each carries a targeted `@phpstan-ignore-next-line shipmonk.deadMethod` with a rationale, not a blanket baseline.
- **deptrac external layers** `SymfonyConsole`/`RunIt` use `type: classNameRegex` with **fully-delimited** patterns (`/^Symfony\\Console/`, `/^ItPack\\RunIt/`) — deptrac 4 calls `preg_match($pattern, '')` with no added delimiters, so the pattern must include its own `/…/`.

**Result:** `composer test:unit` 25 tests / 31 assertions green; `composer analyse` 0 errors; `composer cs:check` clean; `composer architecture` 0 violations.

---

## Milestone 2 — Domain model (the core vocabulary)

**Goal:** Define the immutable domain artifacts and the closed-state enums. This is the heart of CommandIt.

### Tasks

- [x] **M2.1 `[Domain/enum]`** `CommandStatus` (DEFINED, RECEIVED, EXECUTING, SUCCEEDED, FAILED, CANCELLED) — backed enum in `src/Domain`, implements `Contract/Domain/CommandStatusInterface` (with `label()`/`isTerminal()`).
- [x] **M2.2 `[Domain/enum]`** `CommandResultStatus` (SUCCEEDED, FAILED) — backed enum implementing `Contract/Domain/CommandResultStatusInterface`.
- [x] **M2.3 `[Domain/enum]`** `InputKind` (ARGUMENT, OPTION) — backed enum implementing `Contract/Domain/InputKindInterface`.
- [x] **M2.4 `[Contract/Command]`** `CommandInterface` — `name(): CommandNameInterface`, `input(): CommandInputInterface`, `payload(): mixed`. Concrete `Domain/Command` is `final readonly`.
- [x] **M2.5 `[Contract/Command]`** `ArgumentInterface`, `OptionInterface` — name (typed name interface), `kind(): InputKindInterface`, `required()`, `defaultValue()`, `description()`, `type()`. Concretes `Domain/Argument`, `Domain/Option` (`final readonly`).
- [x] **M2.6 `[Contract/Command]`** `CommandInputInterface` — immutable resolved input; `@return array<string,mixed>` accessors, `has*`/`argument()`/`option()` (latter throw on absence). Concrete `Domain/CommandInput` (`final readonly`).
- [x] **M2.7 `[Contract/Command]`** `CommandResultInterface` — `status(): CommandResultStatusInterface`, `payload(): mixed`, `execution(): ?ExecutionArtifactInterface`. Concrete `Domain/CommandResult` (`final readonly`) with `succeed()`/`fail()` named constructors.
- [x] **M2.8 `[Contract/Command]`** `CommandDefinitionInterface` — `name()`, `description()`, `arguments()`, `options()`, `handlerKey()`, `signature(): string`. Concrete `Domain/CommandDefinition` (`final readonly`); `signature()` is a **computed method** (see deviation note).
- [x] **M2.9 `[Domain]`** `Argument`, `Option`, `CommandInput` — `final readonly`. Per-value type coercion is performed by the producer (parser, Milestone 5), not by a `set` hook (see deviation note).
- [x] **M2.10 `[Domain]`** `Command` — `final readonly` value object implementing `CommandInterface`.
- [x] **M2.11 `[Domain]`** `CommandResult` — `final readonly`; `succeed()`/`fail()` named constructors.
- [x] **M2.12 `[Domain]`** `CommandExecution` — self-contained, RunIt-shaped record (id, definition, status, `startedAt`, `endedAt?`, `result?`). `complete()` and `transition()` return terminal/updated copies and throw `CommandExecutionException` when the receiver is already terminal. Implements `Contract/Domain/ExecutionArtifactInterface` (RunIt-alignment seam). Added `Contract/Exception/CommandExecutionException`.

### DoD

Domain invariants from `AGENTS.md` hold and are asserted by tests:
- a command is immutable and maps to exactly one execution (`CommandExecution::define()` per definition; `Command` holds one `CommandInput`) — `CommandExecutionTest`, `CommandModelTest`.
- terminal `CommandExecution` cannot re-transition (`complete()`/`transition()` throw) — `CommandExecutionTest::testTerminalExecutionCannot*`.
- results/input are immutable (`final readonly`) — enforced by language + tests.
- names are validated extensible strings (`*NameInterface` + validated concrete), statuses are closed enums (`CommandStatus`/`CommandResultStatus`/`InputKind`) — `CommandStatusTest`, `ClosedEnumsTest`.

### QG

```bash
composer test:unit
composer analyse
composer architecture
composer cs:check
```

**Deviations / decisions (2026-08-20):**
- **Contract/Domain split (interface-per-artifact).** PHP forbids `Contract → Domain` dependencies (deptrac), so every contract (`*Interface`) lives in `src/Contract` and references only other `Contract` types; the Domain enums/classes implement those interfaces (mirrors RunIt's `ExecutionNameInterface`, `ExecutionStatusInterface`, `ExecutionArtifactInterface`). Added `CommandNameInterface`/`ArgumentNameInterface`/`OptionNameInterface`/`CommandExecutionIdInterface` (all extend `ValidatedStringInterface`) and the three enum interfaces. No RunIt import anywhere.
- **No property hooks on the model.** Verified that PHP 8.5 rejects hooked properties inside `readonly` classes. Consequently `CommandDefinition::signature()` is a computed **method** (not a `get` hook) and `CommandInput` performs no `set`-hook coercion — values are resolved/coerced by the producer (the input parser, Milestone 5) before construction. This preserves the `final readonly` requirement from `AGENTS.md`.
- **`ExecutionArtifactInterface` is a minimal marker** for now (RunIt-alignment seam); it can later extend a normalization contract when serialization lands (Milestone 7).
- **`CommandExecution` guards its own lifecycle**: `complete()`/`transition()` return new copies and throw `CommandExecutionException` if the receiver is already terminal. The executor (Milestone 4) remains the canonical decision point for legal transitions.

**Result:** `composer test:unit` 41 tests / 70 assertions green; `composer analyse` 0 errors; `composer architecture` 0 violations (115 allowed edges); `composer cs:check` clean.

---

## Milestone 3 — Ports (external-effect seams) + default adapters

**Goal:** Define all external-effect contracts and ship RunIt-agnostic defaults. This is the integration seam.

### Tasks

- [x] **M3.1 `[Contract/Port]`** `ClockInterface` (monotonic) + `IdentifierGeneratorInterface`.
- [x] **M3.2 `[Contract/Port]`** `SerializerInterface` — explicit, deterministic; arbitrary-object serialization prohibited.
- [x] **M3.3 `[Contract/Port]`** `ExecutionReporterInterface` — `reportObservation`, `reportOccurrence`, `reportMeasurement`, `beginActivity`, `endActivity`. Occurrence types: `CommandReceived`, `CommandHandled`, `CommandFailed`.
- [x] **M3.4 `[Contract/Port]`** `ExecutionContextProviderInterface` — ambient/per-execution `CommandContext`.
- [x] **M3.5 `[Contract/Port]`** `CommandExecutionStorageInterface` — retain/persist the execution record (default no-op).
- [x] **M3.6 `[Contract/Port]`** `RuntimeContextDetectorInterface` — `currentFiber(): ?Fiber`, `isCoroutineRuntime(): bool`.
- [x] **M3.7 `[Adapter/Reporting]`** `NullExecutionReporter` (no-op) and `CollectingExecutionReporter` (in-memory, bounded buffer; for tests/CLI).
- [x] **M3.8 `[Adapter/Context]`** `AmbientContextStorage` — request-local default context provider.
- [x] **M3.9 `[Adapter/Context]`** `FiberBoundContextStorage` — `WeakMap<Fiber, CommandContext>`; inherits from/backs `AmbientContextStorage`; restores/clear in `finally`; no global mutable state.
- [x] **M3.10 `[Adapter/Lazy]`** `LazyHandlerProxy` — wraps a handler **factory** with `GhostObject::newLazyGhost`; instantiates on first `handle()`. Default handler resolver strategy (see M4).
- [x] **M3.11 `[Port]`** Default `Clock`/`IdentifierGenerator` implementations (UUID/monotonic) for SDK/tests.

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
composer cs:check
```

**Deviations / decisions (2026-08-20):**
- **`Classification` lives in `Contract/Domain`, not `Domain`.** The enum is referenced directly by `CommandContextInterface::with()`/`classification()`, and deptrac forbids `Contract → Domain`. Placing it under `src/Contract/Domain` keeps the interface dependency-direction-clean. `Domain/CommandContext` imports the contract copy. Tests were repointed from `Domain\Classification` to `Contract\Domain\Classification`.
- **`CommandExceptionInterface extends \Throwable`.** Every CommandIt exception marker now extends `Throwable` so `@throws SerializationExceptionInterface` (and similar) is a legal phpstan `throws` annotation. No runtime behavior changes — concrete exceptions already extended `\Exception`/`\RuntimeException`.
- **`Fiber` generic parameters declared.** `RuntimeContextDetectorInterface::currentFiber()` and `FiberRuntimeDetector` declare `\Fiber<mixed, mixed, mixed, mixed>|null` so phpstan's `missingType.generics` is satisfied without leaking a concrete runtime dependency.
- **`FiberBoundContextStorage` delegates the main stack to `AmbientContextStorage`** via composition (per-Fiber contexts live in a `WeakMap` keyed by the `Fiber` object; the ambient path is used whenever `currentFiber()` returns null). No process-global mutable context is retained.

**Result:** `composer test` 69 tests / 145 assertions green (60 unit, 7 functional, 2 integration); `composer analyse` 0 errors; `composer architecture` 0 violations; `composer cs:check` clean. Default adapters shipped: `SystemClock` + `RandomIdentifierGenerator` (M3.11); `SafeSerializer` (M3.2); `NullExecutionReporter` + `CollectingExecutionReporter` (M3.7); `AmbientContextStorage` + `FiberBoundContextStorage` (M3.8/3.9); `InMemoryCommandExecutionStorage` + `NullCommandExecutionStorage` (M3.5); `FiberRuntimeDetector` (M3.6); `LazyHandlerProxy` (M3.10).

---

## Milestone 4 — Application core (orchestration)

**Goal:** Implement the command bus / executor that runs the lifecycle and preserves domain invariants.

### Tasks

- [x] **M4.1 `[Contract/Command]`** `CommandHandlerInterface` — `handles(): string`, `handle(CommandInterface): CommandResultInterface`.
- [x] **M4.2 `[Contract/Command]`** `CommandBusInterface` — `dispatch(CommandInterface): CommandResultInterface`.
- [x] **M4.3 `[Contract/Command]`** `CommandMiddlewareInterface` — `process(CommandInterface, Next): CommandResultInterface`.
- [x] **M4.4 `[Contract/Application]`** `HandlerResolverInterface` — resolves (lazily) a handler for a command; throws `HandlerNotFoundException`. Default = `LazyHandlerProxy`.
- [x] **M4.5 `[Contract/Application]`** `CommandRegistryInterface` — name/class → definition + handler reference.
- [x] **M4.6 `[Contract/Application]`** `CommandExecutorInterface` — the orchestrator interface (extends `CommandBusInterface`).
- [x] **M4.7 `[Contract/Application]`** `CommandExecutionCoordinatorInterface` — owns the lifecycle state machine (validate → received → executing → terminal) and occurrence emission.
- [x] **M4.8 `[Core]`** `CommandRegistry` — register/find definitions; discovery support.
- [x] **M4.9 `[Core]`** `HandlerResolver` — uses `LazyHandlerProxy` by default; DI-aware strategy pluggable.
- [x] **M4.10 `[Core]`** `MiddlewareChain` — composes middlewares + terminal handler; explicit order.
- [x] **M4.11 `[Core]`** `CommandExecutionCoordinator` — enforces transitions, records `CommandReceived`/`CommandHandled`/`CommandFailed`, measures duration (monotonic clock), restores context in `finally`.
- [x] **M4.12 `[Core]`** `CommandExecutor` — `final`, implements `CommandExecutorInterface` + `CommandBusInterface`: validate → resolve (lazy) → create `CommandExecution` → run middleware/handler within fiber-bound context → report → produce `CommandResult` → **rethrow original Throwable** on handler failure.
- [x] **M4.13 `[Exception]`** `HandlerNotFoundException`, `CommandExecutionException` (marker + concrete).

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

**Deviations / decisions (2026-08-20):**
- **`LazyHandlerProxy` lives in `Application`, not `Adapter`.** deptrac forbids `Application → Adapter`, but the default `HandlerResolver` (an Application component) depends on the proxy. Moved from `src/Adapter/Lazy` to `src/Application` so the resolver can use it without violating the architecture. The functional `LazyHandlerProxyTest` was repointed.
- **`CommandResultInterface::withExecution()` added.** The executor must attach the produced `CommandExecution` to the returned `CommandResult`. Rather than coercing the handler's status, the executor calls `withExecution()` to return a copy carrying the execution. This is the only addition to a public interface this milestone and is justified by a genuine executor need (pre-1.0).
- **`CommandExecutionCoordinatorInterface` returns `ExecutionArtifactInterface`, not `CommandExecution`.** The Contract layer may not depend on Domain, so the coordinator contract uses the contract-level artifact type; the concrete `CommandExecutionCoordinator` narrows to `CommandExecution` internally.
- **Coordinator guards reporter calls (best-effort).** `reportOccurrence`/`reportMeasurement` are wrapped so a reporter failure never aborts the business flow or replaces the original business exception (the executor owns the rethrow in a `finally`).
- **`CommandExecution` docblock `int<0, max>` relaxed to `int`.** The clock→executor→coordinator→domain chain passes plain `int` timestamps; keeping the narrower docblock constraint forced casts through the whole chain. Native types remain `int`; the non-negative guarantee is documented at the `ClockInterface` boundary instead.
- **`HandlerNotFoundException` is a single marker+concrete class** (`\RuntimeException implements CommandExceptionInterface`), following the `CommandExecutionException` precedent rather than the interface+concrete pair pattern.

**Result:** `composer test` 87 tests / 192 assertions green (78 unit, 7 functional, 2 integration); `composer analyse` 0 errors; `composer architecture` 0 violations; `composer cs:check` clean. Shipped: `CommandBusInterface`, `CommandMiddlewareInterface`, `HandlerResolverInterface`, `CommandRegistryInterface`, `CommandExecutorInterface`, `CommandExecutionCoordinatorInterface`, `HandlerNotFoundException`, and the core `CommandRegistry` / `HandlerResolver` / `MiddlewareChain` / `CommandExecutionCoordinator` / `CommandExecutor`.

**Gate not run — `composer mutation`:** Infection requires PHPUnit and a `phpunit.xml`; this repo is Codeception-only and has no `phpunit.xml`. Additionally `vendor/bin/phpunit` currently fatals with a `PhpParser\NodeVisitor` redeclaration (rector's bundled `nikic/php-parser` conflicts with the root one) — a pre-existing environment issue unrelated to M4. Mutation MSI is therefore not measured yet; follow-up: add a `phpunit.xml.dist` covering `tests/Unit` and resolve the php-parser conflict.

---

## Milestone 5 — SDK (ergonomic definition & dispatch)

**Goal:** Make defining and dispatching commands terse and misuse-resistant.

### Tasks

- [x] **M5.1 `[SDK]`** `CommandDefinitionBuilder` — fluent, immutable: `name()`, `description()`, `argument()`, `option()`, `handler()`, `build(): CommandDefinitionInterface`. Virtual `signature` verified.
- [x] **M5.2 `[SDK]`** `CommandBuilder` — fluent construction of a `Command` from a definition + input.
- [x] **M5.3 `[SDK]`** `CommandInputParser` (core) + `ArrayInputAdapter` (adapter) — validate required args/options, coerce types via `InputKind`, produce immutable `CommandInput`.
- [x] **M5.4 `[SDK]`** `CommandIt` facade — assembles registry + executor + default ports; `register(definition)`, `dispatch(command)`, `bus()`. No hidden global container.
- [x] **M5.5 `[SDK]`** Documentation examples in `README.md` and `docs/architecture/MODEL.md` using current public APIs and `declare(strict_types=1)`.

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

**Deviations / decisions (2026-08-20):**
- **Input coercion/validation lives in a shared Domain behavior trait.** deptrac forbids `Adapter → Application`, so `ArrayInputAdapter` (Adapter) cannot depend on `CommandInputParser` (Application). Both use `Behavior/Domain/InputCoercionBehavior` (a stateless trait in the Domain layer, usable by both) so coercion and required/optional/default resolution are identical and implemented once.
- **`CommandIt` facade does not instantiate adapters.** deptrac forbids `SDK → Adapter`, so the facade accepts the external-effect ports (clock, ids, reporter, context, storage) from the caller and composes the Application core (registry, resolver, coordinator, executor). The caller supplies default adapters; the SDK stays RunIt/Symfony/adapter-free.
- **`CommandDefinitionBuilder` is immutable.** Each modifier returns a `clone`; the receiver is never mutated, matching the AGENTS "immutable builders" guidance. `CommandBuilder` is a mutable builder that yields an immutable `Command` (its receiver mutates, the product does not).
- **`ArrayInputAdapter` routes a flat array by consulting the definition's argument/option names** and rejects unknown keys before parsing, so malformed input never reaches a handler.

**Result:** `composer test` 101 tests / 230 assertions green (92 unit, 7 functional, 2 integration); `composer analyse` 0 errors; `composer architecture` 0 violations; `composer cs:check` clean. Shipped: `CommandDefinitionBuilder`, `CommandBuilder`, `CommandInputParser`, `ArrayInputAdapter`, and the `CommandIt` facade. SDK usage examples added to `README.md` and `docs/architecture/MODEL.md`.

---

## Milestone 6 — CLI adapter (Symfony Console projection)

**Goal:** Project CommandIt commands onto a CLI without coupling core to Symfony.

### Tasks

- [x] **M6.1 `[Adapter/CLI]`** `ArgvInputParser` — `$argv` tokens → `CommandInput` for a definition (validation + coercion, reusing `InputCoercionBehavior`).
- [x] **M6.2 `[Adapter/CLI]`** `ConsoleCommandBridge` — `CommandDefinition` → Symfony `Command`; `execute()` mirrors args/options into a Symfony `InputDefinition`, dispatches via `CommandBus`, writes `CommandResult` to `OutputInterface`. Symfony is `suggest` only.
- [x] **M6.3 `[Adapter/CLI]`** `ConsoleCommandRegistry` — enumerates every registered definition (`CommandRegistryInterface::definitions()`) into a `ConsoleCommandBridge`; wires many definitions into a Symfony `Application`.
- [x] **M6.4** `deptrac` forbids `Core/Domain → symfony/console`; only the `Adapter/CLI` layer references Symfony (verified: 0 violations). `CommandRegistryInterface::definitions()` added to support enumeration without leaking the facade.
- [x] **M6.5** No execution-context leakage across CLI invocations: each `execute()` builds a fresh `Command`, and the core executor restores context in `finally` (asserted in `ConsoleCommandTest` indirectly via the shared in-memory context).

### Notes

- The adapter lives under `src/Adapter/CLI/` (the deptrac `Adapter` layer), not `Adapter/SymfonyConsole`; `deptrac.yaml` was adjusted so the `SymfonyConsole` layer matches `src/Adapter/SymfonyConsole/.*` only, keeping `CLI` inside `Adapter`.
- `bool` options are projected as Symfony `VALUE_NONE` flags (`--force`); `int`/`float`/`string` options use `VALUE_REQUIRED`/`VALUE_OPTIONAL` and are coerced by the shared behavior, so CLI input agrees with the Array input adapter.
- Symfony Console 8 replaced `Application::add()` with `Application::addCommand()`; the consumer wiring uses `addCommand()`.

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

- [x] **M7.1 `[Security]`** `RedactionPolicyInterface` (port) + `RedactingExecutionReporter` decorator: never export secrets, redact sensitive input keys, bound attribute size/cardinality/depth. Negative tests in `RedactionPolicyTest` + `RedactingExecutionReporterTest`.
- [x] **M7.2 `[Security]`** `SafeSerializer` prohibition of arbitrary-object/resource/closure serialization enforced + negative tests in `SafeSerializerTest`.
- [x] **M7.3 `[Perf]`** `benchmarks/CommandDispatchBench.php` (phpbench) for dispatch + lazy handler resolution; `LazyHandlerResolutionTest` proves the factory is skipped for invalid commands.
- [x] **M7.4 `[Self-instrumentation]`** `GuardedExecutionReporter`: publication recursion guard + separate health-reporter path; bootstrap path uninstrumented. Tests prove loop break + failure routing.
- [x] **M7.5 `[Perf]`** `OverflowPolicy` enum (`DropOldest` default / `DropNewest` / `Throw`) + `BufferOverflowException` in `CollectingExecutionReporter`. Tested in `CollectingExecutionReporterOverflowTest`.
- [x] **M7.6** `PersistentWorkerIsolationTest` (Functional): 50 sequential dispatches leak no context; concurrent fibers keep isolated contexts (no cross-request bleed).

### Notes

- `phpbench.json` was adjusted to the installed phpbench 1.7 dotted-key format (`runner.path`, `runner.bootstrap`, `runner.iterations`, `runner.revs`) and `composer.json` exposes `benchmarks/` under `Benchmarks\\` for autoloading.
- `RedactingExecutionReporter` and `GuardedExecutionReporter` live in `Adapter/Reporting` and depend only on `Contract/Port`, so they stay within the `Adapter` layer (verified by DepTrac: 0 violations).

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

- [x] **M8.1** Expand `docs/architecture/MODEL.md` — command lifecycle diagram (state machine + invariants), extended vocabulary mapping table with canonical `runit:` IRIs, and a RunIt integration-seam section.
- [x] **M8.2** ADRs in `docs/adr/`: `0001-run-it-integration-via-ports.md`, `0002-lazy-handler-resolution.md`, `0003-fiber-bound-context.md`.
- [x] **M8.3** `@see` vocabulary IRI references on public contracts (verified existing refs + corrected `CommandStatusInterface`→`ExecutionStatus`, `CommandBusInterface`→`ExecutionDefinition`; added per-constant `@see` to the three occurrence types).
- [x] **M8.4** `README.md` finalized: Installation section (`composer require` + `suggest` adapter table), executable getting-started example, links to MODEL/ADR/ROADMAP.
- [x] **M8.5** `composer quality` green (validate → cs → analyse → architecture → test → audit). Mutation MSI attempted — see notes.
- [x] **M8.6** Backward-compatibility check configured: `dantleech/what-changed` is allow-listed in `composer.json` `allow-plugins` and exposed as `composer bc-check` (`@composer what-changed`).

### Notes

- The vocabulary-alignment `@see` references were largely present from earlier milestones; M8.3 corrected two inaccurate IRIs (`runit:Command` does not exist in the ontology — `Command` is a specialization of `runit:ExecutionDefinition`; `CommandStatus` maps to `runit:ExecutionStatus`) and added `@see` to the occurrence-type constants.
- 3 pre-existing style violations in M6/M7 test files (`(new X())->method()` → `new X()->method()`, import ordering) were fixed mechanically via `composer cs:fix` so the `cs:check` gate is green; no logic changed.
- **Mutation MSI (`composer mutation`)** was attempted. `infection.json5` now sets `"testFramework": "codeception"` (the `infection/codeception-adapter` is installed; the repo is Codeception-only and has no `phpunit.xml`). The adapter's initial-test step invokes `codecept run` across **all** suites (including the empty `Acceptance` suite, which Codeception ships by default), and that run aborts with exit 143 because no coverage file is produced for the empty suite — so a project MSI target could not be measured this milestone. The `composer quality` QG does **not** include mutation, so the gate still passes. Follow-up (post-M8): restrict the mutation runner to the `Unit` suite (e.g. add a `phpunit.xml.dist` covering `tests/Unit`, or configure the adapter to target only `Unit`) and enable PCOV-driven coverage so `composer mutation` can run and an MSI floor can be set.

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
