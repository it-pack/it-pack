<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;

final readonly class NoopSnapshotRecorder implements SnapshotRecorderInterface
{
    public function __construct(private ExecutionIdInterface $executionId)
    {
    }

    public function record(
        SnapshotTypeInterface $type,
        mixed $payload,
        ?SnapshotClassificationInterface $classification = null,
        int $schemaVersion = 1,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface {
        return Snapshot::create(
            SnapshotId::fromString('noop-snapshot'),
            $this->executionId,
            $type,
            $schemaVersion,
            $payload,
            new DeterministicHashCalculator(),
            classification: $classification,
        );
    }
}
