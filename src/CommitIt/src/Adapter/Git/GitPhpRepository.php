<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\Commit as GitCommit;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\TagCleanupOptions;
use ItPack\CommitIt\Domain\Value\BranchInfo;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagInfo;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\Domain\Value\TagType;

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
    private const string FETCH_REF = '(fetch)';

    private ?GitPhpWorkingTree $workingTree = null;

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

    /**
     * @throws VcsOperationException
     */
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

    public function getBranchInfo(BranchName $name): ?BranchInfo
    {
        return $this->run(function () use ($name): ?BranchInfo {
            $lines = $this->repository->execute(
                'for-each-ref',
                '--format=%(refname:short)|%(objectname)|%(HEAD)|%(upstream:short)',
                'refs/heads/' . (string) $name,
            );

            if ($lines === []) {
                return null;
            }

            return $this->parseBranchInfo($lines[0], (string) $name);
        });
    }

    public function searchBranches(BranchQuery $query): array
    {
        return $this->run(function () use ($query): array {
            $args = ['for-each-ref', '--format=%(refname:short)|%(objectname)|%(HEAD)|%(upstream:short)'];
            $refSpecs = [];

            if ($query->currentOnly) {
                $current = $this->repository->execute('symbolic-ref', '--short', 'HEAD');
                $currentBranch = $current === [] ? '' : trim($current[0]);

                if ($currentBranch === '') {
                    return [];
                }

                $refSpecs[] = 'refs/heads/' . $currentBranch;
            } elseif ($query->remoteOnly) {
                $refSpecs[] = 'refs/remotes/origin';
            } else {
                $refSpecs[] = 'refs/heads';
                $refSpecs[] = 'refs/remotes';
            }

            $pattern = $query->pattern;
            $results = [];

            foreach ($refSpecs as $refSpec) {
                $lines = $this->repository->execute(...[...$args, $refSpec]);

                foreach ($lines as $line) {
                    $parts = explode('|', $line, 4);
                    $branchName = $parts[0];
                    $isRemote = str_starts_with($refSpec, 'refs/remotes');

                    if ($this->matchesBranchQuery($branchName, $pattern, $isRemote)) {
                        $results[] = $this->parseBranchInfo($line, $branchName);
                    }
                }
            }

            return $results;
        });
    }

    private function parseBranchInfo(string $line, string $branchName): BranchInfo
    {
        $parts = explode('|', $line, 4);
        $isCurrent = ($parts[2] ?? '') !== '';
        $isRemote = str_contains($branchName, '/');
        $upstream = ($parts[3] ?? '') !== '' ? $parts[3] : null;

        return new BranchInfo(
            BranchName::from($branchName),
            CommitId::from($parts[1]),
            $isCurrent,
            $isRemote,
            $upstream,
        );
    }

    private function matchesBranchQuery(string $branchName, ?string $pattern, bool $isRemote): bool
    {
        if ($pattern !== null && !fnmatch($pattern, $branchName) && !fnmatch($pattern, basename($branchName))) {
            return false;
        }

        return true;
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

    public function getTagInfo(TagName $name): ?TagInfo
    {
        return $this->run(function () use ($name): ?TagInfo {
            $lines = $this->repository->execute(
                'for-each-ref',
                '--format=%(*objectname)|%(objectname)|%(*authorname)|(*authoremail)|(*authordate:iso)|%(objecttype)',
                'refs/tags/' . (string) $name,
            );

            if ($lines === []) {
                return null;
            }

            $firstLine = $lines[0];

            return $this->parseTagInfo($firstLine, (string) $name);
        });
    }

    public function cleanupTags(TagCleanupOptions $options): array
    {
        return $this->run(function () use ($options): array {
            $lines = $this->repository->execute(
                'for-each-ref',
                '--format=%(refname:short)|%(objectname)|%(*authorname)|(*authoremail)|(*authordate:iso8601)|%(objecttype)',
                'refs/tags',
            );

            $removed = [];
            $pattern = $options->pattern;
            $before = $options->before;

            foreach ($lines as $line) {
                $parts = explode('|', $line, 6);
                $tagNameStr = $parts[0];

                if ($pattern !== null && !fnmatch($pattern, $tagNameStr)) {
                    continue;
                }

                if ($before !== null) {
                    $dateStr = $parts[4];

                    if ($dateStr === '') {
                        continue;
                    }

                    $tagDate = DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateStr);

                    if ($tagDate === false) {
                        $tagDate = new DateTimeImmutable($dateStr);
                    }

                    if ($tagDate >= $before) {
                        continue;
                    }
                }

                $this->repository->execute('tag', '-d', '--end-of-options', $tagNameStr);
                $removed[] = TagName::from($tagNameStr);
            }

            return $removed;
        });
    }

    /**
     * @throws DateMalformedStringException
     * @throws GitException
     */
    private function parseTagInfo(string $line, string $tagNameStr): TagInfo
    {
        $parts = explode('|', $line, 6);
        $targetCommit = $parts[0] !== '' ? $parts[0] : ($parts[1] ?? '');
        $authorName = $parts[2] !== '' ? $parts[2] : null;
        $authorEmail = $parts[3] !== '' ? $parts[3] : null;
        $dateStr = $parts[4];
        $objectType = $parts[5];

        $message = null;
        $taggerName = null;
        $taggerEmail = null;
        $date = null;

        if ($objectType === 'tag') {
            $showOutput = $this->repository->execute('show', '--no-patch', '--format=%B|%an|%ae|%aI', 'refs/tags/' . $tagNameStr);

            if ($showOutput !== []) {
                $showParts = explode('|', $showOutput[0], 4);

                $message = trim($showParts[0]) !== '' ? $showParts[0] : null;

                if ($showParts[1] !== '') {
                    $taggerName = $showParts[1];
                }

                if ($showParts[2] !== '') {
                    $taggerEmail = $showParts[2];
                }

                if ($showParts[3] !== '') {
                    $parsed = \DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $showParts[3]);
                    $date = $parsed !== false ? $parsed : new DateTimeImmutable($showParts[3]);
                }
            }
        }

        $resolvedTaggerName = $taggerName ?? ($authorName ?? null);
        $resolvedTaggerEmail = $taggerEmail ?? ($authorEmail ?? null);

        return new TagInfo(
            TagName::from($tagNameStr),
            CommitId::from($targetCommit),
            $objectType === 'tag' ? TagType::Annotated : TagType::Lightweight,
            $message,
            $resolvedTaggerName,
            $resolvedTaggerEmail,
            $date,
        );
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
                if (!str_contains($line, self::FETCH_REF)) {
                    continue;
                }

                $parts = explode("\t", $line, 2);
                $name = $parts[0];
                $url = (string) preg_replace(
                    '/ ' . preg_quote(self::FETCH_REF, '/') . '$/',
                    '',
                    rtrim($parts[1] ?? ''),
                );

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

    public function execute(string $command, array $args = []): string
    {
        return $this->run(fn (): string => implode("\n", $this->repository->execute($command, ...$args)));
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
            throw new InvalidArgumentException('Repository working tree is not accessible.');
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
            throw new InvalidArgumentException('Staged path must be located inside the repository working tree.');
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
        return Commit::from(
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
