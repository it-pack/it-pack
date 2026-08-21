# Recipe: scheduler

Model each scheduled tick as its own RunIt execution. RunIt does not ship a cron
implementation — your scheduler (cron, a message-broker delay, or a loop) decides
*when* to run; RunIt models *what* ran and records its artifacts. Give each tick a
stable `scheduled` kind so ticks group in tooling.

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
    $runtime->run(
        ExecutionDefinition::create(ExecutionName::fromString('scheduler.tick'), ExecutionKind::fromString('scheduled')),
        static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
            // the scheduled work; record measurements/observations/occurrences as needed
        },
    );

    // Real scheduling is your concern (cron, broker delay, sleep, ...).
    // RunIt models each tick; it does not own the timer.
    sleep(60);
}
```

Each tick gets a fresh execution identity, so overlapping or retried ticks never
collide. For long-running schedulers, combine with a background-worker pattern and
restore context per tick (the runner does this in `finally`).

See [`../concepts/execution.md`](../concepts/execution.md).
