<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use RuntimeException;

/**
 * Thrown when a task already in a terminal state is driven to a *different*
 * terminal state (e.g. SUCCEEDED → FAILED).
 *
 * This is a conflicting completion per ADR-AI-001: the matrix forbids it, and
 * the original terminal outcome must stand. The exception preserves the task
 * id and both statuses so the coordinator can diagnose the conflict.
 *
 * @see https://w3id.org/itpack/asyncit#TaskStatus
  * @api

  */
final class ConflictingCompletionException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(
        public readonly TaskIdInterface $taskId,
        public readonly TaskStatus $from,
        public readonly TaskStatus $to,
    ) {
        parent::__construct(sprintf(
            'Task %s is already %s; conflicting transition to %s is rejected.',
            $taskId->value,
            $from->value,
            $to->value,
        ));
    }
}
