<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Occurrence;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;

/**
 * An immutable occurrence artifact.
 *
 * An occurrence is an immutable fact that happened during execution. It is identified by
 * a stable, versioned occurrence type, an explicit source, and an optional subject, and
 * it carries an optional recursively-primitive data payload, a content type, and bounded
 * extension attributes. The type is always explicit; a PHP class name is never used as
 * an implicit event type.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final readonly class Occurrence implements OccurrenceInterface
{
    use SequencedBehavior;

    /**
     * @param mixed $data
     */
    private function __construct(
        private readonly OccurrenceIdInterface $id,
        private readonly ExecutionIdInterface $executionId,
        private readonly ?ActivityIdInterface $activityId,
        private readonly OccurrenceTypeInterface $type,
        private readonly string $source,
        private readonly ?string $subject,
        private readonly InstantInterface $time,
        private readonly mixed $data,
        private readonly ?string $dataSchema,
        private readonly string $contentType,
        private readonly AttributeCollectionInterface $extensionAttributes,
        private readonly ArtifactSequenceInterface $sequence,
    ) {
    }

    /**
     * @param mixed $data
     */
    public static function create(
        OccurrenceIdInterface $id,
        ExecutionIdInterface $executionId,
        OccurrenceTypeInterface $type,
        string $source,
        InstantInterface $time,
        mixed $data = null,
        ?ActivityIdInterface $activityId = null,
        ?string $subject = null,
        ?string $dataSchema = null,
        string $contentType = MediaTypes::APPLICATION_JSON,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        new ContextValueValidator()->assertSupportedValue($data);

        return new self(
            $id,
            $executionId,
            $activityId,
            $type,
            $source,
            $subject,
            $time,
            $data,
            $dataSchema,
            $contentType,
            $extensionAttributes ?? AttributeCollection::empty(),
            $sequence ?? ArtifactSequence::initial(),
        );
    }

    public function id(): OccurrenceIdInterface
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

    public function type(): OccurrenceTypeInterface
    {
        return $this->type;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function time(): InstantInterface
    {
        return $this->time;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function dataSchema(): ?string
    {
        return $this->dataSchema;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function extensionAttributes(): array
    {
        return $this->extensionAttributes->toArray();
    }

    public function timestamp(): int
    {
        return $this->time->epochSeconds();
    }

    public function sequence(): int
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
            ArtifactField::VERSION => $this->type->version(),
            ArtifactField::SOURCE => $this->source,
            ArtifactField::SUBJECT => $this->subject,
            ArtifactField::TIME => $this->time->epochSeconds(),
            ArtifactField::DATA => $this->data,
            ArtifactField::DATA_SCHEMA => $this->dataSchema,
            ArtifactField::CONTENT_TYPE => $this->contentType,
            ArtifactField::EXTENSION_ATTRIBUTES => $this->extensionAttributes->toArray(),
            ArtifactField::SEQUENCE => $this->sequence->value(),
        ];
    }
}
