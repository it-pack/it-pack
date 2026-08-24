# CommandIt — Implementation Plan

> Multi-purpose command creation and execution library for PHP.
> Commands are executable programmatically (CQRS command bus) or via CLI (Symfony Console and other adapters).

This document records the reasoning and the concrete implementation plan for **CommandIt**. It is the
authoritative blueprint for the package and must be read together with `AGENTS.md` (adapted for this
project) and `docs/EXECUTION.md` (the shared It_Pack execution model).

---

## 1. Goal and constraints

### 1.1 What CommandIt is

- A library for **defining** commands (intent + input schema + handler) and **executing** them.
- Two first-class execution surfaces:
  1. **Programmatic** — a command bus / executor (CQRS write side).
  2. **CLI** — adapters that project commands onto Symfony Console (and potentially other CLIs).
- Vendor-neutral, framework-neutral, and explicit, like the rest of the It_Pack family.

### 1.2 Hard constraints

1. **Respect the It_Pack execution model** (`docs/EXECUTION.md`): every command run is an *Execution*
   carrying *Context*, producing *Activities*, *Observations*, *Occurrences*, *Measurements*, and a
   *Snapshot* (the result). External standards (Symfony Console, PSR-3, OpenTelemetry, CloudEvents) are
   adapters, never the domain.
2. **Open for integration with RunIt later**, but **must not depend on RunIt now** — RunIt is WiP.
   CommandIt therefore ships a *self-contained, minimal* execution abstraction and a *bridge port* that
   a future `it-pack/command-it-run-it` (or RunIt itself) can implement to feed RunIt's pipeline.
3. **Follow the family conventions** (see §2) — `final readonly` classes, interfaces at boundaries,
   `Behavior` traits, `Contract`/`Core`/`Adapter`/`SDK`/`ValueObject` layering.
4. **Apply modern PHP (>= 8.4) judiciously**: property hooks, lazy objects, `WeakMap`/`WeakReference`,
   Fibers, traits — only where they improve clarity, isolation, or performance (per `AGENTS.md`).
5. **SOLID + DRY + hexagonal (ports & adapters)**.

---

## 2. Architectural overview

### 2.1 Layers and dependency direction

```
SDK (convenience facades / builders)
        │
        ▼
Application (Core orchestration: CommandExecutor, Registry, Middleware)
        │
        ▼
Domain (Command, CommandResult, CommandInput, value objects)  ──► Contracts
        ▲                                                          │
        │                                                          │
Adapters (Symfony Console, CLI argv, Lazy, Fiber context, Reporters)
        │
        ▼
Ports (external effects: clock, ids, execution reporter, context storage, runtime detector)
```

Allowed dependency direction (same as RunIt):

- **Domain** → `Contract` only (no app, SDK, adapters, external libs).
- **Core/Application** → `Contract`, `Domain`, `Behavior`, `ValueObject`.
- **SDK** → `Contract`, `Domain`, `Core`, `ValueObject`.
- **Adapter** → `Contract`, `Domain`, `Ports`, and allowed external libs (e.g. symfony/console, optional).
- **Ports** are interfaces; concrete effect implementations live in adapters or SDK defaults.

`deptrac.yaml` will encode these layers exactly as RunIt does.

### 2.2 Vocabulary mapping (CommandIt ⇄ Execution Model)

| Execution Model | CommandIt concept | Notes |
|---|---|---|
| Execution | `CommandExecution` (Domain) | Self-contained, RunIt-shaped record. |
| Context | `CommandContext` via `ExecutionContextProviderInterface` | Ambient/CLI/env/tenant; fiber-bound. |
| Activity | handler work, optionally nested sub-steps | Reported through the reporter port. |
| Observation | logs/notices during handling | `ExecutionReporterInterface::reportObservation()`. |
| Occurrence | `CommandReceived`, `CommandHandled`, `CommandFailed` | Immutable facts via reporter port. |
| Measurement | duration, handler count | `reportMeasurement()`. |
| Snapshot | `CommandResult` | Immutable result/state after execution. |

The reporter and context ports are the **integration seam** with RunIt (see §4).

---

## 3. Domain model

### 3.1 Core concepts

- **Command** (`CommandInterface`) — immutable intent value object carrying validated input. Built by
  consumers or via the SDK `Command` value object + builder. A command *is* an execution trigger.
- **CommandDefinition** (`CommandDefinitionInterface`) — metadata: name, description, arguments,
  options, and a handler reference/resolver key. Used by programmatic bus *and* CLI projection.
- **CommandInput** (`CommandInputInterface`) — immutable resolved input (arguments + options) built by a
  parser/adapter before dispatch.
- **Argument / Option** — `InputKind` enum (argument | option) distinguishes them; names are validated
  string value objects, not enums (per `AGENTS.md`: user-defined names stay extensible).
- **CommandHandler** (`CommandHandlerInterface`) — `handle(CommandInterface): CommandResultInterface`.
  Implemented by consumers; resolved lazily by the bus.
- **CommandResult** (`CommandResultInterface`) — `CommandResultStatus` (succeeded | failed), optional
  payload, and an optional attached `CommandExecution` snapshot.
- **CommandBus** (`CommandBusInterface`) — programmatic dispatch: `dispatch(CommandInterface): CommandResultInterface`.
- **CommandRegistry** (`CommandRegistryInterface`) — maps command name/class → definition + handler
  resolver. Enables discovery and CLI listing.
- **CommandMiddleware** (`CommandMiddlewareInterface`) — wraps dispatch (`process(CommandInterface, Next): CommandResultInterface`).
- **CommandExecutor** (`CommandExecutorInterface`, application service) — orchestrates the lifecycle.

### 3.2 State machines

**Command lifecycle** (mirrors RunIt's execution lifecycle):

```
DEFINED
   │  (validated)
   ▼
RECEIVED            ──► occurrence: CommandReceived
   │
   ▼
EXECUTING           ──► activity begins, measurement starts
   ├──► SUCCEEDED   ──► occurrence: CommandHandled, snapshot: CommandResult
   ├──► FAILED      ──► occurrence: CommandFailed, original Throwable preserved
   └──► CANCELLED   ──► (optional, via Cancellation port)
```

Transitions are explicit, validated, idempotent where documented, and safe during exceptions. A
terminal command cannot transition again. On handler `Throwable`: record failure, complete execution as
failed, restore prior context in `finally`, rethrow the **original** `Throwable` (never the reporter's).

### 3.3 Closed vs extensible

- **Enums (closed)**: `CommandStatus`, `CommandResultStatus`, `InputKind`.
- **Validated strings (extensible)**: `CommandName`, `ArgumentName`, `OptionName`, any user command name.

---

## 4. RunIt integration strategy (the key design decision)

CommandIt **must not depend on RunIt** (WiP) yet **must be open for integration**. Decision:

1. CommandIt defines a **minimal, self-contained execution record** `CommandExecution` (Domain) that
   deliberately mirrors RunIt's `Execution` shape (id, definition, status, startedAt/endedAt, result,
   context ref). It is fully usable standalone.
2. External effects are behind **ports**:
   - `ExecutionReporterInterface` — `reportObservation`, `reportOccurrence`, `reportMeasurement`,
     `beginActivity`, `endActivity`.
   - `ExecutionContextProviderInterface` — ambient context for the current execution.
   - `CommandExecutionStorageInterface` — persist/retain the execution record.
   - `RuntimeContextDetectorInterface` — detects Fiber / coroutine runtime.
   - `IdentifierGeneratorInterface`, `ClockInterface`, `SerializerInterface`.
3. The SDK ships **default adapters**: `NullExecutionReporter` (no-op), `CollectingExecutionReporter`
   (in-memory for tests/CLI), `AmbientContextStorage` / `FiberBoundContextStorage`.
4. **Later**, an `it-pack/command-it-run-it` package (or RunIt itself) provides implementations of these
   ports that translate `CommandExecution`, observations, and occurrences into RunIt's model. Because
   the vocabulary and shapes already align (§2.2), this is an adapter, not a rewrite.

This satisfies "respect the model now, integrate later" without coupling to a moving target.

---

## 5. Modern PHP feature application (rationale)

Each feature is used only where it earns its place.

### 5.1 Property hooks (PHP 8.4+) — computed & validated projections

- `CommandDefinition` exposes a **virtual `signature` property** via a `get` hook, computed from name +
  arguments + options (e.g. `user:create {email} {--notify}`). No stored duplicate state.
- Value objects `CommandName` / `ArgumentName` / `OptionName` use a **`set` hook** to normalize
  (trim, lower, validate format) at construction, and a `get` hook to expose the canonical form.
- `CommandInput` values can use `set` hooks for per-value coercion (int/bool/string) without a separate
  normalization pass.
- Rationale: removes boilerplate getters, keeps a single source of truth, aligns with `AGENTS.md`
  guidance to back value objects with property hooks.

### 5.2 Lazy objects (PHP 8.4 `newLazyGhost` / `newLazyProxy`) — deferred handler cost

- `Adapter/Lazy/LazyHandlerProxy` wraps a handler **factory** with `GhostObject` initialization. The
  handler class is instantiated only on the first `handle()` call, inside the executor, after validation
  and context setup. Avoids constructing expensive handlers for rejected/invalid commands.
- The lazy resolver is behind `HandlerResolverInterface` so consumers can supply their own (DI-aware)
  strategy; the default is the ghost proxy.
- Optional reuse: `it-pack/laze-it` is a sibling utility for lazy objects; we *suggest* it but keep
  CommandIt's internal resolver self-contained to avoid a premature hard dependency.
- Rationale: performance on hot dispatch paths; DRY through a single resolver port.

### 5.3 WeakMap / WeakReference (PHP 8.1+) — non-owning associations

- `FiberBoundContextStorage` keys a `WeakMap<Fiber, CommandContext>` so each coroutine/Fiber carries its
  own execution context without global mutable state and without preventing Fiber GC (mirrors OpenTelemetry's
  `FiberBoundContextStorage`).
- An ambient `WeakMap<CommandInterface, CommandExecution>` lets the executor attach metadata to a command
  instance for the duration of a run **without retaining the command** or mutating the immutable object.
- Rationale: fiber/coroutine isolation without leaks; no hidden global state (per `AGENTS.md` security
  rules).

### 5.4 Fibers (PHP 8.1+) — runtime-neutral context isolation

- The context provider port is **Fiber-aware**: within a Fiber (Swoole/OpenSwoole coroutine, or user
  Fiber), context is read/written per-Fiber via the `WeakMap` above; in a non-Fiber runtime it falls back
  to a request-local scope.
- Optional (behind a port): a handler may suspend/resume through a Fiber to allow middleware hooks at
  suspension points. This is *not* required for v1 and stays behind `RuntimeContextDetectorInterface`.
- Rationale: prepares for async runtimes and persistent workers (Swoole) without coupling domain to them.

### 5.5 Traits / Behavior (DRY for cross-cutting domain rules)

`Behavior/Domain/*` stateless traits, mirroring RunIt's `Behavior` namespace:

- `ValidatedStringValueBehavior` — shared normalization/validation for string value objects.
- `NamedConstructorBehavior` — `from*` named constructors.
- `TimestampedBehavior` — projects `createdAt` onto `Countable`/timing.
- `ExecutionOwnedBehavior` — links an artifact to its `CommandExecution` id.
- `CommandNamedBehavior` — exposes `commandName()` projection.

Traits are **stateless** and never hold hidden dependencies (per `AGENTS.md`).

### 5.6 readonly + immutability

All domain artifacts (`Command`, `CommandResult`, `CommandInput`, value objects, `CommandExecution`)
are `final readonly`. Mutations produce copies (e.g. `CommandExecution::complete()`). Input parsing
returns a new immutable `CommandInput`.

---

## 6. Proposed file tree

```
src/
  Behavior/Domain/
    ValidatedStringValueBehavior.php
    NamedConstructorBehavior.php
    TimestampedBehavior.php
    ExecutionOwnedBehavior.php
    CommandNamedBehavior.php
  Contract/
    Command/
      CommandInterface.php
      CommandHandlerInterface.php
      CommandBusInterface.php
      CommandResultInterface.php
      CommandDefinitionInterface.php
      CommandInputInterface.php
      ArgumentInterface.php
      OptionInterface.php
      CommandRegistryInterface.php
      CommandMiddlewareInterface.php
    Application/
      CommandExecutorInterface.php
      HandlerResolverInterface.php
      CommandExecutionCoordinatorInterface.php
    Port/
      ExecutionReporterInterface.php
      ExecutionContextProviderInterface.php
      CommandExecutionStorageInterface.php
      RuntimeContextDetectorInterface.php
      IdentifierGeneratorInterface.php
      ClockInterface.php
      SerializerInterface.php
    Exception/
      CommandExceptionInterface.php
      InvalidCommandException.php
      HandlerNotFoundException.php
      CommandExecutionException.php
  Domain/
    Command.php
    CommandResult.php
    CommandInput.php
    Argument.php
    Option.php
    CommandExecution.php
    CommandStatus.php                 # enum
    CommandResultStatus.php           # enum
    InputKind.php                     # enum
  ValueObject/
    CommandName.php
    ArgumentName.php
    OptionName.php
    ValidatedString.php
  Core/
    CommandExecutor.php
    CommandRegistry.php
    HandlerResolver.php
    CommandDefinition.php
    CommandInputParser.php
    CommandExecutionCoordinator.php
    MiddlewareChain.php
  SDK/
    CommandDefinitionBuilder.php
    CommandBuilder.php
    CommandIt.php                    # facade
  Adapter/
    SymfonyConsole/
      ConsoleCommandAdapter.php
      ConsoleApplicationBridge.php
    Cli/
      ArgvInputParser.php
      ArrayInputAdapter.php
    Lazy/
      LazyHandlerProxy.php
    Context/
      FiberBoundContextStorage.php
      AmbientContextStorage.php
    Reporting/
      NullExecutionReporter.php
      CollectingExecutionReporter.php
  Exception/
    InvalidCommandException.php
    HandlerNotFoundException.php
    CommandExecutionException.php
docs/
  EXECUTION.md
  IMPLEMENTATION_PLAN.md             # this file
tests/
  Unit/  Functional/  Integration/
deptrac.yaml
phpstan.neon.dist
php-cs-fixer.dist.php
infection.json5
composer.json                       # updated: php ^8.4, scripts, suggest symfony/console
```

---

## 7. Key interface skeletons

```php
// Contract/Command/CommandBusInterface.php
interface CommandBusInterface
{
    public function dispatch(CommandInterface $command): CommandResultInterface;
}

// Contract/Command/CommandHandlerInterface.php
interface CommandHandlerInterface
{
    public function handles(): string; // command name or FQCN
    public function handle(CommandInterface $command): CommandResultInterface;
}

// Contract/Application/HandlerResolverInterface.php
interface HandlerResolverInterface
{
    /** Resolves (lazily) the handler for a command; throws HandlerNotFoundException. */
    public function resolve(CommandInterface $command): CommandHandlerInterface;
}

// Contract/Port/ExecutionReporterInterface.php
interface ExecutionReporterInterface
{
    public function reportObservation(CommandExecution $execution, Observation $o): void;
    public function reportOccurrence(CommandExecution $execution, Occurrence $e): void;
    public function reportMeasurement(CommandExecution $execution, Measurement $m): void;
    public function beginActivity(CommandExecution $execution, Activity $a): void;
    public function endActivity(CommandExecution $execution, Activity $a): void;
}

// Contract/Port/RuntimeContextDetectorInterface.php
interface RuntimeContextDetectorInterface
{
    public function currentFiber(): ?Fiber;
    public function isCoroutineRuntime(): bool;
}
```

```php
// Domain/CommandExecution.php  (self-contained, RunIt-shaped)
final readonly class CommandExecution implements ExecutionArtifactInterface
{
    public function __construct(
        public ExecutionIdInterface $id,
        public CommandDefinitionInterface $definition,
        public CommandStatus $status,
        public InstantInterface $startedAt,
        public ?InstantInterface $endedAt = null,
        public ?CommandResultInterface $result = null,
    ) {}

    public function complete(CommandResultInterface $result, InstantInterface $endedAt): self
    { /* returns copy with terminal status */ }
}
```

```php
// SDK/CommandDefinitionBuilder.php (convenience, fluent, mirrors ConfigureIt builder)
final class CommandDefinitionBuilder
{
    public function name(string $name): self
    public function description(string $description): self
    public function argument(string $name, string $type = 'string'): self
    public function option(string $name, bool $required = false): self
    public function handler(string|callable|CommandHandlerInterface $handler): self
    public function build(): CommandDefinitionInterface
}
```

---

## 8. CLI adapter design

- `Adapter/SymfonyConsole/ConsoleCommandAdapter` maps a `CommandDefinition` to a Symfony
  `Command` subclass: arguments/options become input definitions; `execute()` dispatches through the
  `CommandBus` and writes the `CommandResult` to the `OutputInterface`. Symfony Console is an **optional**
  `suggest` dependency; the core never imports it.
- `Adapter/Cli/ArgvInputParser` parses `$argv` into a `CommandInput` for a given definition (validates
  required args/options, coerces types). `ArrayInputAdapter` does the same from an array (useful for
  tests and programmatic CLI simulation).
- `ConsoleApplicationBridge` registers many definitions into a Symfony `Application` and provides a
  `--list` of known commands from the `CommandRegistry`.

---

## 9. Security, performance, testing

- **Security**: context never exports secrets; observations/occurrences redact sensitive input keys (configurable redaction policy via the reporter port). No unbounded attribute growth; no global mutable context (Fiber-bound `WeakMap`).
- **Performance**: lazy handler construction; monotonic clock for duration; deferred normalization; no reflection on hot paths; bounded in-memory reporter.
- **Testing** (Codeception, mirroring RunIt/ConfigureIt):
  - Unit: command validation, value objects, input parsing, middleware chain order, lazy resolution, registry lookup, enum transitions.
  - Functional: full bus dispatch (success, handler throws → original exception preserved, invalid command rejected, nested activities).
  - Integration: Symfony Console adapter end-to-end (argv → result → output); Fiber context isolation (two Fibers, distinct contexts); persistent-worker leak check (no cross-request context bleed).
  - Mutation: state transitions, failure policy, redaction, resolver.
- **Quality gates**: add `composer` scripts — `test`, `analyse` (phpstan highest level), `architecture` (deptrac), `cs:check` (php-cs-fixer), `mutation` (infection), `security:audit`.

---

## 10. Implementation phases (incremental)

1. **Skeleton + conventions**: `composer.json` (php ^8.4, scripts, suggest), `deptrac.yaml`,
   `phpstan.neon.dist`, `php-cs-fixer.dist.php`, empty `tests/`.
2. **Domain + ValueObject + Behavior**: commands, results, input, enums, validated strings, traits.
3. **Ports + default adapters**: reporter (null/collecting), context storage (ambient + fiber-bound),
   clock/id/serializer ports, lazy handler proxy.
4. **Core orchestration**: registry, resolver, definition, input parser, executor, middleware chain,
   execution coordinator (lifecycle + original-exception preservation).
5. **SDK**: builders + facade.
6. **CLI adapter**: Symfony Console mapping + argv/array parsers.
7. **Tests + quality gates**: unit/functional/integration, deptrac, phpstan, infection.
8. **Docs**: update `README.md`, add `docs/architecture/MODEL.md`, keep `EXECUTION.md`, this plan.

---

## 11. Open questions / risks

- **RunIt contract drift**: the `CommandExecution` shape is a *guess* at RunIt's final API. Mitigation:
  keep it behind `Contract/Port` so the adapter absorbs any mismatch; do not reference RunIt types.
- **LazeIt reuse**: optional; defer hard dependency until LazeIt stabilizes.
- **Fiber suspension for handlers**: deferred to a later version; the detector port exists but v1 uses
  only context isolation, not handler suspension.
- **Symfony Console version**: pin to `^7.0` (or `^6.4`) as optional; keep adapter isolated.
