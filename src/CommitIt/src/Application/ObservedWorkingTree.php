<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

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
final readonly class ObservedWorkingTree implements WorkingTree
{
    use ObserverDispatch;

    public function __construct(
        private readonly WorkingTree $inner,
        private readonly RepositoryObserver $observer,
    ) {
    }

    /**
     * @return list<WorktreeInfo>
     */
    public function listWorktrees(): array
    {
        return $this->dispatchRun($this->observer, 'listWorktrees', fn (): array => $this->inner->listWorktrees());
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $context = ['path' => $path->toString()];

        if ($name !== null) {
            $context['branch'] = $name->__toString();
        }

        $this->dispatchRunVoid($this->observer, 'add', fn () => $this->inner->add($path, $name), $context);
    }

    public function remove(RepositoryPath $path): void
    {
        $this->dispatchRunVoid($this->observer, 'remove', fn () => $this->inner->remove($path), ['path' => $path->toString()]);
    }
}
