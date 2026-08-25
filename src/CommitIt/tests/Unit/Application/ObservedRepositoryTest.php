<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommitIt\Application\ObservedRepository;
use ItPack\CommitIt\Application\ObservedWorkingTree;
use ItPack\CommitIt\Contract\NullRepositoryObserver;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use RuntimeException;

/**
 * @covers \ItPack\CommitIt\Application\ObservedRepository
 */
final class ObservedRepositoryTest extends Unit
{
    public function testSuccessDispatchesStartThenSuccess(): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $id = $repository->commit(CommitMessage::fromParts('Subject'));

        self::assertInstanceOf(CommitId::class, $id);
        self::assertSame(['start:commit', 'success:commit'], $observer->sequence());
        self::assertContains('commit', $inner->calls);
    }

    public function testSuccessContextOmitsMessageBody(): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $repository->commit(CommitMessage::fromParts('Secret subject', 'secret body'));

        self::assertSame([], $observer->events[0]['context']);
    }

    public function testFailureDispatchesStartThenFailureAndRethrowsOriginal(): void
    {
        $inner = new FakeRepository();
        $inner->throwOn = 'commit';
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $thrown = null;

        try {
            $repository->commit(CommitMessage::fromParts('Subject'));
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('boom on commit', $thrown->getMessage());
        self::assertSame(['start:commit', 'failure:commit'], $observer->sequence());

        $failureEvent = $observer->events[1];
        self::assertArrayHasKey('error', $failureEvent);
        self::assertSame($thrown, $failureEvent['error']);
    }

    public function testObserverFailureDoesNotBreakOperation(): void
    {
        $inner = new FakeRepository();
        $repository = new ObservedRepository($inner, new ThrowingObserver());

        $result = $repository->hasChanges();

        self::assertFalse($result);
        self::assertContains('hasChanges', $inner->calls);
    }

    public function testNullObserverIsNoOp(): void
    {
        $inner = new FakeRepository();
        $repository = new ObservedRepository($inner, new NullRepositoryObserver());

        $id = $repository->commit(CommitMessage::fromParts('Subject'));

        self::assertInstanceOf(CommitId::class, $id);
        self::assertContains('commit', $inner->calls);
    }

    public function testVoidOperationDispatchesStartThenSuccess(): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $repository->stageAll();

        self::assertContains('stageAll', $inner->calls);
        self::assertSame(['start:stageAll', 'success:stageAll'], $observer->sequence());
    }

    public function testGetWorkingTreeIsObserved(): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $tree = $repository->getWorkingTree();

        self::assertInstanceOf(ObservedWorkingTree::class, $tree);

        $tree->add(RepositoryPath::from('/wt'), BranchName::from('wt-branch'));

        self::assertSame(['start:add', 'success:add'], $observer->sequence());
    }
}
