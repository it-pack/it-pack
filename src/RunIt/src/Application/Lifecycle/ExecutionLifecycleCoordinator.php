<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Lifecycle;

use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Domain\Exception\ConflictingCompletionException;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;

/**
 * The closed transition matrix for the execution lifecycle.
 *
 * `created` may only move to `running` or `cancelled`. `running` may only move to a
 * terminal status. Terminal statuses are immutable except for an idempotent repeat of
 * the same status, which is a no-op rather than an error. Any other transition, or a
 * repeat with a different terminal status, is rejected.
 *
 * The coordinator produces the new immutable execution record through the domain's own
 * completion method and never mutates the receiver.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionLifecycleCoordinator implements ExecutionLifecycleCoordinatorInterface
{
    /**
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'created' => ['running', 'cancelled'],
        'running' => ['succeeded', 'failed', 'cancelled', 'timed_out'],
    ];

    public function transition(ExecutionInterface $execution, ExecutionResultInterface $result, InstantInterface $endedAt): ExecutionInterface
    {
        $from = $execution->status();
        $to = $result->status();

        if ($from->isTerminal()) {
            if ($from === $to) {
                return $execution;
            }

            throw ConflictingCompletionException::forConflict($from, $to);
        }

        if (!isset(self::ALLOWED[$from->name()]) || !in_array($to->name(), self::ALLOWED[$from->name()], true)) {
            throw InvalidLifecycleTransitionException::forTransition($from, $to);
        }

        return $execution->complete($result, $endedAt);
    }

    public function start(ExecutionInterface $execution): ExecutionInterface
    {
        if ($execution->status() !== ExecutionStatus::Created) {
            throw InvalidLifecycleTransitionException::forTransition($execution->status(), ExecutionStatus::Running);
        }

        return $execution->beginRunning();
    }

    /**
     * Returns whether the transition from one status to another is permitted.
     *
     * This is the pure, side-effect-free companion to {@see self::transition()} and is
     * used by tests and tooling to reason about the matrix without completing an execution.
     */
    public static function isAllowed(ExecutionStatusInterface $from, ExecutionStatusInterface $to): bool
    {
        if ($from->isTerminal()) {
            return $from === $to;
        }

        return isset(self::ALLOWED[$from->name()]) && in_array($to->name(), self::ALLOWED[$from->name()], true);
    }
}
