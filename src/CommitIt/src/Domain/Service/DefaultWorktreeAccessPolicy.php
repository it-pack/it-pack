<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use ItPack\CommitIt\Contract\WorktreeAccessPolicy;
use ItPack\CommitIt\Domain\Exception\WorktreeAccessDeniedException;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Default implementation of {@see WorktreeAccessPolicy} that enforces the
 * path-scope invariant from AGENTS.md: a worktree path must never escape the
 * repository's parent directory.
 *
 * This formalizes the `assertWithinRepositoryScope` check previously embedded
 * in the git adapter, making it a standalone, testable, and replaceable rule.
 *
 * @api
 */
final readonly class DefaultWorktreeAccessPolicy implements WorktreeAccessPolicy
{
    public function __construct(
        private readonly string $repositoryPath,
    ) {
    }

    public function validateAdd(RepositoryPath $path): void
    {
        $this->assertWithinScope($path);
    }

    public function validateRemove(RepositoryPath $path): void
    {
        $this->assertWithinScope($path);
    }

    private function assertWithinScope(RepositoryPath $path): void
    {
        $repoParent = dirname($this->repositoryPath);
        $targetParent = dirname($path->toString());

        $realTargetParent = realpath($targetParent);

        if ($realTargetParent === false) {
            throw new WorktreeAccessDeniedException(
                'Worktree parent directory does not exist: ' . $targetParent,
            );
        }

        $realRepoParent = realpath($repoParent);

        if ($realRepoParent === false) {
            throw new WorktreeAccessDeniedException(
                'Repository parent directory is not accessible: ' . $repoParent,
            );
        }

        $separator = DIRECTORY_SEPARATOR;
        $inside = $realTargetParent === $realRepoParent
            || str_starts_with($realTargetParent . $separator, $realRepoParent . $separator);

        if (!$inside) {
            throw new WorktreeAccessDeniedException(
                'Worktree path must be located inside the repository parent directory.',
            );
        }
    }
}
