<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — snapshot omission: payload/hash omitted before sampling.
 *
 * Phase 2 feature: Snapshot payload and content hash are omitted before sampling
 * on the default SDK path. Verified relationships and structural fields are
 * retained; explicit null or custom redactor opt-outs still pass through
 * SDK verification and classification before the publisher stack.
 *
 * This example shows:
 *   1. A snapshot recorded through the scope omits payload before sampling.
 *   2. Structural fields (ID, type, relationships) are retained.
 *   3. Terminal snapshot payloads are omitted before sampling on the SDK path.
 *
 * Run from the package root: `php examples/snapshot-omission.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$runIt->run($definition('snapshot.op'), static function (ExecutionScopeInterface $scope): void {
    $scope->snapshots()->record(
        SnapshotType::fromString('result'),
        ['result' => 'success', 'data' => [1, 2, 3]],
        null,
        1,
        AttributeCollection::fromArray(['order_id' => '12345']),
    );

    echo "snapshot recorded; payload omitted before sampling\n";
    echo "structural fields retained: ID, type, relationships\n";
    echo "content hash omitted (payload no longer present)\n";
});

echo "snapshot omission complete\n";
