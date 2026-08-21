<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Contract\Domain\ActivityDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;

/**
 * An immutable activity record.
 *
 * The activity record is produced by the activity runtime. It carries the immutable
 * definition, the current status, timing, a bounded attribute set, and an optional
 * safe throwable description, and never retains a mutable activity session. Identity
 * is reported through the activity identifier and the owning execution identifier.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final readonly class Activity implements ActivityInterface
{
    private function __construct(
        private ActivityIdInterface                     $id,
        private ExecutionIdInterface                    $executionId,
        private ActivityDefinitionInterface    $definition,
        private ActivityStatusInterface        $status,
        private InstantInterface               $startedAt,
        private ?InstantInterface              $endedAt,
        private AttributeCollectionInterface   $attributes,
        private ?ThrowableDescriptionInterface $throwableDescription,
        private ArtifactSequenceInterface      $sequence,
    ) {
    }

    public static function start(
        ActivityIdInterface $id,
        ExecutionIdInterface $executionId,
        ActivityDefinitionInterface $definition,
        InstantInterface $startedAt,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
    ): self {
        return new self(
            $id,
            $executionId,
            $definition,
            ActivityStatus::Running,
            $startedAt,
            null,
            $attributes ?? AttributeCollection::empty(),
            null,
            $sequence ?? ArtifactSequence::initial(),
        );
    }

    public function complete(
        ActivityStatusInterface $status,
        ?ThrowableDescriptionInterface $throwableDescription,
        InstantInterface $endedAt,
    ): self {
        return new self(
            $this->id,
            $this->executionId,
            $this->definition,
            $status,
            $this->startedAt,
            $endedAt,
            $this->attributes,
            $throwableDescription,
            $this->sequence,
        );
    }

    public function id(): ActivityIdInterface
    {
        return $this->id;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function activityId(): StringIdentifierInterface
    {
        return $this->id;
    }

    public function definition(): ActivityDefinitionInterface
    {
        return $this->definition;
    }

    public function status(): ActivityStatusInterface
    {
        return $this->status;
    }

    public function startedAt(): InstantInterface
    {
        return $this->startedAt;
    }

    public function endedAt(): ?InstantInterface
    {
        return $this->endedAt;
    }

    public function throwableDescription(): ?ThrowableDescriptionInterface
    {
        return $this->throwableDescription;
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
        return $this->startedAt->epochSeconds();
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
            'execution_id' => $this->executionId->toString(),
            'name' => $this->definition->name()->toString(),
            'kind' => $this->definition->kind()->toString(),
            'status' => $this->status->label(),
            'started_at' => $this->startedAt->epochSeconds(),
            'ended_at' => $this->endedAt?->epochSeconds(),
            'parent_activity_id' => $this->definition->parentActivityId()?->toString(),
            'sequence' => $this->sequence->value(),
            ArtifactField::ATTRIBUTES => $this->attributes->toArray(),
            ArtifactField::THROWABLE => $this->throwableDescription?->toArray(),
        ];
    }
}
