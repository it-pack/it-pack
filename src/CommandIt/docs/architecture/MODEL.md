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
- CLI adapter, cross-cutting, and release hardening follow in later milestones.

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

| Execution model | CommandIt artifact |
|---|---|
| Command | `Command` (port-dispatched via `CommandBusInterface`) |
| Handler | `CommandHandlerInterface` (resolved lazily by `HandlerResolver`) |
| Execution | `CommandExecution` (orchestrated by `CommandExecutor` + `CommandExecutionCoordinator`) |
| Context | `CommandContext` (port-provided, fiber-bound) |
| Activity | handler work (future) |
| Measurement | `duration_ms` (monotonic clock, reported by coordinator) |
| Observation | reporter observation |
| Occurrence | `CommandReceived`, `CommandHandled`, `CommandFailed` |
| Snapshot | `CommandResult` (carries the `CommandExecution`) |

See `docs/EXECUTION.md` for the governing principles.

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

