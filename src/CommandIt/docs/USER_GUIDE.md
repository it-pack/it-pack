# CommandIt — User Guide

This guide is the practical, task-oriented companion to the architecture docs
(`docs/architecture/MODEL.md`), the load-bearing decisions (`docs/adr/`), and the
milestone plan (now in `docs/archive/ROADMAP.md`). It assumes PHP `>= 8.4` and a
basic familiarity with PHP, but no prior knowledge of CommandIt.

CommandIt is a **command creation and execution library**: you define commands
once and run them either programmatically (a CQRS-style command bus) or through
adapters such as Symfony Console. Every execution is an explicit, observable
lifecycle that records immutable artifacts and never leaks context between
Fibers or requests.

---

## Table of contents

1. [The mental model](#1-the-mental-model)
2. [Requirements & installation](#2-requirements--installation)
3. [Quick start](#3-quick-start)
4. [Defining commands](#4-defining-commands)
5. [Building & validating input](#5-building--validating-input)
6. [Writing handlers](#6-writing-handlers)
7. [Dispatching & the lifecycle](#7-dispatching--the-lifecycle)
8. [The command bus & middleware](#8-the-command-bus--middleware)
9. [Context, enrichment & isolation](#9-context-enrichment--isolation)
10. [Reporting & observability](#10-reporting--observability)
11. [Storage & serialization](#11-storage--serialization)
12. [Security & isolation guarantees](#12-security--isolation-guarantees)
13. [CLI adapter (Symfony Console)](#13-cli-adapter-symfony-console)
14. [Customizing the ports](#14-customizing-the-ports)
15. [Testing your commands](#15-testing-your-commands)
16. [Performance & benchmarks](#16-performance--benchmarks)
17. [Versioning & compatibility](#17-versioning--compatibility)
18. [Common pitfalls](#18-common-pitfalls)
19. [Where to go next](#19-where-to-go-next)

---

## 1. The mental model

CommandIt models **command execution itself**. A command is not "a function call"
or "a console command" — it is a validated *intent* that flows through a fixed
pipeline. The core vocabulary (mirrored 1:1 by the RunIt vocabulary at
`w3id.org/itpack/runit`) is:

| Concept | What it is | Where it lives |
|---|---|---|
| **Command** | An immutable intent: a name + resolved `CommandInput` + optional payload. | `Domain\Command` |
| **CommandExecution** | The record of one logical run: `received → executing → succeeded\|failed`. | `Domain\CommandExecution` |
| **CommandContext** | Immutable, classified key/value data attached to the current run. | `Domain\CommandContext` |
| **Activity** | A bounded unit of work (the handler, or sub-steps). | reporter `beginActivity`/`endActivity` |
| **Measurement** | A quantitative fact (`duration_ms`). | reporter `reportMeasurement` |
| **Observation** | A qualitative note (`reportObservation`). | reporter `reportObservation` |
| **Occurrence** | An immutable, versioned fact: `command.received.v1`, `command.handled.v1`, `command.failed.v1`. | reporter `reportOccurrence` |
| **CommandResult** | The immutable outcome (status + payload + snapshot). | `Domain\CommandResult` |

The lifecycle state machine:

```
RECEIVED            ──▶ occurrence: command.received.v1
   │
   ▼
EXECUTING           ──▶ activity begins (handler + middleware), measurement starts
   ├──▶ SUCCEEDED   ──▶ occurrence: command.handled.v1, snapshot: CommandResult
   ├──▶ FAILED      ──▶ occurrence: command.failed.v1, original Throwable preserved
```

`CommandStatus` also declares `Cancelled`, but **it is reserved** — not reachable
from the executor until a `CancellationPort` is introduced. Do not rely on it yet.

Two closed enums capture state: `CommandStatus` (the lifecycle) and
`CommandResultStatus` (`Succeeded` / `Failed`, on the result). User-defined *names*
(arguments, options, command names) are validated strings, not enums — they are
extensible on purpose.

**Architecture in one sentence:** `SDK` (ergonomics) → `Application` (core) →
`Domain` + `Contract`, with `Adapter`s projecting onto specific runtimes
(Symfony Console, PSR-3, PSR-14, clocks, storage) through `Contract\Port`
interfaces. Core never imports an adapter or a framework.

---

## 2. Requirements & installation

```bash
composer require it-pack/command-it
```

- **PHP `>= 8.4`**, **no runtime dependencies**.
- The following are `suggest`-only (never imported by core); install when you
  project onto a runtime/standard:

  | Package | When you need it |
  |---|---|
  | `symfony/console` | CLI projection (`Adapter\CLI`) |
  | `psr/log` | PSR-3 observation/logger integration |
  | `psr/event-dispatcher` | PSR-14 occurrence integration |
  | `psr/clock` | PSR-20 monotonic clock integration |

- The in-box adapters (`SystemClock`, `RandomIdentifierGenerator`,
  `CollectingExecutionReporter`, `AmbientContextStorage`, `FiberBoundContextStorage`,
  `InMemoryCommandExecutionStorage`, `NullCommandExecutionStorage`, `SafeSerializer`,
  `RedactionPolicy`) cover framework-free programmatic dispatch, so no `suggest`
  package is required to run the examples below.

---

## 3. Quick start

The fastest, safe-by-default path is the composition-root factory. It wires a
guarded + redacting reporter, a fiber-aware (or ambient) context, and a no-op
storage for you:

```php
<?php

declare(strict_types=1);

use ItPack\CommandIt\Bootstrap\CommandItFactory;

$commandIt = CommandItFactory::create();
```

`CommandItFactory::create()` accepts any port as a nullable override and falls
back to in-box defaults:

```php
CommandItFactory::create(
    clock:     null,                              // → SystemClock
    ids:       null,                              // → RandomIdentifierGenerator
    reporter:  null,                              // → SafeReporterFactory::wrap(CollectingExecutionReporter)
    context:   null,                              // → FiberBoundContextStorage(FiberRuntimeDetector, AmbientContextStorage)
    storage:   null,                              // → NullCommandExecutionStorage
    serializer: null,                            // → payload kept verbatim
    middlewares: [],                             // → no middleware
);
```

A complete, runnable first command:

```php
<?php

declare(strict_types=1);

use ItPack\CommandIt\Bootstrap\CommandItFactory;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

$ci = CommandItFactory::create();

$definition = (new CommandDefinitionBuilder())
    ->name('greet')
    ->description('Greet a person by name')
    ->argument(new Argument(ArgumentName::fromString('name'), required: true, type: 'string'))
    ->build();

$ci->register($definition, static fn () => new class () implements \ItPack\CommandIt\Contract\Command\CommandHandlerInterface {
    public function handles(): string
    {
        return 'greet';
    }

    public function handle(\ItPack\CommandIt\Contract\Command\CommandInterface $command): \ItPack\CommandIt\Contract\Command\CommandResultInterface
    {
        $name = $command->input()->argument('name');

        return CommandResult::succeed("Hello, {$name}!");
    }
});

$input = (new ArrayInputAdapter())->parse($definition, ['name' => 'Ada']);
$command = (new CommandBuilder())->definition($definition)->input($input)->build();

$result = $ci->dispatch($command);

// $result->status() === CommandResultStatus::Succeeded
// $result->payload() === "Hello, Ada!"
```

That is the whole happy path. The rest of this guide expands each step and shows
how to observe, secure, and project it.

---

## 4. Defining commands

A **definition** is the immutable blueprint of a command: its name, description,
arguments, and options. Use the fluent, immutable `CommandDefinitionBuilder`:

```php
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\Option;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\Domain\ValueObject\OptionName;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;

$definition = (new CommandDefinitionBuilder())
    ->name('cache:clear')
    ->description('Clear a cache pool')
    ->argument(new Argument(ArgumentName::fromString('pool'), required: true, type: 'string'))
    ->option(new Option(OptionName::fromString('force'), required: false, type: 'bool', defaultValue: false))
    ->option(new Option(OptionName::fromString('timeout'), required: false, type: 'int', defaultValue: 30))
    ->build();
```

Rules:

- `name()` accepts a `CommandNameInterface` or a `string`; names are validated
  strings (e.g. `cache:clear`). Unknown command names throw on dispatch.
- `Argument` and `Option` are `readonly`. Both take `type: 'int'|'float'|'bool'|'string'`
  (default `string`), `required` (default `false`), `defaultValue`, and `description`.
- `build()` returns a validated, `readonly` `CommandDefinitionInterface`. Calling
  `build()` without a name throws `InvalidCommandException`.
- `CommandDefinition::signature()` returns a computed usage string, e.g.
  `cache:clear <pool> [--force] [--timeout]`. Useful for CLI help.

Arguments are positional; options are `--name`. The same coercion rules apply
everywhere (programmatic input, array input, and the CLI adapter), so a value
validated once behaves identically across runtimes.

---

## 5. Building & validating input

Input is **resolved and type-coerced before** it reaches a handler, so handlers
receive clean, typed values. Three adapters produce an immutable `CommandInput`:

### `ArrayInputAdapter` — flat associative array (programmatic)

```php
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;

$input = (new ArrayInputAdapter())->parse($definition, [
    'pool'    => 'redis',
    'force'   => '1',          // string '1' → bool true
    'timeout' => '60',         // string '60' → int 60
]);
```

Keys are routed to arguments or options by name; an unknown key is rejected with
`InvalidCommandException`. Required arguments/options missing (and without a
`defaultValue`) throw on parse.

### `CommandInputParser` — separated arrays (core)

When you already have `$arguments` and `$options` as distinct arrays (e.g. from
your own router):

```php
use ItPack\CommandIt\Application\CommandInputParser;

$input = (new CommandInputParser())->parse(
    $definition,
    ['pool' => 'redis'],
    ['force' => true, 'timeout' => 60],
);
```

### `ArgvInputParser` — raw `$argv` tokens (CLI)

```php
use ItPack\CommandIt\Adapter\CLI\ArgvInputParser;

// tokens after the script name and command name:
$input = (new ArgvInputParser())->parse($definition, ['redis', '--force', '--timeout=60']);
```

Positional tokens fill arguments in declaration order; `--name`, `--name=value`,
and `--name value` fill options. `bool` options become flags.

Token grammar:

- Long options only (`--force`, not `-f` — short flags are rejected loudly).
- A following token that looks like a negative number (`--limit -5`) is accepted
  as the option value; `--limit=-5` works too.
- `--` terminates option parsing; every later token is positional.
- Negative numbers are valid positional arguments.
- Unknown options (`--forc`) and surplus positionals throw
  `InvalidCommandException`.

### Reading input in a handler

`CommandInput` is immutable and already coerced:

```php
$pool    = $command->input()->argument('pool');     // string
$force   = $command->input()->option('force');      // bool
$timeout = $command->input()->option('timeout');    // int
```

`argument()`/`option()` throw `InvalidCommandException` if the key is absent
(use `hasArgument()`/`hasOption()` first if optional without a default).

### Building the `Command`

```php
use ItPack\CommandIt\SDK\CommandBuilder;

$command = (new CommandBuilder())
    ->definition($definition)
    ->input($input)
    // ->payload($somePriorPayload)   // optional, carried through unchanged
    ->build();
```

`build()` returns an immutable `CommandInterface` whose name always matches the
definition.

---

## 6. Writing handlers

A handler implements `CommandHandlerInterface`:

```php
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Domain\CommandResult;

final class ClearCacheHandler implements CommandHandlerInterface
{
    public function handles(): string
    {
        return 'cache:clear';
    }

    public function handle(CommandInterface $command): CommandResultInterface
    {
        $pool = $command->input()->argument('pool');

        // …do the work…

        return CommandResult::succeed(['cleared' => $pool]);
    }
}
```

Return `CommandResult::succeed($payload)` or `CommandResult::fail($payload)`.
The payload may be any value; if you pass a serializer to `CommandIt`/`CommandItFactory`,
it is normalized before storage (best-effort — see [§11](#11-storage--serialization)).

### Registering

```php
$ci->register($definition, static fn (): CommandHandlerInterface => new ClearCacheHandler());
```

Registering the same command name twice throws `InvalidCommandException` —
collisions fail fast instead of silently overwriting (ADR 0004).

Handlers are resolved **lazily** (`HandlerResolver` + `LazyHandlerProxy`): the
factory closure is stored, and the handler object is constructed only when the
command is actually dispatched — and exactly once. This keeps expensive
constructors off the hot path and out of registration time.

Returning a `CommandResult::fail(...)` is **not** the same as throwing. A failed
result is a normal, observed terminal outcome (`command.failed.v1` is *not*
emitted for a returned failure — that occurrence is reserved for thrown
exceptions). Throw when the work genuinely errored; return `fail()` when the
business result is "no-op / rejected".

---

## 7. Dispatching & the lifecycle

```php
$result = $ci->dispatch($command);
```

`dispatch()` runs the full pipeline and always returns a `CommandResultInterface`:

1. **Validate** the command against the registry. Unknown name → `HandlerNotFoundException`.
2. **Begin** the execution (`command.received.v1` occurrence), generate an id, store it.
3. **Resolve** the handler lazily; enter the context; run the middleware chain → handler.
4. On success: complete as `SUCCEEDED` (`command.handled.v1` + `duration_ms` measurement),
   normalize the payload, store, and return `result->withExecution($execution)`.
5. On throw: complete as `FAILED` (`command.failed.v1`, original error class + message,
   `duration_ms`), store, and **rethrow the original `Throwable`**.
6. **Finally**: leave the context (restoring the previous one).

Inspecting the result:

```php
use ItPack\CommandIt\Domain\CommandResultStatus;

$status  = $result->status();                 // CommandResultStatus (Succeeded/Failed)
$label   = $result->status()->label();        // 'succeeded' | 'failed'
$payload = $result->payload();                // whatever the handler returned
$exec    = $result->execution();              // CommandExecution artifact (snapshot)
$execId  = $exec?->id()->toString();          // the execution id
```

If a handler throws, `dispatch()` throws — **the original exception**, never a
reporter/storage error. Catch it in your calling code:

```php
try {
    $ci->dispatch($command);
} catch (SpecificDomainException $e) {
    // $e is exactly what the handler threw
}
```

---

## 8. The command bus & middleware

`CommandIt` *is* the bus (`CommandBusInterface`); `bus()` returns it, and
`dispatch()` is the entry point. Cross-cutting logic (logging, timing,
authorization, correlation ids) belongs in **middleware**, not handlers.

Implement `CommandMiddlewareInterface`:

```php
use ItPack\CommandIt\Contract\Command\CommandMiddlewareInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ContextEnricherInterface;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\Classification;

final class CorrelationMiddleware implements CommandMiddlewareInterface
{
    public function process(
        CommandInterface $command,
        callable $next,
        ContextEnricherInterface $context,
    ): CommandResultInterface {
        $correlationId = bin2hex(random_bytes(8));

        // Attach classified context that flows to reporters (see §9).
        $context->enrich(
            fn ($ctx) => $ctx->with('correlation_id', $correlationId, Classification::Internal),
        );

        $started = hrtime(true);
        $result  = $next($command);                 // run the rest of the chain + handler
        $elapsed = (hrtime(true) - $started) / 1_000_000;

        // You may also inspect/transform the result:
        return $result->withPayload([
            'correlation_id' => $correlationId,
            'elapsed_ms'     => $elapsed,
            'inner'          => $result->payload(),
        ]);
    }
}
```

Ordering: the **first** registered middleware is the **outermost**; the **last**
is closest to the handler. Register via the factory:

```php
$ci = CommandItFactory::create(middlewares: [new CorrelationMiddleware()]);
```

Or construct `CommandIt` directly with a `middlewares: [...]` array. The chain is
wrapped around the terminal handler; `$next($command)` runs the remaining
middlewares and, finally, the handler. Middleware must be non-intrusive —
logging, timing, and authorization live here, not in handlers.

> A middleware that short-circuits (returns without calling `$next`) ends the
> chain; the handler will not run. Use this for authorization gates.

---

## 9. Context, enrichment & isolation

`CommandContext` is an **immutable**, classified map (`key → {value, classification}`).
Classifications (`Contract\Domain\Classification`) control export and propagation:

| Classification | Meaning | Exported? |
|---|---|---|
| `Public` | Safe to emit | yes, as-is |
| `Internal` | System bookkeeping (ids, names) | yes, as-is |
| `Secret` | Sensitive (password, token, …) | **redacted to `***redacted***`** |
| `Debug` | Diagnostic, high-volume | policy-dependent |

The executor enters a fresh context (`command`, `execution_id`) per dispatch and
**restores the previous context in `finally`**, so nothing leaks across calls —
including in persistent workers.

### Attaching context from middleware

Use the `ContextEnricherInterface` passed to middleware (and, via the port, to
handlers) — it is a **copy-on-write** seam:

```php
$context->enrich(
    fn ($ctx) => $ctx->with('api_token', $token, Classification::Secret),
);
```

The enclosing token is preserved, so `leave()` still restores correctly. Because
the coordinator merges the **redacted** context into every emitted occurrence,
a `Secret` attached here reaches reporters as `***redacted***` (never plaintext),
while a `Public`/`Internal` entry passes through.

### Storage backends

- `FiberBoundContextStorage` (the factory default) keeps a **per-Fiber** stack via
  a `WeakMap`, delegating the synchronous main stack to `AmbientContextStorage`.
  Concurrent Fibers never share context; detached Fibers are collectable without
  manual cleanup.
- `AmbientContextStorage` is the synchronous fallback (a simple LIFO stack).
- `ContextStack` implements the provider contract for a single Fiber/main stack.

You only choose a context provider when you override the factory; otherwise the
fiber-aware default is correct.

---

## 10. Reporting & observability

Observability flows through `ExecutionReporterInterface` (a `Contract\Port`):

```php
interface ExecutionReporterInterface
{
    public const COMMAND_RECEIVED = 'command.received.v1';
    public const COMMAND_HANDLED  = 'command.handled.v1';
    public const COMMAND_FAILED   = 'command.failed.v1';

    public function reportObservation(string $message, array $context = []): void;
    public function reportOccurrence(string $type, array $context = []): void;
    public function reportMeasurement(string $name, int|float $value, string $unit): void;
    public function beginActivity(string $name): string;   // returns an activity id
    public function endActivity(string $activityId): void;
}
```

Occurrence types are **stable, lower-case, versioned** (and map onto RunIt
vocabulary), independent of any PHP class name. The three lifecycle occurrences
are emitted by the coordinator; `duration_ms` is measured for every run.

### In-box reporter decorators (`Adapter\Reporting`)

These wrap *any* `ExecutionReporterInterface` and stay in the `Adapter` layer:

- **`CollectingExecutionReporter`** — keeps a bounded in-memory buffer; inspect
  `entries()` (list of `['type'=>…, …]`). Perfect for tests, examples, and
  "emit nowhere" sinks. Configure `OverflowPolicy`:
  - `DropOldest` (default) — drop the oldest entry when full.
  - `DropNewest` — ignore new entries when full.
  - `Throw` — raise `BufferOverflowException`.
- **`RedactingExecutionReporter`** — applies a `RedactionPolicyInterface` to every
  context before it reaches the delegate. The default `RedactionPolicy` redacts
  keys containing `password`, `token`, `secret`, `authorization`, `key`, `auth`,
  `credential`, `cvv`, `ssn`, `private`, … (case-insensitive substring), truncates
  oversized strings, caps array cardinality, and limits nesting depth.
- **`GuardedExecutionReporter`** — wraps a delegate with a **recursion guard** and
  a **separate health reporter**: if publishing triggers a nested report (a
  self-observation loop) or throws, the signal is routed to the health reporter
  instead of re-entering the delegate or bubbling into the business flow.
- **`NullExecutionReporter`** — the health fallback (does nothing).
- **`SafeReporterFactory::wrap($sink)`** — composes the above into a single
  safe-by-default reporter: a `GuardedExecutionReporter` wrapping a
  `RedactingExecutionReporter($sink, RedactionPolicy)`. **Use this whenever
  sensitive data may flow through reporting.**

```php
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;

$sink = new CollectingExecutionReporter(limit: 500);
$reporter = (new SafeReporterFactory())->wrap($sink); // redaction + recursion guard
```

### Connecting to PSR-3 / PSR-14

`ExecutionReporterInterface` is the adapter seam: a PSR-3 logger maps
observations→logs, and a PSR-14 dispatcher maps occurrences→events. Bring your
own adapter behind this interface; core never imports PSR packages.

---

## 11. Storage & serialization

### Storage

`CommandExecutionStorageInterface` retains execution records:

```php
interface CommandExecutionStorageInterface
{
    public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void;
    public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface;
}
```

- **`NullCommandExecutionStorage`** (factory default) discards everything — safe
  when you don't need to retain executions.
- **`InMemoryCommandExecutionStorage`** keeps them for the process lifetime
  (handy for tests and local inspection).

A future `it-pack/command-it-run-it` adapter will persist/transport executions
via this same port — the domain shape is intentionally RunIt-like but
self-contained.

### Serialization

`SerializerInterface::normalize($data)` returns a **recursively-primitive**
structure (no PHP objects, resources, or closures), so downstream encoding is
explicit, deterministic, and free of arbitrary object serialization. The default
`SafeSerializer` rejects resources, closures, and objects without `toArray()` /
`Stringable` with a `SerializationException`.

Pass a serializer to `CommandItFactory::create(serializer: …)` (or the
`CommandIt` constructor). It is used to **normalize the result payload before
storage** — best-effort: if normalization throws, the original payload is kept
and the business result is unaffected. This keeps persisted snapshots safe and
deterministic without changing what handlers return.

---

## 12. Security & isolation guarantees

These are invariants, not optional features:

- **Original exceptions preserved.** A handler throw is rethrown verbatim.
  Reporter/storage failures never replace it (they are caught and ignored on the
  observation/storage path).
- **No secret leakage.** `SafeReporterFactory` (the factory default reporter) applies
  redaction and the publication recursion guard. A `Secret` attached to context is
  exported as `***redacted***`. A bare, unwrapped reporter applies **neither** —
  always wrap sensitive sinks.
- **No global mutable state.** Context is stored per-Fiber (`WeakMap`) or per
  call stack, never in process-global mutable state. The executor restores the
  prior context in `finally`, so repeated dispatch in a persistent worker leaves
  no residue.
- **Best-effort observation.** A reporter or storage error must never abort the
  business flow; it is swallowed and the original result/exception survives.
- **Bounded buffers.** Reporters, redaction, and storage are bounded by policy
  (overflow, max length, max cardinality, max depth).

---

## 13. CLI adapter (Symfony Console)

`Adapter\CLI` projects registered definitions onto Symfony Console. `symfony/console`
is `suggest`-only — core never imports it; only this adapter does.

```php
<?php

declare(strict_types=1);

use ItPack\CommandIt\Adapter\CLI\ConsoleCommandRegistry;
use ItPack\CommandIt\Bootstrap\CommandItFactory;
use Symfony\Component\Console\Application;

$ci = CommandItFactory::create();
// …register your commands on $ci as usual…

$app = new Application();
$app->setAutoExit(false);

foreach ((new ConsoleCommandRegistry($ci->registry(), $ci->bus()))->toConsoleCommands() as $command) {
    $app->add($command);
}

$app->run(); // e.g. `php bin/console cache:clear redis --force --timeout=60`
```

- `ConsoleCommandRegistry` enumerates every `CommandDefinitionInterface` into a
  `ConsoleCommandBridge` (one Symfony `Command` per definition).
- The bridge mirrors arguments/options into a Symfony `InputDefinition`; `bool`
  options become flags (`--force`), and `int`/`float`/`string` values are coerced
  by the **shared coercion behavior**, so CLI input agrees exactly with the
  programmatic input adapters.
- `execute()` dispatches through the `CommandBusInterface` and writes the
  `CommandResult` payload to the output stream (JSON for arrays, plain text for
  scalars). Objects and object-bearing arrays are **never dumped raw**: supply a
  `SerializerInterface` to the bridge (e.g. `SafeSerializer`) to print their
  normalized form; otherwise the command fails with `{"error":"unserializable
  payload"}`. Handler failures are written to stderr when the output supports it,
  with a non-zero exit code; the original exception is preserved by the core
  executor.
- `ArgvInputParser` covers the raw `$argv` → `CommandInput` path (validation +
  coercion) when you build commands without a full Symfony `Application`. Its
  token grammar rejects unknown options, surplus positionals, and short flags;
  negative numbers are supported as option values and positionals (`--` ends
  option parsing).

---

## 14. Customizing the ports

Every external effect is a `Contract\Port` you can implement and inject. Pass any
override to `CommandItFactory::create(...)` (unmapped ports keep their safe
default). Examples:

```php
use ItPack\CommandIt\Adapter\Common\SystemClock;
use My\App\Infra\UuidIdentifierGenerator;
use My\App\Infra\Psr3ExecutionReporter;
use My\App\Infra\RedisContextStorage;

$ci = CommandItFactory::create(
    clock:    new SystemClock(),                       // or your PSR-20 clock
    ids:      new UuidIdentifierGenerator(),            // CommandExecutionId values
    reporter: (new SafeReporterFactory())->wrap(new Psr3ExecutionReporter($logger)),
    context:  new RedisContextStorage(/* … */),        // your port impl
    storage:  new \ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage(),
);
```

Ports you can supply:

| Port | Responsibility | In-box default |
|---|---|---|
| `ClockInterface` | wall-clock + monotonic time | `SystemClock` (PSR-20 style) |
| `IdentifierGeneratorInterface` | raw id values | `RandomIdentifierGenerator` |
| `ExecutionReporterInterface` | observations/occurrences/measurements/activities | `SafeReporterFactory::wrap(CollectingExecutionReporter)` |
| `ExecutionContextProviderInterface` | ambient/fiber context | `FiberBoundContextStorage` |
| `CommandExecutionStorageInterface` | retain executions | `NullCommandExecutionStorage` |
| `RedactionPolicyInterface` | what/how to redact | `RedactionPolicy` |
| `SerializerInterface` | normalize payloads | *(none — payload kept verbatim)* |

Implement the narrow interface, keep concrete classes `final` and `readonly` where
possible, and the rest of the pipeline is unchanged.

---

## 15. Testing your commands

CommandIt is designed to be testable without infrastructure. The most common
pattern: inject a `CollectingExecutionReporter` and assert on what was emitted,
plus on the returned `CommandResult`.

```php
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Adapter\Reporting\SafeReporterFactory;
use ItPack\CommandIt\Domain\CommandResultStatus;

$reporter = new CollectingExecutionReporter();
$ci = CommandItFactory::create(reporter: (new SafeReporterFactory())->wrap($reporter));

$ci->register($definition, static fn () => $handler);
$result = $ci->dispatch($command);

// Result assertions
assert($result->status() === CommandResultStatus::Succeeded);
assert($result->payload() === ['cleared' => 'redis']);

// Observability assertions
$types = array_map(static fn ($e) => $e['name'] ?? null, $reporter->entries());
assert(in_array('command.received.v1', $types, true));
assert(in_array('command.handled.v1', $types, true));
```

Other useful checks:

- `$ci->has('cache:clear')` — is a command registered?
- `$result->execution()` — assert on the `CommandExecution` snapshot (id, status).
- A `Secret` attached via the enrichment seam appears as `***redacted***` in the
  emitted occurrence context, never plaintext — assert this for security tests.
- Unknown command name → `HandlerNotFoundException`; malformed input →
  `InvalidCommandException`.

Consumers are encouraged to build **fixture interfaces / verifier objects**
(rather than asserting on private internals) so adapter authors can conform
without inheriting a base class.

---

## 16. Performance & benchmarks

Hot paths are designed to stay cheap:

- **Lazy handler resolution.** `HandlerResolver` + `LazyHandlerProxy` defer handler
  construction until dispatch and build it exactly once.
- **Monotonic clocks** for durations (no wall-clock drift).
- **Per-Fiber `WeakMap`** context (no global locking).
- **Best-effort, non-throwing** observation/storage — failures never slow or abort
  the business path.

A PHPBench suite is included:

```bash
composer benchmark
```

`benchmarks/CommandDispatchBench.php` measures `benchDispatch` (full
`register → dispatch`) and `benchHandlerResolution` (lazy resolve only). Expect
sub-millisecond dispatch (low hundreds of microseconds) on commodity hardware.
Wire this as a CI job to catch regressions (see `docs/archive/ROADMAP.md` / the
repository CI config).

---

## 17. Versioning & compatibility

CommandIt is **pre-`1.0.0`**. Breaking changes may occur but must be deliberate
and documented. Treat as compatibility-sensitive:

- Public interfaces and signatures; public classes and enums.
- Artifact normalization fields and serialized schemas marked stable.
- Occurrence type conventions (`command.received.v1`, …).
- Adapter extension contracts and exception types exposed to consumers.

Do not assume a method will be added to a public interface without a bump — adding
an interface method breaks third-party implementations, so new behavior arrives as
a new, composed interface.

---

## 18. Common pitfalls

- **Forgetting the factory's safe reporter.** Passing a *bare* reporter to
  `CommandIt` skips redaction **and** the recursion guard. Always
  `SafeReporterFactory::wrap($sink)` for anything that may carry sensitive data.
- **Treating `CommandResult::fail()` like a throw.** A returned failure is a normal
  observed outcome; `command.failed.v1` is emitted only for *thrown* exceptions.
  Throw for real errors.
- **Assuming context leaks are fine.** Context is isolated per Fiber/call; if you
  stash state in a global/static, you bypass the guarantee. Put it in the context
  with a `Classification` instead.
- **Mixing `Argument`/`Option` types inconsistently.** Coercion is uniform across
  adapters; declaring `type: 'int'` means CLI `--timeout=abc` will be rejected at
  parse time.
- **Relying on `Cancelled`.** It is reserved and not reachable yet.
- **Catching the wrong exception.** `dispatch()` rethrows the *original* handler
  exception; assert against your domain type, not a CommandIt wrapper.

---

## 19. Where to go next

- `docs/architecture/MODEL.md` — the domain model in depth.
- `docs/adr/` — load-bearing decisions: RunIt integration via ports (0001),
  lazy handler resolution (0002), fiber-bound context (0003).
- `docs/archive/ROADMAP.md` — the milestone plan (M0–M12) and confidence tooling.
- `docs/archive/REFACTORING_PLAN.md`, `docs/archive/REVIEW.md`,
  `docs/archive/IMPLEMENTATION_PLAN.md` — historical working docs.

When extending CommandIt, follow the dependency direction
`Domain → Contract`, `Application → Contract/Domain`, `SDK → Application`, and
`Adapter → Contract`: core never imports an adapter, Symfony, or RunIt.
