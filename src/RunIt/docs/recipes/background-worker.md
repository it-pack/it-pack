# Recipe: background worker

Run a long-lived worker where each unit of work is its own RunIt execution. The
runner restores the prior context in `finally`, so context never leaks between units
of work. For a real persistent worker (Swoole/OpenSwoole) use the coroutine context
storage and `ServerExecutionMiddleware` from
[`../integrations/swoole.md`](../integrations/swoole.md); the pattern below is the
framework-agnostic core.

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

$running = true;
while ($running) {
    $work = $queue->pull();
    if ($work === null) {
        usleep(1000);
        continue;
    }

    $runtime->run(
        ExecutionDefinition::create(ExecutionName::fromString('worker.process'), ExecutionKind::fromString('worker')),
        static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope) use ($work): void {
            // process one unit of work; record artifacts as needed
        },
    );
    // context is restored here; never retain the execution scope between iterations
}
```

Never store request/execution context in process-global mutable state. Bind all
per-unit data to the `ExecutionScope`. Flush bounded publishers within the worker's
shutdown budget. See
[`../architecture/context-propagation.md`](../architecture/context-propagation.md).
