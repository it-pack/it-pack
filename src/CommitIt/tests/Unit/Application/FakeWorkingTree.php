<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Minimal {@see WorkingTree} test double.
 */
final class FakeWorkingTree implements WorkingTree
{
    /**
     * @return list<\ItPack\CommitIt\Domain\Value\WorktreeInfo>
     */
    public function listWorktrees(): array
    {
        return [];
    }

    public function add(RepositoryPath $path, ?\ItPack\CommitIt\Domain\Value\BranchName $name = null): void
    {
    }

    public function remove(RepositoryPath $path): void
    {
    }
}
