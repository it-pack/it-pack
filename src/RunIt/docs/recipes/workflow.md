# Recipe: workflow

Model a multi-step workflow as one execution with sequential activities, where each
step may contain nested activities. Steps share the execution identity and timing; a
failed step records the failure and rethrows, failing the whole execution.

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
    ExecutionDefinition::create(ExecutionName::fromString('pipeline.run'), ExecutionKind::fromString('workflow')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('fetch'), ActivityKind::fromString('step')),
            static function () use ($scope): void {
                $scope->activities()->run(
                    ActivityDefinition::create(ActivityName::fromString('fetch.source'), ActivityKind::fromString('step')),
                    static function (): void {
                        // nested work
                    },
                );
            },
        );

        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('transform'), ActivityKind::fromString('step')),
            static function (): void {
                // work
            },
        );

        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('publish'), ActivityKind::fromString('step')),
            static function (): void {
                // work
            },
        );
    },
);
```

Open activities are resolved when the execution completes. Nested activities are
linked by identity (`parentActivityId`), not by an in-memory tree. See
[`../concepts/activity.md`](../concepts/activity.md) and
[`../architecture/lifecycle.md`](../architecture/lifecycle.md).
