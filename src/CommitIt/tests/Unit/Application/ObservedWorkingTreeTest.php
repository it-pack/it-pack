<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommitIt\Application\ObservedWorkingTree;
use ItPack\CommitIt\Contract\NullRepositoryObserver;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
use RuntimeException;

/**
 * @covers \ItPack\CommitIt\Application\ObservedWorkingTree
 */
final class ObservedWorkingTreeTest extends Unit
{
    private function fakeTree(?string $throwOn = null): RecordingWorkingTree
    {
        return new RecordingWorkingTree($throwOn);
    }

    public function testListWorktreesIsObserved(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $result = $tree->listWorktrees();

        self::assertSame([], $result);
        self::assertSame(['listWorktrees'], $inner->calls);
        self::assertSame(['start:listWorktrees', 'success:listWorktrees'], $observer->sequence());
    }

    public function testAddIsObservedWithContext(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $tree->add(RepositoryPath::from('/wt'), BranchName::from('wt-branch'));

        self::assertSame(['start:add', 'success:add'], $observer->sequence());
        self::assertContains('add', $inner->calls);
        self::assertSame(
            ['path' => '/wt', 'branch' => 'wt-branch'],
            $observer->events[0]['context'],
        );
    }

    public function testRemoveIsObservedWithPathContext(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $tree->remove(RepositoryPath::from('/wt'));

        self::assertSame(['start:remove', 'success:remove'], $observer->sequence());
        self::assertContains('remove', $inner->calls);
        self::assertSame(['path' => '/wt'], $observer->events[0]['context']);
    }

    public function testFailureDispatchesFailureAndRethrowsOriginal(): void
    {
        $inner = $this->fakeTree('add');
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $thrown = null;

        try {
            $tree->add(RepositoryPath::from('/wt'), BranchName::from('wt-branch'));
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('boom on add', $thrown->getMessage());
        self::assertSame(['start:add', 'failure:add'], $observer->sequence());
        self::assertSame($thrown, $observer->events[1]['error']);
    }

    public function testObserverFailureDoesNotBreakOperation(): void
    {
        $inner = $this->fakeTree();
        $tree = new ObservedWorkingTree($inner, new ThrowingObserver());

        $tree->listWorktrees();

        self::assertSame(['listWorktrees'], $inner->calls);
    }

    public function testNullObserverIsNoOp(): void
    {
        $inner = $this->fakeTree();
        $tree = new ObservedWorkingTree($inner, new NullRepositoryObserver());

        $tree->add(RepositoryPath::from('/wt'));

        self::assertSame(['add'], $inner->calls);
    }

    public function testSetSparsePatternsIsObserved(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $patterns = [SparsePattern::from('src/*'), SparsePattern::from('!src/vendor/*')];

        $tree->setSparsePatterns($patterns);

        self::assertSame(['start:setSparsePatterns', 'success:setSparsePatterns'], $observer->sequence());
        self::assertContains('setSparsePatterns', $inner->calls);
        self::assertSame(
            ['patterns' => ['src/*', '!src/vendor/*']],
            $observer->events[0]['context'],
        );
    }

    public function testGetSparsePatternsIsObserved(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $tree->getSparsePatterns();

        self::assertSame(['start:getSparsePatterns', 'success:getSparsePatterns'], $observer->sequence());
        self::assertContains('getSparsePatterns', $inner->calls);
    }

    public function testDisableSparseCheckoutIsObserved(): void
    {
        $inner = $this->fakeTree();
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $tree->disableSparseCheckout();

        self::assertSame(['start:disableSparseCheckout', 'success:disableSparseCheckout'], $observer->sequence());
        self::assertContains('disableSparseCheckout', $inner->calls);
    }

    public function testSetSparsePatternsFailureDispatchesFailureAndRethrowsOriginal(): void
    {
        $inner = $this->fakeTree('setSparsePatterns');
        $observer = new RecordingObserver();
        $tree = new ObservedWorkingTree($inner, $observer);

        $thrown = null;

        try {
            $tree->setSparsePatterns([SparsePattern::from('src/*')]);
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('boom on setSparsePatterns', $thrown->getMessage());
        self::assertSame(['start:setSparsePatterns', 'failure:setSparsePatterns'], $observer->sequence());
        self::assertSame($thrown, $observer->events[1]['error']);
    }
}

final class RecordingWorkingTree implements WorkingTree
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private readonly ?string $throwOn = null)
    {
    }

    public function listWorktrees(): array
    {
        $this->calls[] = 'listWorktrees';

        if ($this->throwOn === 'listWorktrees') {
            throw new RuntimeException('boom on listWorktrees');
        }

        return [];
    }

    public function add(RepositoryPath $path, ?BranchName $name = null): void
    {
        $this->calls[] = 'add';

        if ($this->throwOn === 'add') {
            throw new RuntimeException('boom on add');
        }
    }

    public function remove(RepositoryPath $path): void
    {
        $this->calls[] = 'remove';

        if ($this->throwOn === 'remove') {
            throw new RuntimeException('boom on remove');
        }
    }

    public function setSparsePatterns(array $patterns): void
    {
        $this->calls[] = 'setSparsePatterns';

        if ($this->throwOn === 'setSparsePatterns') {
            throw new RuntimeException('boom on setSparsePatterns');
        }
    }

    public function getSparsePatterns(): array
    {
        $this->calls[] = 'getSparsePatterns';

        if ($this->throwOn === 'getSparsePatterns') {
            throw new RuntimeException('boom on getSparsePatterns');
        }

        return [];
    }

    public function disableSparseCheckout(): void
    {
        $this->calls[] = 'disableSparseCheckout';

        if ($this->throwOn === 'disableSparseCheckout') {
            throw new RuntimeException('boom on disableSparseCheckout');
        }
    }

    public function findOrphanedWorktrees(): array
    {
        $this->calls[] = 'findOrphanedWorktrees';

        if ($this->throwOn === 'findOrphanedWorktrees') {
            throw new RuntimeException('boom on findOrphanedWorktrees');
        }

        return [];
    }

    public function cleanupOrphanedWorktrees(): int
    {
        $this->calls[] = 'cleanupOrphanedWorktrees';

        if ($this->throwOn === 'cleanupOrphanedWorktrees') {
            throw new RuntimeException('boom on cleanupOrphanedWorktrees');
        }

        return 0;
    }
}
