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
     * Legal non-terminal transitions (D4 / ADR-0005).
     *
     * The lifecycle machine is enforced by {@see self::transition()}: a status maps
     * to exactly the statuses it may legally enter. Terminal moves (succeeded/failed)
     * are not listed here — they are produced only by {@see self::complete()}, which
     * requires the execution to currently be `executing`. `cancelled` has no incoming
     * edge until a `CancellationPort` is introduced.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'defined' => ['received'],
        'received' => ['executing'],
        'executing' => [],
    ];

    /**
     * @param int $startedAt
     * @param int|null $endedAt
     */
    private function __construct(
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
     * The receiver is never mutated. Moves are restricted to the legal lifecycle
     * predecessors ({@see self::ALLOWED_TRANSITIONS}); a terminal execution cannot
     * transition and an illegal predecessor pair throws.
     *
     * @throws CommandExecutionException
     *
     * @see docs/adr/0005-execution-transition-matrix.md
     */
    public function transition(CommandStatusInterface $next): self
    {
        if ($this->isTerminal()) {
            throw new CommandExecutionException(
                \sprintf('A terminal execution (%s) cannot transition.', $this->status->label()),
            );
        }

        $current = $this->status->label();
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (!\in_array($next->label(), $allowed, true)) {
            throw new CommandExecutionException(
                \sprintf('Illegal transition from "%s" to "%s".', $current, $next->label()),
            );
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
     * The receiver is never mutated. Throws when already terminal, when the current
     * status is not `executing` (the only legal predecessor of a terminal outcome),
     * or when the execution ends before it started.
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

        if ($this->status->label() !== CommandStatus::Executing->value) {
            throw new CommandExecutionException(
                \sprintf('Only an executing execution can be completed (current: %s).', $this->status->label()),
            );
        }

        if ($endedAt < $this->startedAt) {
            throw new CommandExecutionException('Execution ended before it started.');
        }

        $next = $result->status() === CommandResultStatus::Succeeded
            ? CommandStatus::Succeeded
            : CommandStatus::Failed;

        return new self($this->id, $this->definition, $next, $this->startedAt, $endedAt, $result);
    }
}
