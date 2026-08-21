<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * Operations on a repository's working trees (git worktree support).
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
}
