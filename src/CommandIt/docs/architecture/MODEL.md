# CommandIt — Domain & Architecture Model

> This document is the canonical reference for CommandIt's domain artifacts and
> their mapping to the shared It_Pack execution model (`docs/EXECUTION.md`).
> It is filled in progressively as milestones land.

## Status

- **Milestone 0 (skeleton & quality gates)**: shipped.
- **Milestone 1 (value objects & behaviors)**: shipped.
- **Milestone 2 (domain model)**: shipped.
- **Milestone 3 (ports + default adapters)**: shipped — all external-effect contracts and RunIt-agnostic defaults in place (clock, ids, serializer, reporter, context storage, execution storage, runtime detector, lazy handler proxy).
- **Milestone 4 (application core / orchestration)**: shipped — the command bus + executor run the lifecycle (validate → received → executing → terminal), resolve handlers lazily, compose middleware, record occurrences via `CommandExecutionCoordinator`, and rethrow the original business exception on handler failure. `CommandExecutor` implements `CommandBusInterface` + `CommandExecutorInterface`.
- **Milestone 5 (SDK — ergonomic definition & dispatch)**: shipped — `CommandDefinitionBuilder` (immutable) + `CommandBuilder` produce validated definitions/commands; `CommandInputParser` (core) and `ArrayInputAdapter` (adapter) validate + coerce input via a shared `InputCoercionBehavior`; the `CommandIt` facade assembles the core with caller-supplied ports for terse, framework-free dispatch.
- **CLI adapter (M6)**: shipped — Symfony Console projection under `src/Adapter/CLI` (ArgvInputParser, ConsoleCommandBridge, ConsoleCommandRegistry); core imports zero Symfony types (DepTrac-verified).
- **Cross-cutting security/perf/self-instrumentation (M7)**: shipped — `RedactionPolicy` + `RedactingExecutionReporter`, `SafeSerializer` object-serialization prohibition, phpbench dispatch benchmarks, `GuardedExecutionReporter` recursion guard, bounded `CollectingExecutionReporter` overflow policy, persistent-worker isolation tests.
- **Documentation, ADR & release hardening (M8)**: shipped — lifecycle diagram, vocabulary mapping with canonical `runit:` IRIs, RunIt integration seam; ADRs in `docs/adr/` (`0001` RunIt-via-ports, `0002` lazy handler resolution, `0003` fiber-bound context); `@see` vocabulary alignment on public contracts; finalized `README.md` (install + executable examples); `composer bc-check` (what-changed) configured.

## Command lifecycle

A command is an immutable intent. Dispatch runs the canonical lifecycle; every transition is
enforced by `CommandExecutionCoordinator` and the `CommandExecutor`, and produces exactly one
terminal outcome.

```text
DEFINED
   │  (validated: name + required input present)
   ▼
RECEIVED            ── occurrence: CommandReceived   (reported by coordinator)
   │
   ▼
EXECUTING           ── activity begins; duration_ms measurement starts (monotonic clock)
   ├──► SUCCEEDED   ── occurrence: CommandHandled; snapshot: CommandResult (carries CommandExecution)
   ├──► FAILED      ── occurrence: CommandFailed; original Throwable preserved
   └──► CANCELLED   ── (optional, via Cancellation port)
```

Invariants enforced by the core (see `AGENTS.md`):

- A `Command` maps to exactly one `CommandExecution` (`CommandExecution::define()` per definition).
- Terminal executions (`SUCCEEDED`/`FAILED`/`CANCELLED`) cannot re-transition; `complete()`/`transition()` throw `CommandExecutionException`.
- On a handler `Throwable`: failure is recorded safely, the **original** exception is rethrown, and
  the prior context is restored in `finally` (reporter/adapter failures never replace it).
- Context is stored per-Fiber via a `WeakMap` and never in process-global mutable state.

## Layering (DepTrac)

```text
Domain  → Contract, Common
Application (Core) → Domain, Contract, Port, Common
SDK → Application, Domain, Contract, Port, Common
Adapter → Domain, Contract, Port, Common
```

External adapters (Symfony Console, RunIt) are **forbidden** from every internal
layer. CommandIt must remain RunIt-agnostic; RunIt integration arrives later via
ports (`src/Contract/Port`) only.

## Vocabulary mapping

CommandIt is a specialization of the shared It_Pack execution model (`docs/EXECUTION.md`). The
canonical vocabulary is the RunIt execution ontology
(`https://w3id.org/itpack/runit#`, sources in `../src/RunIt/vocab/run-it.ttl`). Public contracts
carry `@see` references to the corresponding term IRI (see `M8.3`).

| Execution model | CommandIt artifact | Canonical vocabulary IRI |
|---|---|---|
| Command (intent) | `Command` (port-dispatched via `CommandBusInterface`) | `runit:ExecutionDefinition` |
| Handler | `CommandHandlerInterface` (resolved lazily by `HandlerResolver`) | `runit:Activity` (handler work) |
| Execution | `CommandExecution` (orchestrated by `CommandExecutor` + `CommandExecutionCoordinator`) | `runit:Execution` |
| Execution status | `CommandStatus` enum | `runit:ExecutionStatus` |
| Context | `CommandContext` (port-provided, fiber-bound) | `runit:ExecutionContext` |
| Activity | handler work (one per execution; nested activities future) | `runit:Activity` |
| Measurement | `duration_ms` (monotonic clock, reported by coordinator) | `runit:Measurement` |
| Observation | reporter observation | `runit:Observation` |
| Occurrence | `CommandReceived`, `CommandHandled`, `CommandFailed` | `runit:Occurrence` |
| Snapshot | `CommandResult` (carries the `CommandExecution`) | `runit:Snapshot` |

Occurrence-type individuals are stable, lowercase, and versioned independently of PHP class names
(`docs/adr/0001-run-it-integration-via-ports.md`). They are projected onto PSR-14 / CloudEvents by
adapters; CommandIt never depends on those standards.

See `docs/EXECUTION.md` for the governing principles and `docs/adr/` for the load-bearing decisions.

## RunIt integration seam

CommandIt is **RunIt-agnostic** by design. No core/sdk source imports `ItPack\RunIt` (DepTrac
verified: 0 violations). Integration arrives exclusively through `src/Contract/Port`:

```text
ItPack\CommandIt (core + sdk)
        │  public API: CommandBusInterface, CommandExecutorInterface
        ▼
Contract/Port  ── ExecutionReporterInterface, ExecutionContextProviderInterface,
                │  CommandExecutionStorageInterface, RuntimeContextDetectorInterface,
                │  ClockInterface, IdentifierGeneratorInterface, SerializerInterface
                ▼
[ future ] it-pack/command-it-run-it   ── implements the ports, feeds RunIt's pipeline
```

- The `CommandExecution` shape is intentionally RunIt-like (id, status, started/ended, result,
  occurrences) but self-contained.
- Serialized formats are not altered to chase a guessed RunIt API; any mismatch is absorbed in the
  adapter (`docs/adr/0001-run-it-integration-via-ports.md`).
- `ExecutionArtifactInterface` is the minimal RunIt-alignment marker; it can extend a normalization
  contract when RunIt serialization lands.


## SDK usage (Milestone 5)

Defining and dispatching a command is framework-free; the `CommandIt` facade is
assembled with the caller's chosen ports (no global container):

```php
<?php

declare(strict_types=1);

use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\SDK\CommandIt;

$commandIt = new CommandIt(
    new SystemClock(),
    new RandomIdentifierGenerator(),
    new CollectingExecutionReporter(),
    new AmbientContextStorage(),
    new InMemoryCommandExecutionStorage(),
);

$definition = (new CommandDefinitionBuilder())
    ->name('cache:clear')
    ->argument(new Argument(ArgumentName::fromString('count'), required: true, type: 'int'))
    ->handler('cache.clear')
    ->build();

$commandIt->register($definition, static fn () => new class () implements \ItPack\CommandIt\Contract\Command\CommandHandlerInterface {
    public function handles(): string
    {
        return 'cache:clear';
    }

    public function handle(\ItPack\CommandIt\Contract\Command\CommandInterface $command): \ItPack\CommandIt\Contract\Command\CommandResultInterface
    {
        return \ItPack\CommandIt\Domain\CommandResult::succeed($command->input()->argument('count'));
    }
});

$input = (new ArrayInputAdapter())->parse($definition, ['count' => '5']);
$command = (new CommandBuilder())->definition($definition)->input($input)->build();

$result = $commandIt->dispatch($command);
```

The builder yields an immutable `readonly` definition; `build()` throws if a name
is missing. Input values are validated (required args/options) and coerced by
`type()` (`int`, `float`, `bool`, `string`) before reaching the handler.

