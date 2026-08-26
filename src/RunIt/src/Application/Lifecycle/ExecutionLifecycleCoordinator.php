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
 * The application-level lifecycle coordinator.
 *
 * The transition matrix itself is owned by the domain
 * ({@see ExecutionStatus::canTransitionTo()}); the coordinator applies it above
 * the aggregate and adds idempotent-completion semantics: a terminal execution
 * completed again with the same status is a no-op rather than an error, while a
 * repeat with a different terminal status is rejected. The aggregate enforces
 * the same matrix structurally, so an illegal completion cannot be smuggled
 * past the coordinator by calling the record directly.
 *
 * The coordinator produces the new immutable execution record through the domain's own
 * completion method and never mutates the receiver.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionLifecycleCoordinator implements ExecutionLifecycleCoordinatorInterface
{
    /**
     * Returns whether the transition from one status to another is permitted.
     *
     * This is the pure, side-effect-free companion to {@see self::transition()} and is
     * used by tests and tooling to reason about the matrix without completing an execution.
     */
    public static function isAllowed(ExecutionStatusInterface $from, ExecutionStatusInterface $to): bool
    {
        return $from->canTransitionTo($to);
    }

    public function transition(
        ExecutionInterface $execution,
        ExecutionResultInterface $result,
        InstantInterface $endedAt
    ): ExecutionInterface {
        $from = $execution->status();
        $to = $result->status();

        if ($from->isTerminal()) {
            if ($from === $to) {
                return $execution;
            }

            throw ConflictingCompletionException::forConflict($from, $to);
        }

        if (!$from->canTransitionTo($to)) {
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
}
