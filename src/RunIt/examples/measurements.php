<?php

declare(strict_types=1);

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * RunIt — measurements.
 *
 * A measurement is a quantitative observation: a named, typed, unit-bearing numeric value. Counters
 * and sizes are integers; gauges, durations, and ratios are floats. Durations use the monotonic
 * clock. See docs/concepts/measurement.md.
 *
 * Run from the package root: `php examples/measurements.php`
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
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
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
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (ExecutionScopeInterface $scope): void {
        $scope->measurements()->counter(MeasurementName::fromString('items'), 3, MeasurementUnit::Count);
        $scope->measurements()->duration(MeasurementName::fromString('elapsed'), 12.5, MeasurementUnit::Milliseconds);
        $scope->measurements()->gauge(MeasurementName::fromString('memory_mb'), 64.0, MeasurementUnit::Megabytes);
    },
);

echo 'recorded measurements (counter/items, duration/elapsed, gauge/memory_mb)' . PHP_EOL;
