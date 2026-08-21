<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\Runners\MemoryRunner;
use InvalidArgumentException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

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
    }

    public function testAddFormulatesCommand(): void
    {
        $target = $this->repoDir . DIRECTORY_SEPARATOR . 'wt';
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'add', '-b', 'feature', $target], [], ''),
        );

        $workingTree->add(RepositoryPath::from($target), BranchName::from('feature'));

    }

    public function testRemoveFormulatesCommand(): void
    {
        $target = $this->repoDir . DIRECTORY_SEPARATOR . 'wt';
        $workingTree = $this->buildWorkingTree(
            static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'remove', $target], [], ''),
        );

        $workingTree->remove(RepositoryPath::from($target));

    }

    public function testAddRejectsPathOutsideRepositoryParent(): void
    {
        $workingTree = $this->buildWorkingTree(static fn (MemoryRunner $runner) => $runner->setResult(['worktree', 'list'], [], ''));

        $outside = DIRECTORY_SEPARATOR . 'commitit_wt_escape_' . bin2hex(random_bytes(4));

        $this->expectException(InvalidArgumentException::class);
        $workingTree->add(RepositoryPath::from($outside), BranchName::from('feature'));
    }
}
