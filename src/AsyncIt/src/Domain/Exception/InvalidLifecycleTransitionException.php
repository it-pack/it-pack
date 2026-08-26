<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskId;
use RuntimeException;

/**
 * Thrown when a task is driven along a transition the {@see TaskStatus} matrix
 * does not permit (e.g. CREATED → SUCCEEDED, or any move out of a terminal
 * state that is not a conflicting terminal-to-terminal move).
 *
 * @see https://w3id.org/itpack/asyncit#TaskStatus
 */
final class InvalidLifecycleTransitionException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(
        public readonly TaskId $taskId,
        public readonly TaskStatus $from,
        public readonly TaskStatus $to,
    ) {
        parent::__construct(sprintf(
            'Task %s cannot transition from %s to %s.',
            $taskId->value,
            $from->value,
            $to->value,
        ));
    }
}
