<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Domain\Context\DefaultContextRedactionPolicy;
use ItPack\RunIt\Domain\Exception\ConflictingCompletionException;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;

/**
 * An immutable execution record.
 *
 * The execution record is the first artifact of an execution. It is produced by the
 * runtime and never retains a mutable session: it carries the immutable definition,
 * the current status, timing, an optional result, and its own sequence. Identity is
 * reported through the execution identifier, not through an owning collection.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class Execution implements ExecutionInterface
{
    use SequencedBehavior;

    private function __construct(
        private ExecutionIdInterface         $id,
        private ExecutionDefinitionInterface $definition,
        private ExecutionStatusInterface     $status,
        private InstantInterface             $startedAt,
        private ?InstantInterface            $endedAt,
        private ?ExecutionResultInterface    $result,
        private ArtifactSequenceInterface    $sequence,
    ) {
    }

    public static function create(
        ExecutionIdInterface $id,
        ExecutionDefinitionInterface $definition,
        InstantInterface $startedAt,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self(
            $id,
            $definition,
            ExecutionStatus::Created,
            $startedAt,
            null,
            null,
            $sequence ?? ArtifactSequence::initial()
        );
    }

    public static function start(
        ExecutionIdInterface $id,
        ExecutionDefinitionInterface $definition,
        InstantInterface $startedAt,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return self::create(
            $id,
            $definition,
            $startedAt,
            $sequence
        )->beginRunning();
    }

    public function beginRunning(): self
    {
        if ($this->status !== ExecutionStatus::Created) {
            throw InvalidLifecycleTransitionException::forTransition($this->status, ExecutionStatus::Running);
        }

        return new self(
            $this->id,
            $this->definition,
            ExecutionStatus::Running,
            $this->startedAt,
            null,
            null,
            $this->sequence
        );
    }

    public function complete(ExecutionResultInterface $result, InstantInterface $endedAt): self
    {
        if ($this->status->isTerminal()) {
            throw ConflictingCompletionException::forConflict($this->status, $result->status());
        }

        if (!$this->status->canTransitionTo($result->status())) {
            throw InvalidLifecycleTransitionException::forTransition($this->status, $result->status());
        }

        return new self(
            $this->id,
            $this->definition,
            $result->status(),
            $this->startedAt,
            $endedAt,
            $result,
            $this->sequence
        );
    }

    public function id(): ExecutionIdInterface
    {
        return $this->id;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->id;
    }

    public function definition(): ExecutionDefinitionInterface
    {
        return $this->definition;
    }

    public function status(): ExecutionStatusInterface
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

    public function result(): ?ExecutionResultInterface
    {
        return $this->result;
    }

    public function timestamp(): int
    {
        return $this->startedAt->epochSeconds();
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
            ArtifactField::NAME => $this->definition->name()->toString(),
            ArtifactField::KIND => $this->definition->kind()->toString(),
            ArtifactField::STATUS => $this->status->label(),
            ArtifactField::STARTED_AT => $this->startedAt->epochSeconds(),
            ArtifactField::ENDED_AT => $this->endedAt?->epochSeconds(),
            ArtifactField::PARENT_EXECUTION_ID => $this->definition->parentExecutionId()?->toString(),
            ArtifactField::LINKED_EXECUTION_IDS => array_map(
                static fn (ExecutionIdInterface $linked): string => $linked->toString(),
                $this->definition->linkedExecutionIds(),
            ),
            ArtifactField::SEQUENCE => $this->sequence->value(),
            ArtifactField::CONTEXT => $this->definition->context()->toArray(DefaultContextRedactionPolicy::default()),
            ArtifactField::RESULT => $this->result === null ? null : [
                ArtifactField::STATUS => $this->result->status()->label(),
                ArtifactField::THROWABLE => $this->result->throwableDescription()?->toArray(),
            ],
        ];
    }
}
