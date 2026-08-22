<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use Tests\Support\Helper\GitRepoFactory;

/**
 * Functional coverage of the git adapter against real temporary repositories.
 *
 * These tests exercise the full stack (factory -> repository -> working tree)
 * with a real git binary so the documented behavior is proven end to end.
 *
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpWorkingTree
 */
final class GitPhpFunctionalTest extends Unit
{
    private function open(string $path): Repository
    {
        return new GitPhpRepositoryFactory()->open(RepositoryPath::from($path));
    }

    public function testInitSeedStageCommitAndGetLastCommit(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'hello');
            $repository->stage('a.txt');
            $id = $repository->commit(CommitMessage::fromParts('Add a.txt'));

            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id->__toString());

            $last = $repository->getLastCommit();
            self::assertNotNull($last);
            self::assertSame('Add a.txt', $last->subject);
            self::assertSame($id->__toString(), $last->id->__toString());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testHasChangesTogglesAroundCommit(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'v1');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('seed'));

            self::assertFalse($repository->hasChanges());

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'v2');
            self::assertTrue($repository->hasChanges());

            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('modify'));
            self::assertFalse($repository->hasChanges());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testBranchLifecycle(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'v1');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('seed'));

            $repository->createBranch(BranchName::from('feature'));
            $repository->checkoutBranch(BranchName::from('feature'));

            $names = array_map(static fn (BranchName $b): string => $b->__toString(), $repository->listBranches());
            self::assertContains('feature', $names);
            self::assertContains('main', $names);

            $repository->checkoutBranch(BranchName::from('main'));
            $repository->deleteBranch(BranchName::from('feature'));

            $after = array_map(static fn (BranchName $b): string => $b->__toString(), $repository->listBranches());
            self::assertNotContains('feature', $after);
            self::assertContains('main', $after);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testTagLifecycle(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'v1');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('seed'));

            $repository->createTag(TagName::from('v1.0'));
            $tags = array_map(static fn (TagName $t): string => $t->__toString(), $repository->listTags());
            self::assertContains('v1.0', $tags);

            $repository->deleteTag(TagName::from('v1.0'));
            $after = array_map(static fn (TagName $t): string => $t->__toString(), $repository->listTags());
            self::assertNotContains('v1.0', $after);
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testRemoteLifecycle(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $repository->addRemote(RemoteName::from('origin'), 'https://example.com/repo.git');
            $remotes = $repository->listRemotes();
            self::assertArrayHasKey('origin', $remotes);
            self::assertSame('https://example.com/repo.git', $remotes['origin']);

            $repository->removeRemote(RemoteName::from('origin'));
            self::assertArrayNotHasKey('origin', $repository->listRemotes());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testWorktreeAddListAndRemove(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-fn');
        $worktree = $repo . '-wt';

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'v1');
            $repository->stage('a.txt');
            $repository->commit(CommitMessage::fromParts('seed'));

            $repository->getWorkingTree()->add(RepositoryPath::from($worktree), BranchName::from('wt-branch'));

            $infos = $repository->getWorkingTree()->listWorktrees();
            self::assertCount(2, $infos);
            self::assertSame('wt-branch', $infos[1]->branch);

            $repository->getWorkingTree()->remove(RepositoryPath::from($worktree));

            $after = $repository->getWorkingTree()->listWorktrees();
            self::assertCount(1, $after);
        } finally {
            GitRepoFactory::tearDown($repo, $worktree);
        }
    }

    public function testClonePushPullAndFetch(): void
    {
        $bare = GitRepoFactory::createTempDir('commitit-bare');
        $clone1 = GitRepoFactory::createTempDir('commitit-c1');
        $clone2 = GitRepoFactory::createTempDir('commitit-c2');

        try {
            GitRepoFactory::initBareRepo($bare);
            GitRepoFactory::cloneRepo($bare, $clone1);
            GitRepoFactory::cloneRepo($bare, $clone2);

            $repo1 = $this->open($clone1);
            $repo2 = $this->open($clone2);

            file_put_contents($clone1 . DIRECTORY_SEPARATOR . 'f.txt', 'x');
            $repo1->stage('f.txt');
            $repo1->commit(CommitMessage::fromParts('C1'));

            $branch = $repo1->listBranches()[0];
            $repo1->push(RemoteName::from('origin'), $branch);

            $repo2->pull(RemoteName::from('origin'), $branch);
            $last = $repo2->getLastCommit();
            self::assertNotNull($last);
            self::assertSame('C1', $last->subject);

            $repo2->fetch(RemoteName::from('origin'));
        } finally {
            GitRepoFactory::tearDown($bare, $clone1, $clone2);
        }
    }
}
