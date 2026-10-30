<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of task lifecycle states.
 *
 * The transition matrix is encoded in {@see self::canTransitionTo()}. A task
 * may only move CREATED → RUNNING → one of the four terminal states; terminal
 * states admit no further transition. The literals are bound to
 * {@see TaskStatusVocabulary}.
 *
 * @see https://w3id.org/itpack/asyncit#TaskStatus
 * @see https://w3id.org/itpack/runit#Activity
 */
enum TaskStatus: string implements TaskStatusInterface
{
    /** @see https://w3id.org/itpack/asyncit#TaskCreated */
    case Created = TaskStatusVocabulary::CREATED;

    /** @see https://w3id.org/itpack/asyncit#TaskRunning */
    case Running = TaskStatusVocabulary::RUNNING;

    /** @see https://w3id.org/itpack/asyncit#TaskSucceeded */
    case Succeeded = TaskStatusVocabulary::SUCCEEDED;

    /** @see https://w3id.org/itpack/asyncit#TaskFailed */
    case Failed = TaskStatusVocabulary::FAILED;

    /** @see https://w3id.org/itpack/asyncit#TaskCancelled */
    case Cancelled = TaskStatusVocabulary::CANCELLED;

    /** @see https://w3id.org/itpack/asyncit#TaskTimedOut */
    case TimedOut = TaskStatusVocabulary::TIMED_OUT;

    public function canTransitionTo(TaskStatus $next): bool
    {
        return in_array($next, $this->allowedNext(), strict: true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    /**
     * @return list<self>
     */
    private function allowedNext(): array
    {
        return match ($this) {
            self::Created => [self::Running],
            self::Running => [self::Succeeded, self::Failed, self::Cancelled, self::TimedOut],
            self::Succeeded, self::Failed, self::Cancelled, self::TimedOut => [],
        };
    }
}
