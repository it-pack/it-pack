<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

final class NoopSnapshotRecorder implements SnapshotRecorderInterface
{
    public function __construct(private readonly ExecutionIdInterface $executionId)
    {
    }

    public function record(
        \ItPack\RunIt\Contract\Domain\SnapshotTypeInterface $type,
        mixed $payload,
        ?\ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface $classification = null,
        int $schemaVersion = 1,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface {
        return \ItPack\RunIt\Domain\Snapshot\Snapshot::create(
            \ItPack\RunIt\Domain\Snapshot\SnapshotId::fromString('noop-snapshot'),
            $this->executionId,
            $type,
            $schemaVersion,
            $payload,
            new \ItPack\RunIt\Domain\Shared\DeterministicHashCalculator(),
            null,
            null,
            $classification,
        );
    }
}
