<?php

declare(strict_types=1);

/**
 * RunIt — nested activities.
 *
 * An execution can contain nested activities; an activity may itself contain nested activities
 * through parent/child identity. The active activity is tracked by the scope, so recorders bind
 * artifacts to the right activity automatically.
 *
 * Run from the package root: `php examples/nested-activities.php`
 */

require __DIR__ . '/../vendor/autoload.php';

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
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('validate'), ActivityKind::fromString('step')),
            static function () use ($scope): void {
                $scope->activities()->run(
                    ActivityDefinition::create(ActivityName::fromString('validate.items'), ActivityKind::fromString('step')),
                    static function (): void {
                        echo '  validating items' . PHP_EOL;
                    },
                );
            },
        );

        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('charge'), ActivityKind::fromString('step')),
            static function (): void {
                echo '  charging' . PHP_EOL;
            },
        );
    },
);

echo 'nested activities completed' . PHP_EOL;
