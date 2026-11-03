<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Svn;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Behavior\Adapter\ExtendedCommitBehavior;
use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use ItPack\CommitIt\Contract\ExtendedRepository;
use ItPack\CommitIt\Contract\ProcessRunner;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\TagCleanupOptions;
use ItPack\CommitIt\Domain\Value\BranchInfo;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\ConfigScope;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagInfo;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\Domain\Value\TagType;
use Stringable;
use ValueError;

/**
 * Subversion implementation of the vendor-neutral {@see Repository} contract.
 *
 * SVN maps onto CommitIt concepts as follows:
 *
 *  - **WorkingTree** — the SVN working copy. {@see SvnPhpWorkingTree} delegates
 *    to `svn` subcommands via the injected {@see ProcessRunner}.
 *  - **Commits** — SVN uses a single `svn commit`. SVN has no staging area;
 *    {@see stage()} / {@see stageAll()} are no-ops that record intent, and
 *    {@see commit()} commits all changes directly.
 *  - **Branches** — SVN directories under `^/branches/`. `svn copy` creates them.
 *  - **Tags** — SVN directories under `^/tags/`. `svn copy` creates them.
 *  - **Remotes** — SVN URLs are treated as a single implicit remote (URL-based
 *    VCS); {@see RemotePort} operations map to `svn switch --relocate` and
 *    `svn update`-style fetches.
 *
 * The adapter is NOT tied to the `svn` binary being installed at construction;
 * it defers execution to the {@see ProcessRunner}, so it is unit-testable with
 * a {@see MemoryProcessRunner}.
 *
 * @api
 */
final class SvnPhpRepository implements ExtendedRepository
{
    use ExtendedCommitBehavior;

    private const string SVN_DATE_FORMAT = 'Y-m-d\TH:i:s.u';

    private ?SvnPhpWorkingTree $workingTree = null;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly RepositoryPath $path,
        private readonly string $repositoryUrl,
        private readonly string $username,
    ) {
    }

    public function getPath(): RepositoryPath
    {
        return $this->path;
    }

    /**
     * @throws VcsOperationException
     */
    public function hasChanges(): bool
    {
        $result = $this->runner->run($this->path->realPath, ['svn', 'status', '--non-interactive']);

        return trim($result->stdout) !== '';
    }

    public function stage(string $file): void
    {
    }

    public function stageAll(): void
    {
    }

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $options ??= CommitOptions::default();

        $command = ['svn', 'commit', '--non-interactive', '--message', $message->toString()];

        if ($options->authorName !== null && $options->authorEmail !== null) {
            $command[] = '--username';
            $command[] = $options->authorName;
            $command[] = '--password';
            $command[] = $options->authorEmail;
        }

        if ($options->authorDate !== null) {
            $command[] = '--date';
            $command[] = $options->authorDate->format(DateTimeInterface::ATOM);
        }

        $result = $this->runner->run($this->path->realPath, $command);

        $revision = $this->parseRevisionFromResult($result->stdout);

        return CommitId::from((string) $revision);
    }

    public function getLastCommit(): ?Commit
    {
        $result = $this->runner->run($this->path->realPath, ['svn', 'log', '--limit', '1', '--xml', '--non-interactive']);

        if ($result->stdout === '' || !str_contains($result->stdout, '<logentry')) {
            return null;
        }

        return $this->parseCommitFromXml($result->stdout, null);
    }

    public function getCommit(CommitId $id): Commit
    {
        $result = $this->runner->run(
            $this->path->realPath,
            ['svn', 'log', '--revision', $id->value, '--xml', '--non-interactive'],
        );

        return $this->parseCommitFromXml($result->stdout, $id);
    }

    /**
     * @throws VcsOperationException
     */
    public function createBranch(BranchName $name): void
    {
        $url = $this->branchUrl($name);
        $command = ['svn', 'copy', $this->repositoryUrl . '/trunk', $url, '--message', 'Create branch ' . (string) $name];

        if ($this->username !== '') {
            $command[] = '--username';
            $command[] = $this->username;
        }

        $this->runner->run($this->path->realPath, $command);
    }

    /**
     * @throws VcsOperationException
     */
    public function checkoutBranch(BranchName $name): void
    {
        $url = $this->branchUrl($name);
        $command = ['svn', 'switch', '--non-interactive', $url];

        if ($this->username !== '') {
            $command[] = '--username';
            $command[] = $this->username;
        }

        $this->runner->run($this->path->realPath, $command);
    }

    /**
     * @throws VcsOperationException
     */
    public function deleteBranch(BranchName $name): void
    {
        $url = $this->branchUrl($name);
        $command = ['svn', 'remove', '--non-interactive', $url, '--message', 'Delete branch ' . (string) $name];

        if ($this->username !== '') {
            $command[] = '--username';
            $command[] = $this->username;
        }

        $this->runner->run($this->path->realPath, $command);
    }

    /**
     * @return list<BranchName>
     * @throws VcsOperationException
     */
    public function listBranches(): array
    {
        $result = $this->runner->run(
            $this->path->realPath,
            ['svn', 'list', $this->repositoryUrl . '/branches', '--non-interactive'],
        );

        $lines = $result->stdoutLines();

        return array_map(
            static fn (string $line): BranchName => BranchName::from(rtrim($line, '/')),
            $lines
        );
    }

    /**
     * @throws VcsOperationException
     */
    public function getBranchInfo(BranchName $name): ?BranchInfo
    {
        $result = $this->runner->run(
            $this->path->realPath,
            ['svn', 'info', '--xml', $this->branchUrl($name)],
        );

        if (!str_contains($result->stdout, '<entry')) {
            return null;
        }

        return $this->parseBranchInfo($result->stdout, $name);
    }

    /**
     * @return list<BranchInfo>
     * @throws VcsOperationException
     */
    public function searchBranches(BranchQuery $query): array
    {
        $branches = $this->listBranches();
        $results = [];
        $pattern = $query->pattern;

        foreach ($branches as $branch) {
            if ($pattern !== null && !fnmatch($pattern, (string) $branch) && !fnmatch($pattern, (string) $branch)) {
                continue;
            }

            $info = $this->getBranchInfo($branch);

            if ($info !== null) {
                $results[] = $info;
            }
        }

        return $results;
    }

    /**
     * @throws VcsOperationException
     */
    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $url = $this->repositoryUrl . '/tags/' . (string) $name;
        $source = $target !== null
            ? $this->repositoryUrl . '/trunk@' . $target->value
            : $this->repositoryUrl . '/trunk';

        $command = ['svn', 'copy', $source, $url, '--message', 'Tag ' . (string) $name];

        if ($this->username !== '') {
            $command[] = '--username';
            $command[] = $this->username;
        }

        $this->runner->run($this->path->realPath, $command);
    }

    /**
     * @throws VcsOperationException
     */
    public function deleteTag(TagName $name): void
    {
        $url = $this->repositoryUrl . '/tags/' . (string) $name;
        $command = ['svn', 'remove', '--non-interactive', $url, '--message', 'Remove tag ' . (string) $name];

        if ($this->username !== '') {
            $command[] = '--username';
            $command[] = $this->username;
        }

        $this->runner->run($this->path->realPath, $command);
    }

    /**
     * @return list<TagName>
     * @throws VcsOperationException
     */
    public function listTags(): array
    {
        $result = $this->runner->run(
            $this->path->realPath,
            ['svn', 'list', $this->repositoryUrl . '/tags', '--non-interactive'],
        );

        $lines = $result->stdoutLines();

        return array_map(static fn (string $line): TagName => TagName::from(rtrim($line, '/')), $lines);
    }

    /**
     * @throws VcsOperationException
     */
    public function getTagInfo(TagName $name): ?TagInfo
    {
        $result = $this->runner->run(
            $this->path->realPath,
            ['svn', 'info', '--xml', $this->repositoryUrl . '/tags/' . (string) $name],
        );

        if (!str_contains($result->stdout, '<entry')) {
            return null;
        }

        return $this->parseTagInfo($result->stdout, $name);
    }

    /**
     * @return list<TagName>
     * @throws VcsOperationException
     */
    public function cleanupTags(TagCleanupOptions $options): array
    {
        $allTags = $this->listTags();
        $removed = [];
        $pattern = $options->pattern;
        $before = $options->before;

        foreach ($allTags as $tag) {
            $tagName = (string) $tag;

            if ($pattern !== null && !fnmatch($pattern, $tagName)) {
                continue;
            }

            if ($before !== null) {
                $info = $this->getTagInfo($tag);

                if ($info === null) {
                    continue;
                }

                if ($info->date === null || $info->date >= $before) {
                    continue;
                }
            }

            $this->deleteTag($tag);
            $removed[] = $tag;
        }

        return $removed;
    }

    public function addRemote(RemoteName $name, string $url): void
    {
        throw new SvnAdapterException('SVN adapter does not support named remotes. Use the repository URL directly.');
    }

    public function removeRemote(RemoteName $name): void
    {
        throw new SvnAdapterException('SVN adapter does not support named remotes. Use the repository URL directly.');
    }

    /**
     * @return array<string, string>
     */
    public function listRemotes(): array
    {
        return ['origin' => $this->repositoryUrl];
    }

    /**
     * @throws VcsOperationException
     */
    public function fetch(?RemoteName $remote = null): void
    {
        $this->runner->run($this->path->realPath, ['svn', 'update', '--non-interactive']);
    }

    /**
     * @throws VcsOperationException
     */
    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->runner->run($this->path->realPath, ['svn', 'update', '--non-interactive']);
    }

    /**
     * @throws VcsOperationException
     */
    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->runner->run($this->path->realPath, [
            'svn', 'copy', $this->path->realPath . '/*', $this->repositoryUrl . '/trunk',
            '--non-interactive', '--message', 'Push changes',
        ]);
    }

    public function getWorkingTree(): WorkingTree
    {
        return $this->workingTree ??= new SvnPhpWorkingTree($this->runner, $this->path);
    }

    public function execute(string $command, array $args = []): string
    {
        throw new SvnAdapterException('Generic command passthrough is not supported by the SVN adapter.');
    }

    /**
     * @return array<string, string>
     */
    public function getAll(ConfigScope $scope = ConfigScope::Local): array
    {
        throw new VcsOperationException('Configuration management is not supported by the SVN adapter.');
    }

    public function get(string|Stringable $key, ConfigScope $scope = ConfigScope::Local): ?string
    {
        throw new VcsOperationException('Configuration management is not supported by the SVN adapter.');
    }

    public function set(
        string|Stringable $key,
        string|Stringable $value,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void {
        throw new VcsOperationException('Configuration management is not supported by the SVN adapter.');
    }

    public function unset(
        string|Stringable $key,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void {
        throw new VcsOperationException('Configuration management is not supported by the SVN adapter.');
    }

    private function branchUrl(BranchName $name): string
    {
        return $this->repositoryUrl . '/branches/' . (string) $name;
    }

    private function parseRevisionFromResult(string $stdout): int
    {
        if (preg_match('/revision\s+(\d+)/i', $stdout, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function parseCommitFromXml(string $xml, ?CommitId $id): Commit
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $entries = $doc->getElementsByTagName('logentry');

        if ($entries->length === 0) {
            throw new SvnAdapterException('No commit found in SVN log response.');
        }

        /** @var DOMElement $entry */
        $entry = $entries->item(0);
        $revision = (int) $entry->getAttribute('revision');

        $authorNode = $doc->getElementsByTagName('author')->item(0);
        $authorName = $authorNode !== null ? $authorNode->textContent : '';
        $authorEmail = '';

        $dateNode = $doc->getElementsByTagName('date')->item(0);
        $dateStr = $dateNode !== null ? $dateNode->textContent : '';
        try {
            $parsedDate = DateTimeImmutable::createFromFormat(self::SVN_DATE_FORMAT, $dateStr) ?: null;
        } catch (ValueError) {
        }

        $authorDate = $parsedDate ?: new DateTimeImmutable($dateStr);

        $msgNode = $doc->getElementsByTagName('msg')->item(0);
        $message = $msgNode !== null ? $msgNode->textContent : '';

        $commitId = $id ?? CommitId::from((string) $revision);

        return Commit::from(
            $commitId,
            $message,
            null,
            $authorName,
            $authorEmail,
            $authorDate,
            $authorName,
            $authorEmail,
            $authorDate,
        );
    }

    private function parseBranchInfo(string $xml, BranchName $name): BranchInfo
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $commitNode = $doc->getElementsByTagName('commit')->item(0);
        $revision = $commitNode !== null ? $commitNode->getAttribute('revision') : '0';

        $relativePathNode = $doc->getElementsByTagName('relative-path')->item(0);
        $isCurrent = $relativePathNode !== null && $relativePathNode->textContent === '';

        return new BranchInfo(
            $name,
            CommitId::from($revision !== '' ? $revision : '0'),
            $isCurrent,
            false,
            null,
        );
    }

    private function parseTagInfo(string $xml, TagName $name): TagInfo
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $commitNode = $doc->getElementsByTagName('commit')->item(0);
        $revision = $commitNode !== null ? $commitNode->getAttribute('revision') : '0';

        $dateNode = $doc->getElementsByTagName('date')->item(0);
        $dateStr = $dateNode !== null ? $dateNode->textContent : '';
        $parsedDate = $dateStr !== '' ? DateTimeImmutable::createFromFormat(self::SVN_DATE_FORMAT, $dateStr) : null;
        $date = $parsedDate !== false ? $parsedDate : null;

        $authorNode = $doc->getElementsByTagName('author')->item(0);
        $authorName = $authorNode !== null ? $authorNode->textContent : '';

        return new TagInfo(
            $name,
            CommitId::from($revision !== '' ? $revision : '0'),
            TagType::Lightweight,
            null,
            $authorName,
            '',
            $date,
        );
    }
}
