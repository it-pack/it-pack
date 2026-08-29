<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\Commit as GitCommit;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;

/**
 * Git implementation of the vendor-neutral {@see Repository} contract, backed by
 * `czproject/git-php`. Every backend call is funnelled through {@see GitExceptionMap}
 * so consumers only ever see the CommitIt exception taxonomy.
 *
 * @api
 */
// Intentionally NOT `readonly`: the lazy `$workingTree` memo at line 34 is mutated after construction.
final class GitPhpRepository implements Repository
{
    public function __construct(private GitRepository $repository)
    {
    }

    private ?GitPhpWorkingTree $workingTree = null;

    public function getPath(): RepositoryPath
    {
        return RepositoryPath::from($this->repository->getRepositoryPath());
    }

    public function hasChanges(): bool
    {
        return $this->run(fn (): bool => $this->repository->hasChanges());
    }

    public function stage(string $file): void
    {
        $this->assertWithinWorkingTree($file);

        $this->run(function () use ($file): void {
            $this->repository->addFile($file);
        });
    }

    public function stageAll(): void
    {
        $this->run(function (): void {
            $this->repository->addAllChanges();
        });
    }

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $options ??= CommitOptions::default();

        $params = [];

        if ($options->authorName !== null && $options->authorEmail !== null) {
            $params['--author'] = $options->authorName . ' <' . $options->authorEmail . '>';
        }

        if ($options->authorDate !== null) {
            $params['--date'] = $options->authorDate->format(\DateTimeInterface::ATOM);
        }

        if ($options->allowEmpty) {
            $params[] = '--allow-empty';
        }

        if ($options->amend) {
            $params[] = '--amend';
        }

        return $this->run(function () use ($message, $params): CommitId {
            $this->repository->commit($message->toString(), $params === [] ? null : $params);

            return CommitId::from($this->repository->getLastCommitId()->toString());
        });
    }

    public function getLastCommit(): ?Commit
    {
        // Detect "no commits yet" explicitly rather than by matching the command
        // keyword in git's error text (which is locale-fragile, see REVIEW.md F7).
        try {
            $this->repository->execute('rev-parse', '--verify', 'HEAD');
        } catch (GitException) {
            return null;
        }

        try {
            $commit = $this->repository->getLastCommit();
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Failed to read last commit');
        }

        return self::mapCommit($commit);
    }

    public function getCommit(CommitId $id): Commit
    {
        return $this->run(fn (): Commit => self::mapCommit($this->repository->getCommit($id->__toString())));
    }

    public function createBranch(BranchName $name): void
    {
        $this->run(function () use ($name): void {
            $this->repository->createBranch((string) $name);
        });
    }

    public function checkoutBranch(BranchName $name): void
    {
        $this->run(function () use ($name): void {
            $this->repository->checkout((string) $name);
        });
    }

    public function deleteBranch(BranchName $name): void
    {
        $this->run(function () use ($name): void {
            $this->repository->removeBranch((string) $name);
        });
    }

    public function listBranches(): array
    {
        return $this->run(function (): array {
            $branches = $this->repository->getLocalBranches() ?? [];

            return array_values(array_map(static fn (string $name): BranchName => BranchName::from($name), $branches));
        });
    }

    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $this->run(function () use ($name, $target): void {
            if ($target === null) {
                $this->repository->createTag((string) $name);

                return;
            }

            $this->repository->execute('tag', '--end-of-options', (string) $name, $target->__toString());
        });
    }

    public function deleteTag(TagName $name): void
    {
        $this->run(function () use ($name): void {
            $this->repository->removeTag((string) $name);
        });
    }

    public function listTags(): array
    {
        return $this->run(function (): array {
            $tags = $this->repository->getTags() ?? [];

            return array_values(array_map(static fn (string $name): TagName => TagName::from($name), $tags));
        });
    }

    public function addRemote(RemoteName $name, string $url): void
    {
        $this->run(function () use ($name, $url): void {
            $this->repository->addRemote((string) $name, $url);
        });
    }

    public function removeRemote(RemoteName $name): void
    {
        $this->run(function () use ($name): void {
            $this->repository->removeRemote((string) $name);
        });
    }

    public function listRemotes(): array
    {
        return $this->run(function (): array {
            $lines = $this->repository->execute('remote', '-v');
            $remotes = [];

            foreach ($lines as $line) {
                if (!str_contains($line, '(fetch)')) {
                    continue;
                }

                $parts = explode("\t", $line, 2);
                $name = $parts[0];
                $url = (string) preg_replace('/ \(fetch\)$/', '', rtrim($parts[1] ?? ''));

                $remotes[$name] = $url;
            }

            return $remotes;
        });
    }

    public function fetch(?RemoteName $remote = null): void
    {
        $this->run(function () use ($remote): void {
            $this->repository->fetch($remote?->__toString());
        });
    }

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->run(function () use ($remote, $branch): void {
            $args = ['pull'];

            if ($remote !== null) {
                $args[] = (string) $remote;
            }

            if ($branch !== null) {
                $args[] = (string) $branch;
            }

            $this->repository->execute(...$args);
        });
    }

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->run(function () use ($remote, $branch): void {
            $args = ['push'];

            if ($remote !== null) {
                $args[] = (string) $remote;
            }

            if ($branch !== null) {
                $args[] = (string) $branch;
            }

            $this->repository->execute(...$args);
        });
    }

    public function getWorkingTree(): WorkingTree
    {
        return $this->workingTree ??= new GitPhpWorkingTree($this->repository);
    }

    /**
     * Ensures a staged path resolves inside the repository working tree. The
     * check is enforced by CommitIt (not delegated to git) so the scope
     * invariant holds for every backend; it rejects absolute paths and `..`
     * traversals that would escape the repository.
     */
    private function assertWithinWorkingTree(string $file): void
    {
        $repoRoot = $this->repository->getRepositoryPath();
        $realRepoRoot = realpath($repoRoot);

        if ($realRepoRoot === false) {
            throw new \InvalidArgumentException('Repository working tree is not accessible.');
        }

        $target = $file;

        if (!str_starts_with($target, DIRECTORY_SEPARATOR)) {
            $target = $realRepoRoot . DIRECTORY_SEPARATOR . $target;
        }

        $realTarget = realpath($target);

        if ($realTarget === false) {
            // The path may point at a not-yet-tracked file; canonicalize by hand
            // so traversal escapes are still detected without requiring existence.
            $realTarget = $this->canonicalizePath($target);
        }

        $separator = DIRECTORY_SEPARATOR;
        $inside = $realTarget === $realRepoRoot
            || str_starts_with($realTarget . $separator, $realRepoRoot . $separator);

        if (!$inside) {
            throw new \InvalidArgumentException('Staged path must be located inside the repository working tree.');
        }
    }

    /**
     * Collapses `.` and `..` segments without touching the filesystem, so path
     * scope can be verified even for files that do not exist yet.
     */
    private function canonicalizePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $segments = preg_split('#[/\\\\]+#', $path, -1, PREG_SPLIT_NO_EMPTY);

        if ($segments === false) {
            $segments = [];
        }

        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($stack);

                continue;
            }

            $stack[] = $segment;
        }

        return ($isAbsolute ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $stack);
    }

    public function execute(string $command, array $args = []): string
    {
        return $this->run(fn (): string => implode("\n", $this->repository->execute($command, ...$args)));
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function run(callable $operation)
    {
        return GitExceptionRunner::run($operation, 'Git operation failed');
    }

    private static function mapCommit(GitCommit $commit): Commit
    {
        return new Commit(
            CommitId::from($commit->getId()->toString()),
            $commit->getSubject(),
            $commit->getBody() !== '' ? $commit->getBody() : null,
            $commit->getAuthorName() ?? '',
            $commit->getAuthorEmail(),
            $commit->getAuthorDate(),
            $commit->getCommitterName() ?? '',
            $commit->getCommitterEmail(),
            $commit->getCommitterDate(),
        );
    }
}
