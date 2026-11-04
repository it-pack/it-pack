<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Behavior\Adapter\ExtendedCommitBehavior;
use ItPack\CommitIt\Contract\ExtendedRepository;
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
use ItPack\CommitIt\Domain\Value\ConfigScope;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagInfo;
use ItPack\CommitIt\Domain\Value\TagName;
use Stringable;

/**
 * Minimal {@see Repository} test double. Records every call and can be told to
 * throw on a named operation so the decorator's failure path is exercised.
 */
final class FakeRepository implements ExtendedRepository
{
    use ExtendedCommitBehavior;

    /** @var list<string> */
    public array $calls = [];

    public ?string $throwOn = null;

    private readonly CommitId $commitId;

    public function __construct(?CommitId $commitId = null)
    {
        $this->commitId = $commitId ?? CommitId::from('1111111111111111111111111111111111111111');
    }

    private function maybeThrow(string $operation): void
    {
        if ($this->throwOn === $operation) {
            throw new \RuntimeException(sprintf('boom on %s', $operation));
        }
    }

    public function getPath(): RepositoryPath
    {
        $this->calls[] = 'getPath';
        $this->maybeThrow('getPath');

        return RepositoryPath::from(sys_get_temp_dir());
    }

    public function hasChanges(): bool
    {
        $this->calls[] = 'hasChanges';
        $this->maybeThrow('hasChanges');

        return false;
    }

    public function stage(string $file): void
    {
        $this->calls[] = 'stage';
        $this->maybeThrow('stage');
    }

    public function stageAll(): void
    {
        $this->calls[] = 'stageAll';
        $this->maybeThrow('stageAll');
    }

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $this->calls[] = 'commit';
        $this->maybeThrow('commit');

        return $this->commitId;
    }

    public function getLastCommit(): ?Commit
    {
        $this->calls[] = 'getLastCommit';
        $this->maybeThrow('getLastCommit');

        return null;
    }

    public function getCommit(CommitId $id): Commit
    {
        $this->calls[] = 'getCommit';
        $this->maybeThrow('getCommit');

        return self::fakeCommit($id);
    }

    public function createBranch(BranchName $name): void
    {
        $this->calls[] = 'createBranch';
        $this->maybeThrow('createBranch');
    }

    public function checkoutBranch(BranchName $name): void
    {
        $this->calls[] = 'checkoutBranch';
        $this->maybeThrow('checkoutBranch');
    }

    public function deleteBranch(BranchName $name): void
    {
        $this->calls[] = 'deleteBranch';
        $this->maybeThrow('deleteBranch');
    }

    /**
     * @return list<BranchName>
     */
    public function listBranches(): array
    {
        $this->calls[] = 'listBranches';
        $this->maybeThrow('listBranches');

        return [];
    }

    public function getBranchInfo(BranchName $name): ?BranchInfo
    {
        $this->calls[] = 'getBranchInfo';
        $this->maybeThrow('getBranchInfo');

        return null;
    }

    /**
     * @return list<BranchInfo>
     */
    public function searchBranches(BranchQuery $query): array
    {
        $this->calls[] = 'searchBranches';
        $this->maybeThrow('searchBranches');

        return [];
    }

    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $this->calls[] = 'createTag';
        $this->maybeThrow('createTag');
    }

    public function deleteTag(TagName $name): void
    {
        $this->calls[] = 'deleteTag';
        $this->maybeThrow('deleteTag');
    }

    /**
     * @return list<TagName>
     */
    public function listTags(): array
    {
        $this->calls[] = 'listTags';
        $this->maybeThrow('listTags');

        return [];
    }

    public function getTagInfo(TagName $name): ?TagInfo
    {
        $this->calls[] = 'getTagInfo';
        $this->maybeThrow('getTagInfo');

        return null;
    }

    /**
     * @return list<TagName>
     */
    public function cleanupTags(TagCleanupOptions $options): array
    {
        $this->calls[] = 'cleanupTags';
        $this->maybeThrow('cleanupTags');

        return [];
    }

    public function addRemote(RemoteName $name, string $url): void
    {
        $this->calls[] = 'addRemote';
        $this->maybeThrow('addRemote');
    }

    public function removeRemote(RemoteName $name): void
    {
        $this->calls[] = 'removeRemote';
        $this->maybeThrow('removeRemote');
    }

    /**
     * @return array<string, string>
     */
    public function listRemotes(): array
    {
        $this->calls[] = 'listRemotes';
        $this->maybeThrow('listRemotes');

        return [];
    }

    public function fetch(?RemoteName $remote = null): void
    {
        $this->calls[] = 'fetch';
        $this->maybeThrow('fetch');
    }

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->calls[] = 'pull';
        $this->maybeThrow('pull');
    }

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->calls[] = 'push';
        $this->maybeThrow('push');
    }

    public function getWorkingTree(): WorkingTree
    {
        $this->calls[] = 'getWorkingTree';
        $this->maybeThrow('getWorkingTree');

        return new FakeWorkingTree();
    }

    /**
     * @param list<string> $args
     */
    public function execute(string $command, array $args = []): string
    {
        $this->calls[] = 'execute';
        $this->maybeThrow('execute');

        return '';
    }

    /**
     * @return array<string, string>
     */
    public function getAll(ConfigScope $scope = ConfigScope::Local): array
    {
        $this->calls[] = 'getAll';
        $this->maybeThrow('getAll');

        return [];
    }

    public function get(string|Stringable $key, ConfigScope $scope = ConfigScope::Local): ?string
    {
        $this->calls[] = 'get';
        $this->maybeThrow('get');

        return null;
    }

    public function set(
        string|Stringable $key,
        string|Stringable $value,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void {
        $this->calls[] = 'set';
        $this->maybeThrow('set');
    }

    public function unset(
        string|Stringable $key,
        ConfigScope $scope = ConfigScope::Local,
        string|Stringable|null $file = null
    ): void {
        $this->calls[] = 'unset';
        $this->maybeThrow('unset');
    }

    public static function fakeCommit(CommitId $id): Commit
    {
        return Commit::from(
            $id,
            'subject',
            null,
            'Author',
            'author@example.com',
            new \DateTimeImmutable('@1000000000'),
            'Committer',
            'committer@example.com',
            new \DateTimeImmutable('@1000000000'),
        );
    }
}
