<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

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
 * Minimal {@see Repository} test double. Records every call and can be told to
 * throw on a named operation so the decorator's failure path is exercised.
 */
final class FakeRepository implements Repository
{
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

    public static function fakeCommit(CommitId $id): Commit
    {
        return new Commit(
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
