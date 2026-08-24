<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * An immutable snapshot artifact.
 *
 * A snapshot captures the normalized state of an execution at a point in time. It is identified
 * by a stable identifier, typed by a snapshot type, classified as checkpoint or terminal, and
 * bound to its payload through a deterministic content hash. Snapshots may reference a predecessor
 * so a store can resolve history without retaining every payload inline.
 *
 * This class is intentionally not `final`: it is the single closed base refined only by
 * `ExecutionSnapshot`, mirroring the vocabulary (`runit:ExecutionSnapshot` is a subclass of
 * `runit:Snapshot`). No further subtypes are permitted; this is the documented ADR-015 exception
 * for a vocabulary-backed refinement.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
readonly class Snapshot implements SnapshotInterface
{
    protected function __construct(
        private readonly SnapshotIdInterface $id,
        private readonly ExecutionIdInterface $executionId,
        private readonly ?ActivityIdInterface $activityId,
        private readonly SnapshotTypeInterface $type,
        private readonly SnapshotClassificationInterface $classification,
        private readonly int $schemaVersion,
        private readonly InstantInterface $createdAt,
        private readonly mixed $payload,
        private readonly string $contentType,
        private readonly string $contentHash,
        private readonly ?SnapshotIdInterface $predecessorId,
        private readonly AttributeCollectionInterface $attributes,
        private readonly ArtifactSequenceInterface $sequence,
    ) {
    }

    public static function create(
        SnapshotIdInterface $id,
        ExecutionIdInterface $executionId,
        SnapshotTypeInterface $type,
        int $schemaVersion,
        mixed $payload,
        HashCalculatorInterface $hashCalculator,
        ?ActivityIdInterface $activityId = null,
        ?SnapshotIdInterface $predecessorId = null,
        ?SnapshotClassificationInterface $classification = null,
        ?InstantInterface $createdAt = null,
        string $contentType = 'application/json',
        ?AttributeCollectionInterface $attributes = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        new ContextValueValidator()->assertSupportedValue($payload);

        $content = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentHash = $hashCalculator->calculate($content);

        return static::build(
            $id,
            $executionId,
            $activityId,
            $type,
            $classification ?? SnapshotClassification::Checkpoint,
            $schemaVersion,
            $createdAt ?? Instant::now(),
            $payload,
            $contentType,
            $contentHash,
            $predecessorId,
            $attributes ?? AttributeCollection::empty(),
            $sequence ?? ArtifactSequence::initial(),
        );
    }

    /**
     * @param mixed $payload
     */
    protected static function build(
        SnapshotIdInterface $id,
        ExecutionIdInterface $executionId,
        ?ActivityIdInterface $activityId,
        SnapshotTypeInterface $type,
        SnapshotClassificationInterface $classification,
        int $schemaVersion,
        InstantInterface $createdAt,
        mixed $payload,
        string $contentType,
        string $contentHash,
        ?SnapshotIdInterface $predecessorId,
        AttributeCollectionInterface $attributes,
        ArtifactSequenceInterface $sequence,
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

    public function id(): SnapshotIdInterface
    {
        return $this->id;
    }

    public function executionId(): \ItPack\RunIt\Contract\Domain\ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function activityId(): ?\ItPack\RunIt\Contract\Domain\StringIdentifierInterface
    {
        return $this->activityId;
    }

    public function type(): SnapshotTypeInterface
    {
        return $this->type;
    }

    public function classification(): SnapshotClassificationInterface
    {
        return $this->classification;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function predecessorId(): ?SnapshotIdInterface
    {
        return $this->predecessorId;
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function attributes(): array
    {
        return $this->attributes->toArray();
    }

    public function timestamp(): int
    {
        return $this->createdAt->epochSeconds();
    }

    public function sequence(): int
    {
        return $this->sequence->value();
    }

    public function count(): int
    {
        return $this->sequence->value();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            ArtifactField::ID => $this->id->toString(),
            ArtifactField::EXECUTION_ID => $this->executionId->toString(),
            ArtifactField::ACTIVITY_ID => $this->activityId?->toString(),
            ArtifactField::TYPE => $this->type->toString(),
            ArtifactField::CLASSIFICATION => $this->classification->label(),
            ArtifactField::SCHEMA_VERSION => $this->schemaVersion,
            ArtifactField::CREATED_AT => $this->createdAt->epochSeconds(),
            ArtifactField::CONTENT_TYPE => $this->contentType,
            ArtifactField::CONTENT_HASH => $this->contentHash,
            ArtifactField::PREDECESSOR_ID => $this->predecessorId?->toString(),
            ArtifactField::SEQUENCE => $this->sequence->value(),
            ArtifactField::PAYLOAD => $this->payload,
            ArtifactField::ATTRIBUTES => $this->attributes->toArray(),
        ];
    }
}
