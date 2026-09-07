<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\RepositoryOperation;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;
use Throwable;

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
        private WorkingTree $inner,
        private RepositoryObserver $observer,
    ) {
    }

    /**
     * @return list<WorktreeInfo>
     * @throws Throwable
     */
    public function listWorktrees(): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::ListWorktrees->value,
            fn (): array => $this->inner->listWorktrees(),
        );
    }

    /**
     * @throws Throwable
     */
    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $context = ['path' => $path->toString()];

        if ($name !== null) {
            $context['branch'] = $name->__toString();
        }

        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::AddWorktree->value,
            fn () => $this->inner->add($path, $name),
            $context,
        );
    }

    /**
     * @throws \Throwable
     */
    public function remove(RepositoryPath $path): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::RemoveWorktree->value,
            fn () => $this->inner->remove($path),
            ['path' => $path->toString()],
        );
    }

    /**
     * @param list<SparsePattern> $patterns
     * @throws Throwable
     */
    public function setSparsePatterns(array $patterns): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::SetSparsePatterns->value,
            fn () => $this->inner->setSparsePatterns($patterns),
            ['patterns' => array_map(static fn (SparsePattern $p): string => (string) $p, $patterns)],
        );
    }

    /**
     * @return list<SparsePattern>
     * @throws Throwable
     */
    public function getSparsePatterns(): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetSparsePatterns->value,
            fn (): array => $this->inner->getSparsePatterns(),
        );
    }

    /**
     * @throws Throwable
     */
    public function disableSparseCheckout(): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::DisableSparseCheckout->value,
            fn () => $this->inner->disableSparseCheckout(),
        );
    }
}
