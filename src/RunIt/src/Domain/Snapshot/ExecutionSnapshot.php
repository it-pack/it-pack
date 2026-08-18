<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;

/**
 * An immutable execution snapshot.
 *
 * An execution snapshot captures the normalized state of the execution itself (its definition,
 * status, context, and artifact summary). It refines `Snapshot` so consumers can treat every
 * snapshot uniformly while distinguishing execution snapshots specifically.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class ExecutionSnapshot extends Snapshot implements ExecutionSnapshotInterface
{
    public static function create(
        \ItPack\RunIt\Contract\Domain\SnapshotIdInterface $id,
        \ItPack\RunIt\Contract\Domain\ExecutionIdInterface $executionId,
        \ItPack\RunIt\Contract\Domain\SnapshotTypeInterface $type,
        int $schemaVersion,
        mixed $payload,
        \ItPack\RunIt\Contract\Domain\HashCalculatorInterface $hashCalculator,
        ?\ItPack\RunIt\Contract\Domain\ActivityIdInterface $activityId = null,
        ?\ItPack\RunIt\Contract\Domain\SnapshotIdInterface $predecessorId = null,
        ?\ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface $classification = null,
        ?\ItPack\RunIt\Contract\Domain\InstantInterface $createdAt = null,
        string $contentType = 'application/json',
        ?\ItPack\RunIt\Contract\Domain\AttributeCollectionInterface $attributes = null,
        ?\ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface $sequence = null,
    ): self {
        $content = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::build(
            $id,
            $executionId,
            $activityId,
            $type,
            $classification ?? \ItPack\RunIt\Domain\Snapshot\SnapshotClassification::Checkpoint,
            $schemaVersion,
            $createdAt ?? \ItPack\RunIt\Domain\Shared\Instant::now(),
            $payload,
            $contentType,
            $hashCalculator->calculate($content),
            $predecessorId,
            $attributes ?? \ItPack\RunIt\Domain\Shared\AttributeCollection::empty(),
            $sequence ?? \ItPack\RunIt\Domain\Shared\ArtifactSequence::initial(),
        );
    }


    protected static function build(
        \ItPack\RunIt\Contract\Domain\SnapshotIdInterface $id,
        \ItPack\RunIt\Contract\Domain\ExecutionIdInterface $executionId,
        ?\ItPack\RunIt\Contract\Domain\ActivityIdInterface $activityId,
        \ItPack\RunIt\Contract\Domain\SnapshotTypeInterface $type,
        \ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface $classification,
        int $schemaVersion,
        \ItPack\RunIt\Contract\Domain\InstantInterface $createdAt,
        mixed $payload,
        string $contentType,
        string $contentHash,
        ?\ItPack\RunIt\Contract\Domain\SnapshotIdInterface $predecessorId,
        \ItPack\RunIt\Contract\Domain\AttributeCollectionInterface $attributes,
        \ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface $sequence,
    ): self {
        return new self(
            $id,
            $executionId,
            $activityId,
            $type,
            $classification,
            $schemaVersion,
            $createdAt,
            $payload,
            $contentType,
            $contentHash,
            $predecessorId,
            $attributes,
            $sequence,
        );
    }
}
