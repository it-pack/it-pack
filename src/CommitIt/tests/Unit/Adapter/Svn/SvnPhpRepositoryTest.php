<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Svn;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Adapter\Svn\SvnPhpRepository;
use ItPack\CommitIt\Adapter\Svn\SvnPhpWorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\ProcessResult;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use Tests\Support\MemoryProcessRunner;

/**
 * @covers \ItPack\CommitIt\Adapter\Svn\SvnPhpRepository
 */
final class SvnPhpRepositoryTest extends Unit
{
    private MemoryProcessRunner $runner;

    private SvnPhpRepository $repository;

    private const string TEST_REPO_URL = 'http://svn.example.com/repo';

    private const string TEST_PATH = '/tmp/svn-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MemoryProcessRunner();
        $path = RepositoryPath::from(self::TEST_PATH);
        $this->repository = new SvnPhpRepository($this->runner, $path, self::TEST_REPO_URL, 'testuser');
    }

    public function testHasChangesReturnsFalseForEmptyStatus(): void
    {
        $this->runner->setResult(['svn', 'status', '--non-interactive'], new ProcessResult('', '', 0));

        self::assertFalse($this->repository->hasChanges());
    }

    public function testHasChangesReturnsTrueForNonEmptyStatus(): void
    {
        $statusXml = "M       file.txt\n";
        $this->runner->setResult(['svn', 'status', '--non-interactive'], new ProcessResult($statusXml, '', 0));

        self::assertTrue($this->repository->hasChanges());
    }

    public function testStageRecordsIntentWithoutRunningCommand(): void
    {
        $this->repository->stage('file.txt');

        self::assertFalse($this->runner->ran(['svn', 'status', '--non-interactive']));
    }

    public function testStageAllRecordsIntentWithoutRunningCommand(): void
    {
        $this->repository->stageAll();

        self::assertFalse($this->runner->ran(['svn', 'status', '--non-interactive']));
    }

    public function testCommitReturnsRevisionFromResponse(): void
    {
        $this->runner->setResult(
            ['svn', 'commit', '--non-interactive', '--message', 'Test commit'],
            new ProcessResult("Sending        file.txt\nTransmitting file data .\nCommitted revision 42.\n", '', 0),
        );

        $id = $this->repository->commit(CommitMessage::fromParts('Test commit'));

        self::assertInstanceOf(CommitId::class, $id);
        self::assertSame('42', $id->__toString());
    }

    public function testGetLastCommitReturnsNullWhenNoLogEntries(): void
    {
        $this->runner->setResult(['svn', 'log', '--limit', '1', '--xml', '--non-interactive'], new ProcessResult('', '', 0));

        self::assertNull($this->repository->getLastCommit());
    }

    public function testGetLastCommitParsesXmlResponse(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<log>
<logentry revision="42">
<author>testuser</author>
<date>2024-01-02T03:04:05.000000Z</date>
<msg>Test commit message</msg>
</logentry>
</log>';

        $this->runner->setResult(['svn', 'log', '--limit', '1', '--xml', '--non-interactive'], new ProcessResult($xml, '', 0));

        $commit = $this->repository->getLastCommit();

        self::assertNotNull($commit);
        self::assertSame('Test commit message', $commit->subject);
        self::assertSame('testuser', $commit->authorName);
    }

    public function testCreateBranchDispatchesCopyCommand(): void
    {
        $branchUrl = self::TEST_REPO_URL . '/branches/feature';
        $this->runner->setResult(
            ['svn', 'copy', self::TEST_REPO_URL . '/trunk', $branchUrl, '--message', 'Create branch feature', '--username', 'testuser'],
            new ProcessResult("Committed revision 43.\n", '', 0),
        );

        $this->repository->createBranch(BranchName::from('feature'));

        self::assertTrue($this->runner->ran(['svn', 'copy', self::TEST_REPO_URL . '/trunk', $branchUrl, '--message', 'Create branch feature', '--username', 'testuser']));
    }

    public function testCheckoutBranchDispatchesSwitchCommand(): void
    {
        $branchUrl = self::TEST_REPO_URL . '/branches/feature';
        $this->runner->setResult(
            ['svn', 'switch', '--non-interactive', $branchUrl, '--username', 'testuser'],
            new ProcessResult("At revision 42.\n", '', 0),
        );

        $this->repository->checkoutBranch(BranchName::from('feature'));

        self::assertTrue($this->runner->ran(['svn', 'switch', '--non-interactive', $branchUrl, '--username', 'testuser']));
    }

    public function testDeleteBranchDispatchesRemoveCommand(): void
    {
        $branchUrl = self::TEST_REPO_URL . '/branches/feature';
        $this->runner->setResult(
            ['svn', 'remove', '--non-interactive', $branchUrl, '--message', 'Delete branch feature', '--username', 'testuser'],
            new ProcessResult("Committed revision 44.\n", '', 0),
        );

        $this->repository->deleteBranch(BranchName::from('feature'));

        self::assertTrue($this->runner->ran(['svn', 'remove', '--non-interactive', $branchUrl, '--message', 'Delete branch feature', '--username', 'testuser']));
    }

    public function testListBranchesParsesOutput(): void
    {
        $this->runner->setResult(
            ['svn', 'list', self::TEST_REPO_URL . '/branches', '--non-interactive'],
            new ProcessResult("feature\nrelease\n", '', 0),
        );

        $branches = $this->repository->listBranches();

        self::assertCount(2, $branches);
        self::assertSame('feature', $branches[0]->__toString());
        self::assertSame('release', $branches[1]->__toString());
    }

    public function testGetBranchInfoReturnsNullWhenNotExistent(): void
    {
        $this->runner->setResult(
            ['svn', 'info', '--xml', self::TEST_REPO_URL . '/branches/feature'],
            new ProcessResult('', 'svn: E195012: Unable to find repository location', 1),
        );

        self::assertNull($this->repository->getBranchInfo(BranchName::from('feature')));
    }

    public function testCreateTagDispatchesCopyCommand(): void
    {
        $tagUrl = self::TEST_REPO_URL . '/tags/v1.0';
        $this->runner->setResult(
            ['svn', 'copy', self::TEST_REPO_URL . '/trunk', $tagUrl, '--message', 'Tag v1.0', '--username', 'testuser'],
            new ProcessResult("Committed revision 45.\n", '', 0),
        );

        $this->repository->createTag(TagName::from('v1.0'));

        self::assertTrue($this->runner->ran(['svn', 'copy', self::TEST_REPO_URL . '/trunk', $tagUrl, '--message', 'Tag v1.0', '--username', 'testuser']));
    }

    public function testDeleteTagDispatchesRemoveCommand(): void
    {
        $tagUrl = self::TEST_REPO_URL . '/tags/v1.0';
        $this->runner->setResult(
            ['svn', 'remove', '--non-interactive', $tagUrl, '--message', 'Remove tag v1.0', '--username', 'testuser'],
            new ProcessResult("Committed revision 46.\n", '', 0),
        );

        $this->repository->deleteTag(TagName::from('v1.0'));

        self::assertTrue($this->runner->ran(['svn', 'remove', '--non-interactive', $tagUrl, '--message', 'Remove tag v1.0', '--username', 'testuser']));
    }

    public function testListTagsParsesOutput(): void
    {
        $this->runner->setResult(
            ['svn', 'list', self::TEST_REPO_URL . '/tags', '--non-interactive'],
            new ProcessResult("v1.0\nv2.0\n", '', 0),
        );

        $tags = $this->repository->listTags();

        self::assertCount(2, $tags);
        self::assertSame('v1.0', $tags[0]->__toString());
        self::assertSame('v2.0', $tags[1]->__toString());
    }

    public function testListRemotesReturnsUrl(): void
    {
        self::assertSame(['origin' => self::TEST_REPO_URL], $this->repository->listRemotes());
    }

    public function testAddRemoteThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->repository->addRemote(
            \ItPack\CommitIt\Domain\Value\RemoteName::from('origin'),
            'http://example.com',
        );
    }

    public function testRemoveRemoteThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->repository->removeRemote(
            \ItPack\CommitIt\Domain\Value\RemoteName::from('origin'),
        );
    }

    public function testFetchDispatchesUpdateCommand(): void
    {
        $this->runner->setResult(
            ['svn', 'update', '--non-interactive'],
            new ProcessResult("At revision 42.\n", '', 0),
        );

        $this->repository->fetch();

        self::assertTrue($this->runner->ran(['svn', 'update', '--non-interactive']));
    }

    public function testPullDispatchesUpdateCommand(): void
    {
        $this->runner->setResult(
            ['svn', 'update', '--non-interactive'],
            new ProcessResult("At revision 42.\n", '', 0),
        );

        $this->repository->pull();

        self::assertTrue($this->runner->ran(['svn', 'update', '--non-interactive']));
    }

    public function testPushDispatchesCopyCommand(): void
    {
        $this->runner->setResult(
            ['svn', 'copy', self::TEST_PATH . '/*', self::TEST_REPO_URL . '/trunk', '--non-interactive', '--message', 'Push changes'],
            new ProcessResult("Committed revision 47.\n", '', 0),
        );

        $this->repository->push();

        self::assertTrue($this->runner->ran(['svn', 'copy', self::TEST_PATH . '/*', self::TEST_REPO_URL . '/trunk', '--non-interactive', '--message', 'Push changes']));
    }

    public function testExecuteThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->repository->execute('some-command');
    }

    public function testGetPathReturnsValidatedPath(): void
    {
        $path = $this->repository->getPath();

        self::assertInstanceOf(RepositoryPath::class, $path);
        self::assertSame(self::TEST_PATH, $path->toString());
    }

    public function testGetWorkingTreeReturnsSvnWorkingTree(): void
    {
        $wt = $this->repository->getWorkingTree();

        self::assertInstanceOf(SvnPhpWorkingTree::class, $wt);
    }
}
