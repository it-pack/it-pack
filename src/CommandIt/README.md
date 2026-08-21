# CommandIt

## A multi purpose command creation and execution library for PHP
### Creates commands executable programmatically (e.g., CQRS) or in CLI (via SymfonyConsole, etc. adapters)

CommandIt models command execution itself: a validated intent (the `Command`) is
dispatched through a bus that runs an explicit lifecycle (`received → executing →
succeeded|failed`), records immutable artifacts (context, measurements,
occurrences) via ports, and never leaks context across Fibers or requests. Core
stays RunIt- and Symfony-free; adapters project it onto specific runtimes.

## Installation

```bash
composer require it-pack/command-it
```

CommandIt targets PHP `>= 8.4` and has **no runtime dependencies**. The following adapters are
optional and only needed when you project onto a specific runtime or standard — they are declared
under `suggest` and are never imported by core:

| Package | When you need it |
|---|---|
| `symfony/console` | CLI projection (`Adapter\CLI`) — map definitions to Symfony Console commands |
| `psr/log` | PSR-3 observation/logger integration |
| `psr/event-dispatcher` | PSR-14 occurrence integration |
| `psr/clock` | PSR-20 monotonic clock integration |

The in-box adapters (`SystemClock`, `RandomIdentifierGenerator`, `CollectingExecutionReporter`,
`AmbientContextStorage`, `InMemoryCommandExecutionStorage`, `SafeSerializer`) cover framework-free
programmatic dispatch, so no `suggest` package is required to run the examples below.

## Getting started

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

$result = $commandIt->dispatch($command); // CommandResult with status "succeeded", payload 5 (int)
```

- `CommandDefinitionBuilder` is immutable; `build()` returns a validated, `readonly` definition.
- Input is validated (required args/options) and coerced by declared `type()` (`int`, `float`, `bool`, `string`).
- On a handler failure the **original** exception is rethrown; the reporter/storage never replace it.

## CLI adapter (Symfony Console)

The CLI adapter projects registered definitions onto Symfony Console. `symfony/console`
is `suggest`-only — core never imports it; only `ItPack\CommandIt\Adapter\CLI` does.

```php
<?php

declare(strict_types=1);

use ItPack\CommandIt\Adapter\CLI\ConsoleCommandRegistry;
use Symfony\Component\Console\Application;

// $commandIt from the example above, after registering commands.
$app = new Application();
$app->setAutoExit(false);

foreach (
    (new ConsoleCommandRegistry($commandIt->registry(), $commandIt->bus()))
        ->toConsoleCommands() as $command
) {
    $app->addCommand($command);
}

$app->run(); // e.g. `php bin/console cache:clear 5 --force`
```

- `ConsoleCommandRegistry` enumerates every registered `CommandDefinitionInterface`
  into a `ConsoleCommandBridge` (one Symfony `Command` per definition).
- The bridge mirrors arguments/options into a Symfony `InputDefinition`; `bool`
  options become flags (`--force`), `int`/`float`/`string` values are coerced by the
  shared input-coercion behavior, so CLI input agrees exactly with the Array input
  adapter.
- `execute()` dispatches through the `CommandBusInterface` and writes the
  `CommandResult` payload to the output stream (JSON for arrays/objects). Handler
  failures are reported on the stream with a non-zero exit code; the original
  exception is preserved by the core executor.
- `ArgvInputParser` covers the raw `$argv` → `CommandInput` path (validation +
  coercion) when you build commands without a full Symfony `Application`.

## Security & isolation

- Execution context is restored in `finally` by the core executor, so the CLI is
  safe across repeated invocations in a persistent worker.

### Reporting decorators (`Adapter/Reporting`)

These wrap any `ExecutionReporterInterface` and stay within the `Adapter` layer.

- `RedactingExecutionReporter` applies a `RedactionPolicyInterface` to every
  observation/occurrence context before it reaches the delegate. The default
  `RedactionPolicy` redacts values whose key looks sensitive (`password`, `token`,
  `authorization`, …), truncates oversized strings, caps array cardinality, and
  limits nesting depth — so secrets and high-cardinality/oversized data never reach
  an external sink.
- `GuardedExecutionReporter` wraps a publication delegate with a **recursion guard**
  and a **separate health reporter**: if publishing triggers a nested report (a
  self-observation loop) or throws, the signal is routed to the health reporter
  instead of re-entering the delegate or bubbling into the business flow. Use it
  when the reporter publishes through a path that may itself report.

```php
use ItPack\CommandIt\Adapter\Common\RedactionPolicy;
use ItPack\CommandIt\Adapter\Reporting\GuardedExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\RedactingExecutionReporter;

$reporter = new RedactingExecutionReporter($sink, new RedactionPolicy());
$reporter = new GuardedExecutionReporter($reporter, $healthReporter);
```

- `CollectingExecutionReporter` keeps a bounded in-memory buffer. Configure the
  overflow behavior with `OverflowPolicy` (`DropOldest` default, `DropNewest`, or
  `Throw` → `BufferOverflowException`).

### Serialization

- `SafeSerializer` (the `SerializerInterface` default) never produces arbitrary
  object serialization: resources, closures, and objects without `toArray()`/`Stringable`
  are rejected with a `SerializationException`. Only recursively-primitive data is returned.

See `docs/architecture/MODEL.md` for the domain model, `docs/adr/` for the load-bearing
decisions (RunIt integration, lazy handler resolution, fiber-bound context), and `docs/ROADMAP.md`
for the milestone plan.
