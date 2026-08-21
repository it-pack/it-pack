<?php

declare(strict_types=1);

/**
 * RunIt — snapshots.
 *
 * A snapshot is an immutable projection of resulting state, bound to a deterministic content hash so
 * a store can verify integrity and link predecessors. See docs/concepts/snapshot.md.
 *
 * Run from the package root: `php examples/snapshots.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
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
        $scope->snapshots()->record(
            SnapshotType::fromString('order.state'),
            payload: ['order_id' => 42, 'status' => 'paid'],
            classification: SnapshotClassification::Terminal,
        );
    },
);

echo 'captured terminal snapshot order.state' . PHP_EOL;
