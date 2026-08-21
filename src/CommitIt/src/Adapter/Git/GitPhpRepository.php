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
final class GitPhpRepository implements Repository
{
    public function __construct(private GitRepository $repository)
    {
    }

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
        try {
            $commit = $this->repository->getLastCommit();
        } catch (GitException $e) {
            // An empty repository has no commits to read; that is a normal state
            // rather than an error, so surface it as "no last commit".
            if (stripos($e->getMessage(), 'log') !== false) {
                return null;
            }

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
        return new GitPhpWorkingTree($this->repository);
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
        try {
            return $operation();
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Git operation failed');
        }
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
