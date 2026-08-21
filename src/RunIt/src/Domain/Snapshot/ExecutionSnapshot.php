<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ContextValueValidatorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

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
        SnapshotIdInterface                                         $id,
        ExecutionIdInterface                                     $executionId,
        SnapshotTypeInterface            $type,
        int                              $schemaVersion,
        mixed                            $payload,
        HashCalculatorInterface          $hashCalculator,
        ?ActivityIdInterface             $activityId = null,
        ?SnapshotIdInterface             $predecessorId = null,
        ?SnapshotClassificationInterface $classification = null,
        ?InstantInterface                $createdAt = null,
        string                           $contentType = 'application/json',
        ?AttributeCollectionInterface    $attributes = null,
        ?ArtifactSequenceInterface       $sequence = null,
        ContextValueValidatorInterface   $validator = new ContextValueValidator(),
    ): self {
        $validator->assertSupportedValue($payload);

        $content = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::build(
            $id,
            $executionId,
            $activityId,
            $type,
            $classification ?? SnapshotClassification::Checkpoint,
            $schemaVersion,
            $createdAt ?? Instant::now(),
            $payload,
            $contentType,
            $hashCalculator->calculate($content),
            $predecessorId,
            $attributes ?? AttributeCollection::empty(),
            $sequence ?? ArtifactSequence::initial(),
        );
    }


    protected static function build(
        SnapshotIdInterface                                           $id,
        ExecutionIdInterface                                          $executionId,
        ?ActivityIdInterface                                          $activityId,
        SnapshotTypeInterface                                         $type,
        SnapshotClassificationInterface $classification,
        int                                                           $schemaVersion,
        InstantInterface                $createdAt,
        mixed                                                         $payload,
        string                                                        $contentType,
        string                                                        $contentHash,
        ?SnapshotIdInterface                                          $predecessorId,
        AttributeCollectionInterface    $attributes,
        ArtifactSequenceInterface       $sequence,
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
