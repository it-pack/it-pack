<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Occurrence\Occurrence;

/** @internal An immutable classified occurrence retaining the redaction signal. */
final readonly class ClassifiedRedactedOccurrence implements OccurrenceInterface, RedactedArtifactInterface
{
    use SequencedBehavior;

    public function __construct(private Occurrence $occurrence)
    {
    }

    public function artifactType(): string
    {
        return 'occurrence';
    }

    public function id(): OccurrenceIdInterface
    {
        return $this->occurrence->id();
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->occurrence->executionId();
    }

    public function activityId(): ?StringIdentifierInterface
    {
        return $this->occurrence->activityId();
    }

    public function type(): OccurrenceTypeInterface
    {
        return $this->occurrence->type();
    }

    public function source(): string
    {
        return $this->occurrence->source();
    }

    public function subject(): ?string
    {
        return $this->occurrence->subject();
    }

    public function time(): InstantInterface
    {
        return $this->occurrence->time();
    }

    public function timestamp(): int
    {
        return $this->occurrence->timestamp();
    }

    public function data(): mixed
    {
        return $this->occurrence->data();
    }

    public function dataSchema(): ?string
    {
        return $this->occurrence->dataSchema();
    }

    public function contentType(): string
    {
        return $this->occurrence->contentType();
    }

    public function extensionAttributes(): array
    {
        return $this->occurrence->extensionAttributes();
    }

    public function sequence(): int
    {
        return $this->occurrence->sequence();
    }

    public function normalize(): array
    {
        return $this->occurrence->normalize();
    }
}
