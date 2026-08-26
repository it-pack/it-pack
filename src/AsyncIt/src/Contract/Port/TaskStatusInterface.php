<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Behavioural contract of the closed {@see TaskStatus} set.
 *
 * The scheduler/coordinator consults {@see self::canTransitionTo()} before
 * mutating a task's status so illegal and conflicting transitions are rejected
 * before they corrupt the lifecycle matrix.
 *
 * @see https://w3id.org/itpack/asyncit#TaskStatus
 */
interface TaskStatusInterface
{
    public function canTransitionTo(TaskStatus $next): bool;

    public function isTerminal(): bool;
}
