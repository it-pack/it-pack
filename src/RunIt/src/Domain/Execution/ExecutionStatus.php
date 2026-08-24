<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\TerminalStatusBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;

/**
 * The closed set of execution lifecycle statuses.
 *
 * Terminal statuses (`succeeded`, `failed`, `cancelled`, `timed_out`) cannot be
 * left once entered. The transition matrix is owned by this enum: it is the
 * single source of truth consulted by both the aggregate
 * ({@see Execution::complete()}) and the application-level lifecycle
 * coordinator.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum ExecutionStatus: string implements ExecutionStatusInterface
{
    use TerminalStatusBehavior;

    case Created = 'created';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    /**
     * The closed transition matrix.
     *
     * `created` may only move to `running` or `cancelled`. `running` may only
     * move to a terminal status. Terminal statuses are immutable; an idempotent
     * repeat of the same status is handled above the aggregate as a no-op and
     * is therefore not part of the matrix.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED_TRANSITIONS = [
        self::Created->value => [
            self::Running->value,
            self::Cancelled->value,
        ],
        self::Running->value => [
            self::Succeeded->value,
            self::Failed->value,
            self::Cancelled->value,
            self::TimedOut->value,
        ],
    ];

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Returns whether a non-terminal status may legally move to `$to`.
     *
     * This is the pure, side-effect-free statement of the lifecycle matrix and
     * is shared by the execution aggregate and the application coordinator so
     * both enforce exactly the same rules.
     */
    public function canTransitionTo(ExecutionStatusInterface $to): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        return isset(self::ALLOWED_TRANSITIONS[$this->value])
            && in_array($to->label(), self::ALLOWED_TRANSITIONS[$this->value], true);
    }
}
