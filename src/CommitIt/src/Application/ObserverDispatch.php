<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;

/**
 * Shared observer dispatch for the {@see ObservedRepository} and
 * {@see ObservedWorkingTree} decorators.
 *
 * Both decorators wrap every operation in the same lifecycle: notify
 * {@see RepositoryObserver::onStart}, run the operation, then notify
 * {@see RepositoryObserver::onSuccess} or {@see RepositoryObserver::onFailure}.
 * This trait holds that single implementation so the two decorators do not
 * duplicate it.
 *
 * The observer is passed in rather than read from a property so the trait
 * cannot collide with the `private readonly RepositoryObserver $observer` held
 * by each decorator. The trait carries no state of its own.
 *
 * Observer exceptions are swallowed on a separate, non-recursive path: an
 * observer must never re-observe nor alter the wrapped operation's outcome, and
 * the original {@see Throwable} is always rethrown on failure.
 *
 * @internal
 */
trait ObserverDispatch
{
    /**
     * @template T
     * @param callable(): T $action
     * @param array<string, mixed> $context
     * @return T
     */
    private function dispatchRun(
        RepositoryObserver $observer,
        string $operation,
        callable $action,
        array $context = [],
    ): mixed {
        $this->dispatchNotify(fn () => $observer->onStart($operation, $context));

        try {
            $result = $action();
        } catch (\Throwable $error) {
            $this->dispatchNotify(fn () => $observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->dispatchNotify(fn () => $observer->onSuccess($operation, $context));

        return $result;
    }

    /**
     * Void-returning variant of {@see self::dispatchRun} so the generic never
     * resolves to `void` (which would make `return $result` illegal for non-void
     * operations).
     *
     * @param callable(): void $action
     * @param array<string, mixed> $context
     */
    private function dispatchRunVoid(
        RepositoryObserver $observer,
        string $operation,
        callable $action,
        array $context = [],
    ): void {
        $this->dispatchNotify(fn () => $observer->onStart($operation, $context));

        try {
            $action();
        } catch (\Throwable $error) {
            $this->dispatchNotify(fn () => $observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->dispatchNotify(fn () => $observer->onSuccess($operation, $context));
    }

    /**
     * Routes observer dispatch failures to a separate, non-recursive path: an
     * observer must never re-observe nor alter the wrapped operation's outcome.
     */
    private function dispatchNotify(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable) {
            // Observer failures are intentionally swallowed, not reobserved.
        }
    }
}
