<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * Git worktree support via the wrapped {@see GitRepository}'s `execute()`
 * passthrough. Composition, not subclassing: we never extend `GitRepository`,
 * only call `git worktree ...` through it.
 *
 * Worktree paths are guarded so they can never escape the repository parent
 * directory (a security requirement from AGENTS.md).
 *
 * @api
 */
final readonly class GitPhpWorkingTree implements WorkingTree
{
    private const string WORKTREE_PREFIX = 'worktree ';
    private const string BRANCH_PREFIX = 'branch ';

    public function __construct(private GitRepository $repository)
    {
    }

    /**
     * @throws VcsOperationException
     */
    public function listWorktrees(): array
    {
        try {
            $output = $this->repository->execute('worktree', 'list', '--porcelain');
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Failed to list worktrees');
        }

        return self::parseWorktreeList($output);
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $this->assertWithinRepositoryScope($path);

        $args = ['worktree', 'add'];

        if ($name !== null) {
            $args[] = '-b';
            $args[] = (string) $name;
        }

        $args[] = $path->toString();

        $this->run(function () use ($args): void {
            $this->repository->execute(...$args);
        });
    }

    public function remove(RepositoryPath $path): void
    {
        $this->assertWithinRepositoryScope($path);

        $this->run(function () use ($path): void {
            $this->repository->execute('worktree', 'remove', $path->toString());
        });
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

    private function assertWithinRepositoryScope(RepositoryPath $path): void
    {
        $repoParent = dirname($this->repository->getRepositoryPath());
        $targetParent = dirname($path->toString());

        $realTargetParent = realpath($targetParent);

        if ($realTargetParent === false) {
            throw new InvalidArgumentException(
                'Worktree parent directory does not exist: ' . $targetParent,
            );
        }

        $realRepoParent = realpath($repoParent);

        $separator = DIRECTORY_SEPARATOR;
        $inside = $realTargetParent === $realRepoParent
            || str_starts_with($realTargetParent . $separator, $realRepoParent . $separator);

        if (!$inside) {
            throw new InvalidArgumentException(
                'Worktree path must be located inside the repository parent directory.',
            );
        }
    }

    /**
     * @param array<string> $lines
     * @return list<WorktreeInfo>
     */
    private static function parseWorktreeList(array $lines): array
    {
        /** @var list<array{path: string, branch: ?string}> $blocks */
        $blocks = [];
        /** @var array{path: string, branch: ?string}|null $current */
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
                ];

                continue;
            }

            if ($current !== null && str_starts_with($line, self::BRANCH_PREFIX)) {
                $branch = (string) preg_replace('/^refs\/heads\//', '', substr($line, strlen(self::BRANCH_PREFIX)));
                $current['branch'] = $branch === '' ? null : $branch;
            }
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        $isMain = true;
        $result = [];

        foreach ($blocks as $block) {
            $result[] = new WorktreeInfo($block['path'], $block['branch'], $isMain);
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
