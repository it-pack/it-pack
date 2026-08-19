<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;
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
    private function __construct(
        private readonly ExecutionIdInterface $id,
        private readonly ExecutionDefinitionInterface $definition,
        private readonly ExecutionStatusInterface $status,
        private readonly InstantInterface $startedAt,
        private readonly ?InstantInterface $endedAt,
        private readonly ?ExecutionResultInterface $result,
        private readonly ArtifactSequenceInterface $sequence,
    ) {
    }

    public static function create(
        ExecutionIdInterface $id,
        ExecutionDefinitionInterface $definition,
        InstantInterface $startedAt,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return new self($id, $definition, ExecutionStatus::Created, $startedAt, null, null, $sequence ?? ArtifactSequence::initial());
    }

    public static function start(
        ExecutionIdInterface $id,
        ExecutionDefinitionInterface $definition,
        InstantInterface $startedAt,
        ?ArtifactSequenceInterface $sequence = null,
    ): self {
        return self::create($id, $definition, $startedAt, $sequence)->beginRunning();
    }

    public function beginRunning(): self
    {
        if ($this->status !== ExecutionStatus::Created) {
            throw InvalidLifecycleTransitionException::forTransition($this->status, ExecutionStatus::Running);
        }

        return new self($this->id, $this->definition, ExecutionStatus::Running, $this->startedAt, null, null, $this->sequence);
    }

    public function complete(ExecutionResultInterface $result, InstantInterface $endedAt): self
    {
        return new self($this->id, $this->definition, $result->status(), $this->startedAt, $endedAt, $result, $this->sequence);
    }

    public function id(): ExecutionIdInterface
    {
        return $this->id;
    }

    public function executionId(): \ItPack\RunIt\Contract\Domain\ExecutionIdInterface
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
            'id' => $this->id->toString(),
            'name' => $this->definition->name()->toString(),
            'kind' => $this->definition->kind()->toString(),
            'status' => $this->status->label(),
            'started_at' => $this->startedAt->epochSeconds(),
            'ended_at' => $this->endedAt?->epochSeconds(),
            'parent_execution_id' => $this->definition->parentExecutionId()?->toString(),
            'linked_execution_ids' => array_map(
                static fn (\ItPack\RunIt\Contract\Domain\ExecutionIdInterface $linked): string => $linked->toString(),
                $this->definition->linkedExecutionIds(),
            ),
            'sequence' => $this->sequence->value(),
            'context' => $this->definition->context()->toArray(),
            'result' => $this->result === null ? null : [
                'status' => $this->result->status()->label(),
                'throwable' => $this->result->throwableDescription()?->toArray(),
            ],
        ];
    }
}
