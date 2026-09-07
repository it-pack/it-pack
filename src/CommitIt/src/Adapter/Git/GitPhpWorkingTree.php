<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Contract\WorktreeAccessPolicy;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Service\DefaultWorktreeAccessPolicy;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * Git worktree support via the wrapped {@see GitRepository}'s `execute()`
 * passthrough. Composition, not subclassing: we never extend `GitRepository`,
 * only call `git worktree ...` through it.
 *
 * Worktree paths are guarded by {@see WorktreeAccessPolicy} so they can never
 * escape the repository parent directory (a security requirement from AGENTS.md).
 *
 * @api
 */
final class GitPhpWorkingTree implements WorkingTree
{
    private const string WORKTREE_PREFIX = 'worktree ';
    private const string BRANCH_PREFIX = 'branch ';
    private const string GITDIR_PREFIX = 'gitdir ';

    private readonly WorktreeAccessPolicy $policy;

    /**
     * @param WorktreeAccessPolicy|null $policy When null, a {@see DefaultWorktreeAccessPolicy}
     *   is created from the repository path.
     */
    public function __construct(
        private readonly GitRepository $repository,
        ?WorktreeAccessPolicy $policy = null,
    ) {
        $this->policy = $policy ?? new DefaultWorktreeAccessPolicy($repository->getRepositoryPath());
    }

    /**
     * @throws VcsOperationException
     */
    public function listWorktrees(): array
    {
        return $this->run(function (): array {
            $output = $this->repository->execute('worktree', 'list', '--porcelain');

            return self::parseWorktreeList($output);
        }, 'Failed to list worktrees');
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $this->policy->validateAdd($path);

        $args = ['worktree', 'add'];

        if ($name !== null) {
            $args[] = '-b';
            $args[] = (string) $name;
        }

        $args[] = $path->toString();

        $this->run(function () use ($args): void {
            $this->repository->execute(...$args);
        }, 'Failed to add worktree');
    }

    public function remove(RepositoryPath $path): void
    {
        $this->policy->validateRemove($path);

        $this->run(function () use ($path): void {
            $this->repository->execute('worktree', 'remove', '--force', $path->toString());
        }, 'Failed to remove worktree');
    }

    /**
     * @param list<SparsePattern> $patterns
     */
    public function setSparsePatterns(array $patterns): void
    {
        $patternArgs = array_map(static fn (SparsePattern $p): string => (string) $p, $patterns);

        $this->run(function () use ($patternArgs): void {
            $this->repository->execute('sparse-checkout', 'init');

            if ($patternArgs !== []) {
                $this->repository->execute('sparse-checkout', 'set', ...$patternArgs);
            }
        }, 'Failed to set sparse checkout patterns');
    }

    /**
     * @return list<SparsePattern>
     */
    public function getSparsePatterns(): array
    {
        return $this->run(function (): array {
            try {
                $output = $this->repository->execute('sparse-checkout', 'list');
            } catch (GitException) {
                return [];
            }

            $patterns = [];

            foreach ($output as $line) {
                if ($line === '') {
                    continue;
                }

                $patterns[] = SparsePattern::from($line);
            }

            return $patterns;
        }, 'Failed to get sparse checkout patterns');
    }

    public function disableSparseCheckout(): void
    {
        $this->run(function (): void {
            $this->repository->execute('sparse-checkout', 'disable');
        }, 'Failed to disable sparse checkout');
    }

    /**
     * Returns worktrees whose `gitdir` metadata path does not exist on disk.
     *
     * @return list<WorktreeInfo>
     * @throws \ItPack\CommitIt\Contract\Exception\VcsOperationException
     */
    public function findOrphanedWorktrees(): array
    {
        $worktrees = $this->listWorktrees();

        return array_values(array_filter(
            $worktrees,
            static fn (WorktreeInfo $wt): bool => $wt->orphaned,
        ));
    }

    /**
     * Prunes stale worktree metadata. Returns the count of orphaned worktrees
     * found before pruning.
     */
    public function cleanupOrphanedWorktrees(): int
    {
        $orphaned = $this->findOrphanedWorktrees();

        if ($orphaned === []) {
            return 0;
        }

        $this->run(function (): void {
            $this->repository->execute('worktree', 'prune');
        }, 'Failed to prune orphaned worktrees');

        return count($orphaned);
    }

    /**
     * @param array<string> $lines
     * @return list<WorktreeInfo>
     */
    private static function parseWorktreeList(array $lines): array
    {
        /** @var list<array{path: string, branch: ?string, gitdir: ?string}> $blocks */
        $blocks = [];
        /** @var array{path: string, branch: ?string, gitdir: ?string}|null $current */
        $current = null;

        foreach ($lines as $line) {
            if ($line === '') {
                if ($current !== null) {
                    $blocks[] = $current;
                    $current = null;
                }

                continue;
            }

            if (str_starts_with($line, self::WORKTREE_PREFIX)) {
                if ($current !== null) {
                    $blocks[] = $current;
                }

                $current = [
                    'path' => substr($line, strlen(self::WORKTREE_PREFIX)),
                    'branch' => null,
                    'gitdir' => null,
                ];

                continue;
            }

            if (str_starts_with($line, self::BRANCH_PREFIX) && $current !== null) {
                $branch = (string) preg_replace('/^refs\/heads\//', '', substr($line, strlen(self::BRANCH_PREFIX)));
                $current['branch'] = $branch === '' ? null : $branch;

                continue;
            }

            if (str_starts_with($line, self::GITDIR_PREFIX) && $current !== null) {
                $current['gitdir'] = substr($line, strlen(self::GITDIR_PREFIX));
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        $isMain = true;
        $result = [];

        foreach ($blocks as $block) {
            $orphaned = $block['gitdir'] !== null
                && !file_exists($block['gitdir']);

            $result[] = new WorktreeInfo($block['path'], $block['branch'], $isMain, $orphaned);
            $isMain = false;
        }

        return $result;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws VcsOperationException
     */
    private function run(callable $operation, ?string $context = null)
    {
        return GitExceptionRunner::run($operation, $context ?? 'Worktree operation failed');
    }
}
