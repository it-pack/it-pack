<?php

declare(strict_types=1);

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * RunIt — occurrences.
 *
 * An occurrence is an immutable, versioned fact. Its type is lowercase and suffixed with `.vN`
 * (for example `order.placed.v1`); the payload is recursively primitive. Occurrences map to
 * CloudEvents, not to logs. See docs/concepts/occurrence.md.
 *
 * Run from the package root: `php examples/occurrences.php`
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
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('order.place'), ExecutionKind::fromString('operation')),
    static function (ExecutionScopeInterface $scope): void {
        $scope->occurrences()->record(
            OccurrenceType::fromString('order.placed.v1'),
            'order',
            data: ['order_id' => 42, 'status' => 'paid'],
        );
    },
);

echo 'raised occurrence order.placed.v1' . PHP_EOL;
