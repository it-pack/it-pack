<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Svn;

use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use ItPack\CommitIt\Contract\ProcessRunner;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * SVN working-copy support via `svn` subcommands executed through a
 * {@see ProcessRunner}.
 *
 * **SVN has no worktree concept.** The git-style worktree operations
 * ({@see add()}, {@see remove()}, {@see listWorktrees()}) throw
 * {@see SvnAdapterException} because they cannot be expressed portably in SVN.
 * SVN *does* support sparse checkouts via `--depth` flags, which
 * {@see setSparsePatterns()} / {@see getSparsePatterns()} /
 * {@see disableSparseCheckout()} map to.
 *
 * @api
 */
final class SvnPhpWorkingTree implements WorkingTree
{
    /**
     * @var list<SparsePattern>
     */
    private array $patterns = [];

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly RepositoryPath $path,
    ) {
    }

    /**
     * @return list<WorktreeInfo>
     */
    public function listWorktrees(): array
    {
        throw new SvnAdapterException('SVN does not support multiple worktrees. Use sparse-checkout patterns instead.');
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        throw new SvnAdapterException('SVN does not support adding worktrees. Use sparse-checkout patterns instead.');
    }

    public function remove(RepositoryPath $path): void
    {
        throw new SvnAdapterException('SVN does not support removing worktrees. Use sparse-checkout patterns instead.');
    }

    /**
     * @param list<SparsePattern> $patterns
     * @throws VcsOperationException
     */
    public function setSparsePatterns(array $patterns): void
    {
        $this->patterns = $patterns;

        $result = $this->runner->run(
            $this->path->toString(),
            ['svn', 'update', '--set-depth', 'empty', '--non-interactive'],
        );

        if (!$result->isSuccess()) {
            throw new SvnAdapterException('Failed to set sparse-checkout depth to empty: ' . $result->stderr);
        }
    }

    /**
     * @return list<SparsePattern>
     */
    public function getSparsePatterns(): array
    {
        return $this->patterns;
    }

    /**
     * @throws VcsOperationException
     */
    public function disableSparseCheckout(): void
    {
        $result = $this->runner->run(
            $this->path->toString(),
            ['svn', 'update', '--set-depth', 'infinity', '--non-interactive'],
        );

        if (!$result->isSuccess()) {
            throw new SvnAdapterException('Failed to restore full checkout depth: ' . $result->stderr);
        }

        $this->patterns = [];
    }

    /**
     * @return list<WorktreeInfo>
     */
    public function findOrphanedWorktrees(): array
    {
        return [];
    }

    public function cleanupOrphanedWorktrees(): int
    {
        return 0;
    }
}
