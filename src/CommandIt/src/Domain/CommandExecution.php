<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\CommandStatusInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Exception\CommandExecutionException;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * A self-contained, RunIt-shaped record of one command execution.
 *
 * This is the first artifact produced for a command. It carries the immutable
 * definition, the current status, timing, and an optional result, and never
 * retains a mutable execution session. Terminal executions ({@see CommandStatus})
 * cannot be transitioned again.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CommandExecution implements ExecutionArtifactInterface
{
    /**
     * @param int $startedAt
     * @param int|null $endedAt
     */
    public function __construct(
        private CommandExecutionIdInterface $id,
        private CommandDefinitionInterface $definition,
        private CommandStatusInterface $status,
        private int $startedAt,
        private ?int $endedAt = null,
        private ?CommandResultInterface $result = null,
    ) {
    }

    /**
     * @param int $startedAt
     */
    public static function define(
        CommandDefinitionInterface $definition,
        CommandExecutionIdInterface $id,
        int $startedAt,
    ): self {
        return new self($id, $definition, CommandStatus::Defined, $startedAt);
    }

    public function id(): CommandExecutionIdInterface
    {
        return $this->id;
    }

    public function definition(): CommandDefinitionInterface
    {
        return $this->definition;
    }

    public function status(): CommandStatusInterface
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function startedAt(): int
    {
        return $this->startedAt;
    }

    /**
     * @return int|null
     */
    public function endedAt(): ?int
    {
        return $this->endedAt;
    }

    public function result(): ?CommandResultInterface
    {
        return $this->result;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Returns a copy carrying a new non-terminal status.
     *
     * The receiver is never mutated. Throws when already terminal.
     *
     * @throws CommandExecutionException
     */
    public function transition(CommandStatusInterface $next): self
    {
        if ($this->isTerminal()) {
            throw new CommandExecutionException('A terminal execution cannot transition.');
        }

        return new self(
            $this->id,
            $this->definition,
            $next,
            $this->startedAt,
            $this->endedAt,
            $this->result,
        );
    }

    /**
     * Returns a terminal copy carrying the result and end time.
     *
     * The receiver is never mutated. Throws when already terminal.
     *
     * @param int $endedAt
     *
     * @throws CommandExecutionException
     */
    public function complete(CommandResultInterface $result, int $endedAt): self
    {
        if ($this->isTerminal()) {
            throw new CommandExecutionException('A terminal execution cannot be completed again.');
        }

        $next = $result->status() === CommandResultStatus::Succeeded
            ? CommandStatus::Succeeded
            : CommandStatus::Failed;

        return new self($this->id, $this->definition, $next, $this->startedAt, $endedAt, $result);
    }
}
