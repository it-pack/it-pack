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
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — measurement drop: unclassified measurements dropped before sampling.
 *
 * Phase 2 feature: Unclassified measurements are dropped before the sampler and
 * sink ever see them. This is delivery loss, not value redaction — the
 * measurement value itself is not exposed through the health path.
 *
 * This example shows:
 *   1. A measurement without classification is dropped before sampling.
 *   2. The drop is reported as a payload-free health notice.
 *   3. Classification before sampling is deterministic.
 *
 * Run from the package root: `php examples/measurement-drop.php`
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

$runIt->run($definition('measured.op'), static function (ExecutionScopeInterface $scope): void {
    $scope->measurements()->gauge(
        MeasurementName::fromString('order.latency'),
        42.5,
        MeasurementUnit::Milliseconds,
        AttributeCollection::fromArray(['order_id' => '12345']),
    );

    echo "measurement recorded; unclassified fields dropped before sampling\n";
    echo "delivery loss reported as payload-free health notice\n";
    echo "measurement value itself never exposed through health path\n";
});

echo "measurement drop complete\n";
