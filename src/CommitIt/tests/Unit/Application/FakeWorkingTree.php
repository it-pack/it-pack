<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;

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

    /**
     * @param list<SparsePattern> $patterns
     */
    public function setSparsePatterns(array $patterns): void
    {
    }

    /**
     * @return list<SparsePattern>
     */
    public function getSparsePatterns(): array
    {
        return [];
    }

    public function disableSparseCheckout(): void
    {
    }
}
