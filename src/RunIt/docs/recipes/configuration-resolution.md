# Recipe: configuration resolution

Model configuration resolution as one RunIt execution and record its artifacts: how
many files loaded (counter), how long it took (duration), any warnings (observation),
a `config.loaded` occurrence, and a terminal configuration snapshot. This mirrors the
ConfigureIt adoption case study (M14.7) using only RunIt primitives, so it works with
any configuration source.

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
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
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
    ExecutionDefinition::create(ExecutionName::fromString('config.resolve'), ExecutionKind::fromString('configuration')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope) use ($files, $durationMs, $warnings): void {
        $scope->measurements()->counter(MeasurementName::fromString('config.files_loaded'), count($files), MeasurementUnit::Count);
        $scope->measurements()->duration(MeasurementName::fromString('config.duration'), $durationMs, MeasurementUnit::Milliseconds);

        foreach ($warnings as $warning) {
            $scope->observations()->record($warning, ObservationSeverity::Warning);
        }

        $scope->occurrences()->record(
            OccurrenceType::fromString('config.loaded.v1'),
            'config',
            data: ['files' => $files],
        );

        $scope->snapshots()->record(
            SnapshotType::fromString('config.resolved'),
            payload: ['entries' => count($files)],
            classification: SnapshotClassification::Terminal,
        );
    },
);
```

Occurrence types are stable and versioned (`config.loaded.v1`); the `data` payload is
recursively primitive. See [`../concepts/occurrence.md`](../concepts/occurrence.md),
[`../concepts/snapshot.md`](../concepts/snapshot.md), and
[`../concepts/measurement.md`](../concepts/measurement.md).
