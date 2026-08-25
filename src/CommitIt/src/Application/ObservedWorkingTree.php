<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Non-intrusive decorator that dispatches {@see RepositoryObserver} lifecycle
 * events around every working-tree operation.
 *
 * It mirrors the observation guarantees of {@see ObservedRepository}: the result
 * of the wrapped operation is never changed, the original {@see \Throwable} is
 * always rethrown, and any observer exception is swallowed on a separate,
 * non-recursive path so the observer can neither re-observe nor mask a real
 * failure. {@see ObservedRepository::getWorkingTree()} returns this decorator so
 * working-tree operations are observed like every other repository operation.
 *
 * @api
 */
final class ObservedWorkingTree implements WorkingTree
{
    public function __construct(
        private readonly WorkingTree $inner,
        private readonly RepositoryObserver $observer,
    ) {
    }

    /**
     * @return list<\ItPack\CommitIt\Domain\Value\WorktreeInfo>
     */
    public function listWorktrees(): array
    {
        return $this->run('listWorktrees', fn (): array => $this->inner->listWorktrees());
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $context = ['path' => $path->toString()];

        if ($name !== null) {
            $context['branch'] = $name->__toString();
        }

        $this->runVoid('add', fn () => $this->inner->add($path, $name), $context);
    }

    public function remove(RepositoryPath $path): void
    {
        $this->runVoid('remove', fn () => $this->inner->remove($path), ['path' => $path->toString()]);
    }

    /**
     * @template T
     * @param callable(): T $action
     * @param array<string, mixed> $context
     * @return T
     */
    private function run(string $operation, callable $action, array $context = [])
    {
        $this->notify(fn () => $this->observer->onStart($operation, $context));

        try {
            $result = $action();
        } catch (\Throwable $error) {
            $this->notify(fn () => $this->observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->notify(fn () => $this->observer->onSuccess($operation, $context));

        return $result;
    }

    /**
     * Void-returning variant of {@see self::run} so the generic never resolves to
     * `void` (which would make `return $result` illegal for non-void operations).
     *
     * @param callable(): void $action
     * @param array<string, mixed> $context
     */
    private function runVoid(string $operation, callable $action, array $context = []): void
    {
        $this->notify(fn () => $this->observer->onStart($operation, $context));

        try {
            $action();
        } catch (\Throwable $error) {
            $this->notify(fn () => $this->observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->notify(fn () => $this->observer->onSuccess($operation, $context));
    }

    /**
     * Routes observer dispatch failures to a separate, non-recursive path: an
     * observer must never re-observe nor alter the wrapped operation's outcome.
     */
    private function notify(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable) {
            // Observer failures are intentionally swallowed, not reobserved.
        }
    }
}
