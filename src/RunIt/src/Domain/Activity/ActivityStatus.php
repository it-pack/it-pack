<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\TerminalStatusBehavior;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;

/**
 * The closed set of activity lifecycle statuses.
 *
 * Mirrors the execution status set. Terminal statuses (`succeeded`, `failed`,
 * `cancelled`, `timed_out`) cannot be left once entered, and — mirroring the
 * execution matrix — completing an activity to a non-terminal status is
 * rejected by the aggregate.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
enum ActivityStatus: string implements ActivityStatusInterface
{
    use TerminalStatusBehavior;

    case Created = 'created';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    /**
     * The closed transition matrix, mirroring
     * {@see \ItPack\RunIt\Domain\Execution\ExecutionStatus}.
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

    public function canTransitionTo(ActivityStatusInterface $to): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        return isset(self::ALLOWED_TRANSITIONS[$this->value])
            && in_array($to->label(), self::ALLOWED_TRANSITIONS[$this->value], strict: true);
    }
}
