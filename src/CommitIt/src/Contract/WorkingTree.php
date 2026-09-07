<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * Operations on a repository's working trees (git worktree and sparse-checkout support).
 *
 * Paths are resolved relative to the repository root; a worktree path must never
 * escape the repository's parent scope (enforced by the adapter).
 *
 * @api
 */
interface WorkingTree
{
    /**
     * @return list<WorktreeInfo>
     */
    public function listWorktrees(): array;

    public function add(RepositoryPath $path, ?BranchName $name = null): void;

    public function remove(RepositoryPath $path): void;

    /**
     * Enable sparse-checkout mode (if not already active) and set the given patterns.
     *
     * Replaces any existing sparse-checkout patterns. To turn sparse-checkout
     * off entirely, use {@see disableSparseCheckout()}.
     *
     * @param list<SparsePattern> $patterns
     */
    public function setSparsePatterns(array $patterns): void;

    /**
     * Returns the active sparse-checkout patterns.
     *
     * Returns an empty list when sparse-checkout is not configured.
     *
     * @return list<SparsePattern>
     */
    public function getSparsePatterns(): array;

    /**
     * Disable sparse-checkout mode and restore a full checkout.
     */
    public function disableSparseCheckout(): void;
}
