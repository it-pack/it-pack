<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\Runners\MemoryRunner;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\TagName;

/**
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitPhpRepositoryTest extends Unit
{
    use GitAdapterMemoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRepoDir();
    }

    protected function tearDown(): void
    {
        $this->tearDownRepoDir();
        parent::tearDown();
    }

    public function testCommitReturnsMappedCommitId(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['commit', null, ['-m' => 'Subject line']], [], '');
            $runner->setResult(['log', '--pretty=format:%H', '-n', '1'], [], self::TEST_COMMIT_ID);
        });

        $id = $repository->commit(CommitMessage::fromParts('Subject line'));

        self::assertInstanceOf(CommitId::class, $id);
        self::assertSame(self::TEST_COMMIT_ID, $id->__toString());
        $this->assertCommandRan(['commit', null, ['-m' => 'Subject line']]);
    }

    public function testCommitFailureIsMapped(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['commit', null, ['-m' => 'x']], [], '', 'fatal: some unexpected failure', 1);
        });

        $this->expectException(VcsOperationException::class);
        $repository->commit(CommitMessage::fromParts('x'));
    }

    public function testGetCommitMapsAllFields(): void
    {
        $id = self::TEST_COMMIT_ID;
        $date = '2024-01-02T03:04:05+00:00';

        $repository = $this->buildRepository(function (MemoryRunner $runner) use ($id, $date): void {
            $runner->setResult(['log', '-1', $id, '--format=%s'], [], 'Subject');
            $runner->setResult(['log', '-1', $id, '--format=%b'], [], 'Body text');
            $runner->setResult(['log', '-1', $id, '--format=%ae'], [], 'a@example.com');
            $runner->setResult(['log', '-1', $id, '--format=%an'], [], 'Author Name');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%ad', '--date=iso-strict'], [], $date);
            $runner->setResult(['log', '-1', $id, '--format=%ce'], [], 'c@example.com');
            $runner->setResult(['log', '-1', $id, '--format=%cn'], [], 'Committer');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%cd', '--date=iso-strict'], [], $date);
        });

        $commit = $repository->getCommit(CommitId::from($id));

        self::assertInstanceOf(Commit::class, $commit);
        self::assertSame($id, $commit->id->__toString());
        self::assertSame('Subject', $commit->subject);
        self::assertSame('Body text', $commit->body);
        self::assertSame('Author Name', $commit->authorName);
        self::assertSame('a@example.com', $commit->authorEmail);
        self::assertSame('Committer', $commit->committerName);
        self::assertSame('c@example.com', $commit->committerEmail);
        self::assertSame($date, $commit->authorDate->format(\DateTimeInterface::ATOM));
        self::assertSame($date, $commit->committerDate->format(\DateTimeInterface::ATOM));
    }

    public function testGetCommitMapsEmptyEmailsToEmptyString(): void
    {
        $id = self::TEST_COMMIT_ID;
        $date = '2024-01-02T03:04:05+00:00';

        $repository = $this->buildRepository(function (MemoryRunner $runner) use ($id, $date): void {
            $runner->setResult(['log', '-1', $id, '--format=%s'], [], 'Subject');
            $runner->setResult(['log', '-1', $id, '--format=%b'], [], '');
            $runner->setResult(['log', '-1', $id, '--format=%ae'], [], '');
            $runner->setResult(['log', '-1', $id, '--format=%an'], [], 'Author');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%ad', '--date=iso-strict'], [], $date);
            $runner->setResult(['log', '-1', $id, '--format=%ce'], [], '');
            $runner->setResult(['log', '-1', $id, '--format=%cn'], [], 'Committer');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%cd', '--date=iso-strict'], [], $date);
        });

        $commit = $repository->getCommit(CommitId::from($id));

        self::assertSame('', $commit->authorEmail);
        self::assertSame('', $commit->committerEmail);
    }

    public function testGetLastCommitReturnsNullWhenEmpty(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['rev-parse', '--verify', 'HEAD'], [], '', 'fatal: ambiguous argument', 128);
            $runner->setResult(['log', '--pretty=format:%H', '-n', '1'], [], '', 'fatal: does not have any commits yet', 128);
        });

        self::assertNull($repository->getLastCommit());
    }

    public function testHasChangesReflectsPorcelain(): void
    {
        $dirty = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['update-index', '-q', '--refresh'], [], '');
            $runner->setResult(['status', '--porcelain'], [], ' M file.txt');
        });
        self::assertTrue($dirty->hasChanges());

        $clean = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['update-index', '-q', '--refresh'], [], '');
            $runner->setResult(['status', '--porcelain'], [], '');
        });
        self::assertFalse($clean->hasChanges());
    }

    public function testStageAndStageAll(): void
    {
        $file = $this->repoDir . DIRECTORY_SEPARATOR . 'file.txt';

        if (file_put_contents($file, 'hello') === false) {
            throw new \RuntimeException('Unable to seed file for stage test.');
        }

        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['add', '--end-of-options', 'file.txt'], [], '');
            $runner->setResult(['add', '--all'], [], '');
        });

        $repository->stage('file.txt');
        $repository->stageAll();

        $this->assertCommandRan(['add', '--end-of-options', 'file.txt']);
        $this->assertCommandRan(['add', '--all']);
    }

    public function testStageRejectsPathOutsideWorkingTree(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['add', '--end-of-options', 'file.txt'], [], ''));

        // Absolute path outside the repository must be rejected by CommitIt
        // without ever reaching the git backend.
        $this->expectException(\InvalidArgumentException::class);
        $repository->stage('/abs/outside/repo/file.txt');
    }

    public function testStageRejectsTraversalOutsideWorkingTree(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['add', '--end-of-options', 'file.txt'], [], ''));

        $this->expectException(\InvalidArgumentException::class);
        $repository->stage('../../etc/passwd');
    }

    public function testBranchOperations(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['branch', '--end-of-options', 'feature'], [], '');
            $runner->setResult(['checkout', 'feature'], [], '');
            $runner->setResult(['branch', ['-d' => 'feature']], [], '');
        });

        $repository->createBranch(BranchName::from('feature'));
        $repository->checkoutBranch(BranchName::from('feature'));
        $repository->deleteBranch(BranchName::from('feature'));

        $this->assertCommandRan(['branch', '--end-of-options', 'feature']);
        $this->assertCommandRan(['checkout', 'feature']);
        $this->assertCommandRan(['branch', ['-d' => 'feature']]);
    }

    public function testListBranches(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['branch', '--no-color'], [], "  main\n* develop"));

        $branches = $repository->listBranches();

        self::assertCount(2, $branches);
        self::assertSame('main', $branches[0]->__toString());
        self::assertSame('develop', $branches[1]->__toString());
    }

    public function testTagOperations(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['tag', null, '--end-of-options', 'v1.0'], [], '');
            $runner->setResult(['tag', '--end-of-options', 'v1.0', self::TEST_COMMIT_ID], [], '');
            $runner->setResult(['tag', ['-d' => 'v1.0']], [], '');
        });

        $repository->createTag(TagName::from('v1.0'));
        $repository->createTag(TagName::from('v1.0'), CommitId::from(self::TEST_COMMIT_ID));
        $repository->deleteTag(TagName::from('v1.0'));

        $this->assertCommandRan(['tag', null, '--end-of-options', 'v1.0']);
        $this->assertCommandRan(['tag', '--end-of-options', 'v1.0', self::TEST_COMMIT_ID]);
        $this->assertCommandRan(['tag', ['-d' => 'v1.0']]);
    }

    public function testListTags(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['tag'], [], "v1.0\nv2.0"));

        $tags = $repository->listTags();

        self::assertCount(2, $tags);
        self::assertSame('v1.0', $tags[0]->__toString());
        self::assertSame('v2.0', $tags[1]->__toString());
    }

    public function testRemoteOperationsAndListing(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['remote', 'add', null, '--end-of-options', 'origin', 'https://example.com/repo.git'], [], '');
            $runner->setResult(['remote', 'remove', '--end-of-options', 'origin'], [], '');
            $runner->setResult(['remote', '-v'], [], "origin\thttps://example.com/repo.git (fetch)\norigin\thttps://example.com/repo.git (push)");
        });

        $repository->addRemote(RemoteName::from('origin'), 'https://example.com/repo.git');
        $repository->removeRemote(RemoteName::from('origin'));

        $this->assertCommandRan(['remote', 'add', null, '--end-of-options', 'origin', 'https://example.com/repo.git']);
        $this->assertCommandRan(['remote', 'remove', '--end-of-options', 'origin']);

        $remotes = $repository->listRemotes();
        self::assertSame(['origin' => 'https://example.com/repo.git'], $remotes);
        $this->assertCommandRan(['remote', '-v']);
    }

    public function testFetchPullPush(): void
    {
        $repository = $this->buildRepository(function (MemoryRunner $runner): void {
            $runner->setResult(['fetch', null, '--end-of-options', null], [], '');
            $runner->setResult(['fetch', null, '--end-of-options', 'origin'], [], '');
            $runner->setResult(['pull', 'origin', 'main'], [], '');
            $runner->setResult(['push', 'origin', 'main'], [], '');
        });

        $repository->fetch();
        $repository->fetch(RemoteName::from('origin'));
        $repository->pull(RemoteName::from('origin'), BranchName::from('main'));
        $repository->push(RemoteName::from('origin'), BranchName::from('main'));

        $this->assertCommandRan(['fetch', null, '--end-of-options', null]);
        $this->assertCommandRan(['fetch', null, '--end-of-options', 'origin']);
        $this->assertCommandRan(['pull', 'origin', 'main']);
        $this->assertCommandRan(['push', 'origin', 'main']);
    }

    public function testExecutePassthroughReturnsJoinedOutput(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['status', '--short'], [], 'M file.txt'));

        self::assertSame('M file.txt', $repository->execute('status', ['--short']));
        $this->assertCommandRan(['status', '--short']);
    }

    public function testWorkingTreeIsExposed(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'list', '--porcelain'], [], ''));

        self::assertInstanceOf(\ItPack\CommitIt\Contract\WorkingTree::class, $repository->getWorkingTree());
    }

    public function testGetPathReturnsValidatedRepositoryPath(): void
    {
        $repository = $this->buildRepository(static fn (MemoryRunner $runner) => null);

        self::assertSame($this->repoDir, $repository->getPath()->toString());
    }

    public function testGetLastCommitReturnsMappedCommitWhenPresent(): void
    {
        $id = self::TEST_COMMIT_ID;
        $date = '2024-01-02T03:04:05+00:00';

        $repository = $this->buildRepository(function (MemoryRunner $runner) use ($id, $date): void {
            $runner->setResult(['rev-parse', '--verify', 'HEAD'], [], $id);
            $runner->setResult(['log', '--pretty=format:%H', '-n', '1'], [], $id);
            $runner->setResult(['log', '-1', $id, '--format=%s'], [], 'Subject');
            $runner->setResult(['log', '-1', $id, '--format=%b'], [], 'Body text');
            $runner->setResult(['log', '-1', $id, '--format=%ae'], [], 'a@example.com');
            $runner->setResult(['log', '-1', $id, '--format=%an'], [], 'Author Name');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%ad', '--date=iso-strict'], [], $date);
            $runner->setResult(['log', '-1', $id, '--format=%ce'], [], 'c@example.com');
            $runner->setResult(['log', '-1', $id, '--format=%cn'], [], 'Committer');
            $runner->setResult(['log', '-1', $id, '--pretty=format:%cd', '--date=iso-strict'], [], $date);
        });

        $commit = $repository->getLastCommit();

        self::assertInstanceOf(Commit::class, $commit);
        self::assertSame($id, $commit->id->__toString());
        self::assertSame('Subject', $commit->subject);
        self::assertSame('Author Name', $commit->authorName);
        $this->assertCommandRan(['rev-parse', '--verify', 'HEAD']);
        $this->assertCommandRan(['log', '--pretty=format:%H', '-n', '1']);
    }

    public function testCommitWithOptionsFormulatesParameters(): void
    {
        $date = '2024-01-02T03:04:05+00:00';

        $repository = $this->buildRepository(function (MemoryRunner $runner) use ($date): void {
            $runner->setResult(
                ['commit', null, ['--author' => 'Jane <jane@example.com>', '--date' => $date, '--allow-empty', '--amend', '-m' => 'Subject']],
                [],
                '',
            );
            $runner->setResult(['log', '--pretty=format:%H', '-n', '1'], [], self::TEST_COMMIT_ID);
        });

        $id = $repository->commit(
            CommitMessage::fromParts('Subject'),
            CommitOptions::default()
                ->withAuthor('Jane', 'jane@example.com')
                ->withAuthorDate(new \DateTimeImmutable($date))
                ->withAllowEmpty()
                ->withAmend(),
        );

        self::assertSame(self::TEST_COMMIT_ID, $id->__toString());
        $this->assertCommandRan(
            ['commit', null, ['--author' => 'Jane <jane@example.com>', '--date' => $date, '--allow-empty', '--amend', '-m' => 'Subject']],
        );
    }
}
