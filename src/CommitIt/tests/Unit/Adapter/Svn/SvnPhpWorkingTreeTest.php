<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Svn;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Adapter\Svn\SvnPhpWorkingTree;
use ItPack\CommitIt\Domain\Value\ProcessResult;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use Tests\Support\MemoryProcessRunner;

/**
 * @covers \ItPack\CommitIt\Adapter\Svn\SvnPhpWorkingTree
 */
final class SvnPhpWorkingTreeTest extends Unit
{
    private MemoryProcessRunner $runner;

    private SvnPhpWorkingTree $workingTree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MemoryProcessRunner();
        $this->workingTree = new SvnPhpWorkingTree($this->runner, RepositoryPath::from('/tmp/svn-test'));
    }

    public function testListWorktreesThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->workingTree->listWorktrees();
    }

    public function testAddWorktreeThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->workingTree->add(RepositoryPath::from('/tmp/wt'), null);
    }

    public function testRemoveWorktreeThrows(): void
    {
        $this->expectException(SvnAdapterException::class);
        $this->workingTree->remove(RepositoryPath::from('/tmp/wt'));
    }

    public function testSetSparsePatternsDispatchesDepthCommand(): void
    {
        $pattern = SparsePattern::from('src/');
        $this->runner->setResult(
            ['svn', 'update', '--set-depth', 'empty', '--non-interactive'],
            new ProcessResult("Updated to revision 42.\n", '', 0),
        );

        $this->workingTree->setSparsePatterns([$pattern]);

        self::assertTrue($this->runner->ran(['svn', 'update', '--set-depth', 'empty', '--non-interactive']));
    }

    public function testGetSparsePatternsReturnsSetPatterns(): void
    {
        $pattern = SparsePattern::from('src/');
        $this->runner->setResult(
            ['svn', 'update', '--set-depth', 'empty', '--non-interactive'],
            new ProcessResult("Updated to revision 42.\n", '', 0),
        );

        $this->workingTree->setSparsePatterns([$pattern]);

        self::assertSame([$pattern], $this->workingTree->getSparsePatterns());
    }

    public function testDisableSparseCheckoutDispatchesInfinityDepth(): void
    {
        $this->runner->setResult(
            ['svn', 'update', '--set-depth', 'infinity', '--non-interactive'],
            new ProcessResult("Updated to revision 42.\n", '', 0),
        );

        $this->workingTree->disableSparseCheckout();

        self::assertTrue($this->runner->ran(['svn', 'update', '--set-depth', 'infinity', '--non-interactive']));
        self::assertSame([], $this->workingTree->getSparsePatterns());
    }

    public function testFindOrphanedWorktreesReturnsEmpty(): void
    {
        self::assertSame([], $this->workingTree->findOrphanedWorktrees());
    }

    public function testCleanupOrphanedWorktreesReturnsZero(): void
    {
        self::assertSame(0, $this->workingTree->cleanupOrphanedWorktrees());
    }
}
