<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
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
final class GitPhpWorkingTree implements WorkingTree
{
    public function __construct(private GitRepository $repository)
    {
    }

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

            if (str_starts_with($line, 'worktree ')) {
                if ($current !== null) {
                    $blocks[] = $current;
                }

                $current = [
                    'path' => substr($line, 9),
                    'branch' => null,
                ];

                continue;
            }

            if (str_starts_with($line, 'branch ') && $current !== null) {
                /** @var string $branch */
                $branch = (string) preg_replace('/^refs\/heads\//', '', substr($line, 7));
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

    private function assertWithinRepositoryScope(RepositoryPath $path): void
    {
        $repoParent = dirname($this->repository->getRepositoryPath());
        $targetParent = dirname($path->toString());

        $realTargetParent = realpath($targetParent);

        if ($realTargetParent === false) {
            throw new \InvalidArgumentException(
                'Worktree parent directory does not exist: ' . $targetParent,
            );
        }

        $realRepoParent = realpath($repoParent);

        $separator = DIRECTORY_SEPARATOR;
        $inside = $realTargetParent === $realRepoParent
            || str_starts_with($realTargetParent . $separator, $realRepoParent . $separator);

        if ($realRepoParent === false || !$inside) {
            throw new \InvalidArgumentException(
                'Worktree path must be located inside the repository parent directory.',
            );
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function run(callable $operation)
    {
        try {
            return $operation();
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Worktree operation failed');
        }
    }
}
