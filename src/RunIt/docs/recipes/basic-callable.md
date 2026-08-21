# Recipe: basic callable

Wrap an existing callable in one RunIt execution without changing its body. The
callable receives an `ExecutionScopeInterface` that exposes the recorders; its return
value passes through unchanged.

```php
<?php

declare(strict_types=1);

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
        // existing business code; return value is preserved
        return ['status' => 'ok'];
    },
);

// $result === ['status' => 'ok']
```

The runner attaches the scope, runs the callable, records a terminal snapshot, and
restores the prior context in `finally`. On a throw, the failure is recorded and the
original `Throwable` is rethrown.

See [`../concepts/execution.md`](../concepts/execution.md) and
[`../integrations/README.md`](../integrations/README.md).
