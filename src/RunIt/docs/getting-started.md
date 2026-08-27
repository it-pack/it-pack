# Getting Started with RunIt

This guide gets you from zero to a running execution in about ten minutes. It assumes
PHP **>= 8.4** and Composer. By the end you will have instrumented a callable, seen
the artifacts it produced, and know where to go next.

## Install

```bash
composer require it-pack/run-it:^1.0.0@RC
```

After the `1.0.0` tag is published, use `^1.0.0` instead. The public API is frozen at
`1.0.0-rc.1` and protected by a backward-compatibility baseline (`composer api:check`).

## Your first execution

RunIt executes a callable inside an explicit **execution scope**. You build a runtime
with the ports you want (publisher, clock, sequence allocator, snapshot serializer,
identifier generator), then call `run()`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$result = $runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('checkout.process'),
        ExecutionKind::fromString('operation'),
    ),
    static function (): array {
        return ['status' => 'ok'];
    },
);

echo json_encode($result); // {"status":"ok"}
```

> **Run this:** the working script is [`examples/sdk.php`](https://github.com/it-pack/run-it/blob/main/examples/sdk.php)
> and [`examples/successful-execution.php`](https://github.com/it-pack/run-it/blob/main/examples/successful-execution.php).

## What just happened?

The runner performed a fixed lifecycle (see [architecture/lifecycle.md](../architecture/lifecycle.md)):

1. Created the execution.
2. Attached its (empty) context.
3. Published lifecycle artifacts.
4. Invoked your callable.
5. Recorded success or failure.
6. Produced a terminal snapshot.
7. Restored the previous context in `finally`.
8. Returned your callable's result — or rethrew the original exception.

Every artifact belongs to exactly one execution. That single idea is the whole model;
everything else (activities, measurements, observations, occurrences, snapshots) is a
specialized kind of artifact.

## Where to go next

Follow the **[tutorial](tutorial/01-hello-execution.md)** — seven short, progressive
lessons that build the model up one concept at a time:

- [1. Hello, execution](tutorial/01-hello-execution.md)
- [2. Activities](tutorial/02-activities.md)
- [3. Measurements & observations](tutorial/03-measure-and-observe.md)
- [4. Occurrences & snapshots](tutorial/04-occurrences-and-snapshots.md)
- [5. Context & classification](tutorial/05-context-and-propagation.md)
- [6. Nesting, flow & errors](tutorial/06-nesting-and-flow.md)
- [7. Publish & integrate](tutorial/07-publish-and-integrate.md)

Prefer reference material? Jump to [Core Concepts](../README.md#core-concepts), the
[Execution Model](../EXECUTION.md), or the [glossary](../glossary.md).

Decided how you'll deploy? See [Use cases](use-cases.md) and
[Integrations](../integrations/README.md).
