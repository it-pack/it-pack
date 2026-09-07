<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Contract\Exception\WorktreeAccessDeniedException;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Access-control policy for working-tree operations.
 *
 * Formalizes the path-scope and isolation invariants described in AGENTS.md:
 * a worktree path must never escape the repository's parent scope, and
 * adding/removing a worktree must not corrupt the primary checkout.
 *
 * Implementations decide whether a given path is acceptable for an operation.
 * The core domain and adapter delegate to this port so that security rules can
 * be customized without modifying adapter internals.
 *
 * @api
 */
interface WorktreeAccessPolicy
{
    /**
     * Validates that a new worktree may be added at the given path.
     *
     * @throws WorktreeAccessDeniedException When the path violates this policy.
     */
    public function validateAdd(RepositoryPath $path): void;

    /**
     * Validates that a worktree at the given path may be removed.
     *
     * @throws WorktreeAccessDeniedException When the path violates this policy.
     */
    public function validateRemove(RepositoryPath $path): void;
}
