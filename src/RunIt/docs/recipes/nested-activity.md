# Recipe: nested activity

An execution can contain nested activities. Each activity is a bounded unit of work
and may itself contain nested activities through `parentActivityId()`. The active
activity identity is tracked by the scope, so recorders bind artifacts correctly.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
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

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('order.place'), ExecutionKind::fromString('operation')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope) use ($runtime): void {
        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('validate'), ActivityKind::fromString('step')),
            static function () use ($scope): void {
                // a nested activity inside "validate"
                $scope->activities()->run(
                    ActivityDefinition::create(ActivityName::fromString('validate.items'), ActivityKind::fromString('step')),
                    static function (): void {
                        // work
                    },
                );
            },
        );

        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('charge'), ActivityKind::fromString('step')),
            static function (): void {
                // work
            },
        );
    },
);
```

Activities are resolved when the execution completes; open activities are closed by
the completion policy. Nesting is expressed by identity, not by an in-memory tree.

See [`../concepts/activity.md`](../concepts/activity.md) and
[`../architecture/lifecycle.md`](../architecture/lifecycle.md).
