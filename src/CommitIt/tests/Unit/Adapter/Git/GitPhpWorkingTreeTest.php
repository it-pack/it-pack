<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\Runners\MemoryRunner;
use InvalidArgumentException;
use ItPack\CommitIt\Adapter\Git\GitPhpWorkingTree;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;
use Tests\Support\Helper\GitRepoFactory;

/**
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpWorkingTree
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitPhpWorkingTreeTest extends Unit
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

    public function testListWorktreesParsesPorcelain(): void
    {
        $workingTree = $this->buildWorkingTree(static fn (MemoryRunner $runner) => $runner->setResult(
            ['worktree', 'list', '--porcelain'],
            [],
            implode("\n", [
                'worktree /repo',
                'HEAD abc123',
                'branch refs/heads/main',
                '',
                'worktree /repo/feature',
                'branch refs/heads/feature',
                '',
                'worktree /repo/detached',
                'detached',
            ]),
        ));

        $trees = $workingTree->listWorktrees();

        self::assertCount(3, $trees);
        self::assertInstanceOf(WorktreeInfo::class, $trees[0]);

        self::assertSame('/repo', $trees[0]->path);
        self::assertSame('main', $trees[0]->branch);
        self::assertTrue($trees[0]->isMain);

        self::assertSame('/repo/feature', $trees[1]->path);
        self::assertSame('feature', $trees[1]->branch);
        self::assertFalse($trees[1]->isMain);

        self::assertSame('/repo/detached', $trees[2]->path);
        self::assertNull($trees[2]->branch);
        $this->assertCommandRan(['worktree', 'list', '--porcelain']);
    }

    public function testAddFormulatesCommand(): void
    {
        $target = $this->repoDir . DIRECTORY_SEPARATOR . 'wt';
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'add', '-b', 'feature', $target], [], ''),
        );

        $workingTree->add(RepositoryPath::from($target), BranchName::from('feature'));

        $this->assertCommandRan(['worktree', 'add', '-b', 'feature', $target]);
    }

    public function testRemoveFormulatesCommand(): void
    {
        $target = $this->repoDir . DIRECTORY_SEPARATOR . 'wt';
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'remove', $target], [], ''),
        );

        $workingTree->remove(RepositoryPath::from($target));

        $this->assertCommandRan(['worktree', 'remove', $target]);
    }

    public function testAddWithoutBranchFormulatesCommand(): void
    {
        $target = $this->repoDir . DIRECTORY_SEPARATOR . 'wt';
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'add', $target], [], ''),
        );

        $workingTree->add(RepositoryPath::from($target));

        $this->assertCommandRan(['worktree', 'add', $target]);
    }

    public function testAddRejectsPathOutsideRepositoryParent(): void
    {
        $workingTree = $this->buildWorkingTree(static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'list'], [], ''));

        $outside = DIRECTORY_SEPARATOR . 'commitit_wt_escape_' . bin2hex(random_bytes(4));

        $this->expectException(InvalidArgumentException::class);
        $workingTree->add(RepositoryPath::from($outside), BranchName::from('feature'));
    }

    public function testAddRejectsSiblingPrefixPath(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'commitit_wt_scope_' . bin2hex(random_bytes(6));
        $repoDir = $base . DIRECTORY_SEPARATOR . 'repo';
        $sibling = $base . '-sibling';

        if (!mkdir($repoDir, 0o755, true) && !is_dir($repoDir)) {
            self::fail(sprintf('Unable to create repo dir "%s".', $repoDir));
        }

        if (!mkdir($sibling, 0o755, true) && !is_dir($sibling)) {
            self::fail(sprintf('Unable to create sibling dir "%s".', $sibling));
        }

        try {
            $runner = new MemoryRunner($repoDir);
            $runner->setResult(['worktree', 'add', '-b', 'feature', $sibling . DIRECTORY_SEPARATOR . 'wt'], [], '');
            $gitRepository = new GitRepository($repoDir, $runner);
            $workingTree = new GitPhpWorkingTree($gitRepository);

            $this->expectException(InvalidArgumentException::class);
            $workingTree->add(
                RepositoryPath::from($sibling . DIRECTORY_SEPARATOR . 'wt'),
                BranchName::from('feature'),
            );
        } finally {
            GitRepoFactory::tearDown($repoDir, $sibling);
        }
    }

    public function testListWorktreesFailureIsMapped(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'list', '--porcelain'], [], '', 'fatal: not a worktree', 1),
        );

        $this->expectException(VcsOperationException::class);
        $workingTree->listWorktrees();
    }

    public function testListWorktreesParsesConsecutiveEntries(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(
                ['worktree', 'list', '--porcelain'],
                [],
                implode("\n", [
                    'worktree /repo/a',
                    'worktree /repo/b',
                ]),
            ),
        );

        $trees = $workingTree->listWorktrees();

        self::assertCount(2, $trees);
        self::assertSame('/repo/a', $trees[0]->path);
        self::assertSame('/repo/b', $trees[1]->path);
        $this->assertCommandRan(['worktree', 'list', '--porcelain']);
    }

    public function testAddRejectsWhenParentDoesNotExist(): void
    {
        $outside = DIRECTORY_SEPARATOR . 'commitit_wt_nonexistent_' . bin2hex(random_bytes(4));
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'list'], [], ''),
        );

        $this->expectException(InvalidArgumentException::class);
        $workingTree->add(
            RepositoryPath::from($outside . DIRECTORY_SEPARATOR . 'wt'),
            BranchName::from('feature'),
        );
    }

    public function testSetSparsePatternsInitializesAndSets(): void
    {
        $workingTree = $this->buildWorkingTree(static function (MemoryRunner $runner): void {
            $runner->setResult(['sparse-checkout', 'init'], [], '');
            $runner->setResult(['sparse-checkout', 'set', 'src/*', '!src/vendor/*'], [], '');
        });

        $workingTree->setSparsePatterns([
            SparsePattern::from('src/*'),
            SparsePattern::from('!src/vendor/*'),
        ]);

        $this->assertCommandRan(['sparse-checkout', 'init']);
        $this->assertCommandRan(['sparse-checkout', 'set', 'src/*', '!src/vendor/*']);
    }

    public function testSetSparsePatternsWithEmptyListOnlyInits(): void
    {
        $workingTree = $this->buildWorkingTree(static fn (MemoryRunner $runner) => $runner->setResult(['sparse-checkout', 'init'], [], ''));

        $workingTree->setSparsePatterns([]);

        $this->assertCommandRan(['sparse-checkout', 'init']);
    }

    public function testSetSparsePatternsFailureIsMapped(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['sparse-checkout', 'init'], [], '', 'fatal: not a git repository', 1),
        );

        $this->expectException(VcsOperationException::class);
        $workingTree->setSparsePatterns([SparsePattern::from('src/*')]);
    }

    public function testGetSparsePatternsReturnsParsedPatterns(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(
                ['sparse-checkout', 'list'],
                [],
                implode("\n", ['src/*', '!src/vendor/*', '']),
            ),
        );

        $patterns = $workingTree->getSparsePatterns();

        self::assertCount(2, $patterns);
        self::assertInstanceOf(SparsePattern::class, $patterns[0]);
        self::assertSame('src/*', (string) $patterns[0]);
        self::assertSame('!src/vendor/*', (string) $patterns[1]);
        $this->assertCommandRan(['sparse-checkout', 'list']);
    }

    public function testGetSparsePatternsReturnsEmptyWhenNotConfigured(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['sparse-checkout', 'list'], [], '', 'fatal: not a sparse checkout', 1),
        );

        $patterns = $workingTree->getSparsePatterns();

        self::assertSame([], $patterns);
    }

    public function testDisableSparseCheckoutFormulatesCommand(): void
    {
        $workingTree = $this->buildWorkingTree(static fn (MemoryRunner $runner) => $runner->setResult(['sparse-checkout', 'disable'], [], ''));

        $workingTree->disableSparseCheckout();

        $this->assertCommandRan(['sparse-checkout', 'disable']);
    }

    public function testDisableSparseCheckoutFailureIsMapped(): void
    {
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['sparse-checkout', 'disable'], [], '', 'fatal: not a worktree', 1),
        );

        $this->expectException(VcsOperationException::class);
        $workingTree->disableSparseCheckout();
    }
}
