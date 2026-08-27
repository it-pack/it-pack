# Tutorial 1 — Hello, execution

**Goal:** run one unit of work inside a RunIt execution and get its result back.

This is the smallest useful RunIt program. Everything else in the tutorial builds on
it, so make sure you can run this first.

## The runtime

RunIt is assembled from **ports** — small interfaces for external effects. You supply
the implementations you want; the SDK wires the rest:

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
```

The `InMemoryArtifactPublisher` keeps every emitted artifact in memory so you can
inspect it afterwards. In production you would swap it for a real publisher (see
[Tutorial 7](07-publish-and-integrate.md)).

## Run an execution

```php
$result = $runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static function (): array {
        return ['order_id' => 42, 'status' => 'paid'];
    },
);

echo json_encode($result); // {"order_id":42,"status":"paid"}
```

The callable's return value passes through **unchanged**. RunIt wrapped it in an
execution, recorded the lifecycle, and gave you your result back.

> **Run this:** [`examples/successful-execution.php`](https://github.com/it-pack/run-it/blob/main/examples/successful-execution.php)
> prints the recorded execution status from the in-memory publisher.

## What you learned

- An **execution** is one logical operation; you describe it with an
  `ExecutionDefinition` (a name + a kind).
- `$runtime->run(definition, callable)` is the universal entry point.
- Your business code does not change — the result flows through untouched.

## Next

[Part 2 — Activities](02-activities.md): break the work into bounded steps.
