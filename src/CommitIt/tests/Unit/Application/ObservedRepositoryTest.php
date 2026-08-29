<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommitIt\Application\ObservedRepository;
use ItPack\CommitIt\Application\ObservedWorkingTree;
use ItPack\CommitIt\Contract\NullRepositoryObserver;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use RuntimeException;

/**
 * @covers \ItPack\CommitIt\Application\ObservedRepository
 */
final class ObservedRepositoryTest extends Unit
{
    private const SOME_ID = '2222222222222222222222222222222222222222';

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

    /**
     * Every observed operation except {@see ObservedRepository::getWorkingTree()} (which only wraps its
     * collaborator) must dispatch `onStart` then `onSuccess` with the operation name preserved.
     *
     * @return iterable<string, array{0: callable, 1: string}>
     */
    public function observedOperationProvider(): iterable
    {
        yield 'getPath' => [static fn (ObservedRepository $r) => $r->getPath(), 'getPath'];
        yield 'hasChanges' => [static fn (ObservedRepository $r) => $r->hasChanges(), 'hasChanges'];
        yield 'stage' => [static fn (ObservedRepository $r) => $r->stage('file.txt'), 'stage'];
        yield 'stageAll' => [static fn (ObservedRepository $r) => $r->stageAll(), 'stageAll'];
        yield 'commit' => [static fn (ObservedRepository $r) => $r->commit(CommitMessage::fromParts('Subject')), 'commit'];
        yield 'commitWithOptions' => [
            static fn (ObservedRepository $r) => $r->commit(CommitMessage::fromParts('Subject'), CommitOptions::default()->withAmend()),
            'commit',
        ];
        yield 'getLastCommit' => [static fn (ObservedRepository $r) => $r->getLastCommit(), 'getLastCommit'];
        yield 'getCommit' => [static fn (ObservedRepository $r) => $r->getCommit(CommitId::from(self::SOME_ID)), 'getCommit'];
        yield 'createBranch' => [static fn (ObservedRepository $r) => $r->createBranch(BranchName::from('feature')), 'createBranch'];
        yield 'checkoutBranch' => [static fn (ObservedRepository $r) => $r->checkoutBranch(BranchName::from('feature')), 'checkoutBranch'];
        yield 'deleteBranch' => [static fn (ObservedRepository $r) => $r->deleteBranch(BranchName::from('feature')), 'deleteBranch'];
        yield 'listBranches' => [static fn (ObservedRepository $r) => $r->listBranches(), 'listBranches'];
        yield 'createTag' => [static fn (ObservedRepository $r) => $r->createTag(TagName::from('v1')), 'createTag'];
        yield 'createTagWithTarget' => [
            static fn (ObservedRepository $r) => $r->createTag(TagName::from('v1'), CommitId::from(self::SOME_ID)),
            'createTag',
        ];
        yield 'deleteTag' => [static fn (ObservedRepository $r) => $r->deleteTag(TagName::from('v1')), 'deleteTag'];
        yield 'listTags' => [static fn (ObservedRepository $r) => $r->listTags(), 'listTags'];
        yield 'addRemote' => [
            static fn (ObservedRepository $r) => $r->addRemote(RemoteName::from('origin'), 'https://example.com/repo.git'),
            'addRemote',
        ];
        yield 'removeRemote' => [static fn (ObservedRepository $r) => $r->removeRemote(RemoteName::from('origin')), 'removeRemote'];
        yield 'listRemotes' => [static fn (ObservedRepository $r) => $r->listRemotes(), 'listRemotes'];
        yield 'fetch' => [static fn (ObservedRepository $r) => $r->fetch(), 'fetch'];
        yield 'pull' => [static fn (ObservedRepository $r) => $r->pull(), 'pull'];
        yield 'push' => [static fn (ObservedRepository $r) => $r->push(), 'push'];
        yield 'execute' => [static fn (ObservedRepository $r) => $r->execute('status'), 'execute'];
    }

    /**
     * @dataProvider observedOperationProvider
     */
    public function testEveryOperationDispatchesStartThenSuccess(callable $operation, string $name): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $operation($repository);

        self::assertContains($name, $inner->calls);
        self::assertSame(['start:' . $name, 'success:' . $name], $observer->sequence());
    }

    /**
     * @dataProvider observedOperationProvider
     */
    public function testEveryOperationDispatchesFailureAndRethrowsOriginal(callable $operation, string $name): void
    {
        $inner = new FakeRepository();
        $inner->throwOn = $name;
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $thrown = null;

        try {
            $operation($repository);
        } catch (RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown, 'operation must rethrow the original failure');
        self::assertSame('boom on ' . $name, $thrown->getMessage());
        self::assertSame(['start:' . $name, 'failure:' . $name], $observer->sequence());

        $failureEvent = $observer->events[1];
        self::assertArrayHasKey('error', $failureEvent);
        self::assertSame($thrown, $failureEvent['error']);
    }

    public function testObserverFailureOnSuccessDoesNotMaskResult(): void
    {
        $inner = new FakeRepository();
        $observer = new ThrowingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $id = $repository->commit(CommitMessage::fromParts('Subject'));

        self::assertInstanceOf(CommitId::class, $id);
        self::assertContains('commit', $inner->calls);
    }

    /**
     * Every context-bearing operation must record its structured parameters in both the `onStart`
     * and `onSuccess` events. Asserting the exact context kills `ArrayItem` / `ArrayItemRemoval`
     * mutants that would otherwise drop or corrupt an observed parameter.
     *
     * @return iterable<string, array{0: callable, 1: array<string, mixed>}>
     */
    public function operationContextProvider(): iterable
    {
        yield 'stage' => [static fn (ObservedRepository $r) => $r->stage('file.txt'), ['file' => 'file.txt']];
        yield 'commitWithOptions' => [
            static fn (ObservedRepository $r) => $r->commit(
                CommitMessage::fromParts('Subject'),
                CommitOptions::default()->withAllowEmpty()->withAmend(),
            ),
            ['allowEmpty' => true, 'amend' => true],
        ];
        yield 'getCommit' => [
            static fn (ObservedRepository $r) => $r->getCommit(CommitId::from(self::SOME_ID)),
            ['commit' => self::SOME_ID],
        ];
        yield 'createBranch' => [
            static fn (ObservedRepository $r) => $r->createBranch(BranchName::from('feature')),
            ['branch' => 'feature'],
        ];
        yield 'checkoutBranch' => [
            static fn (ObservedRepository $r) => $r->checkoutBranch(BranchName::from('feature')),
            ['branch' => 'feature'],
        ];
        yield 'deleteBranch' => [
            static fn (ObservedRepository $r) => $r->deleteBranch(BranchName::from('feature')),
            ['branch' => 'feature'],
        ];
        yield 'createTag' => [
            static fn (ObservedRepository $r) => $r->createTag(TagName::from('v1')),
            ['tag' => 'v1'],
        ];
        yield 'createTagWithTarget' => [
            static fn (ObservedRepository $r) => $r->createTag(TagName::from('v1'), CommitId::from(self::SOME_ID)),
            ['tag' => 'v1', 'target' => self::SOME_ID],
        ];
        yield 'deleteTag' => [
            static fn (ObservedRepository $r) => $r->deleteTag(TagName::from('v1')),
            ['tag' => 'v1'],
        ];
        yield 'addRemote' => [
            static fn (ObservedRepository $r) => $r->addRemote(RemoteName::from('origin'), 'https://example.com/repo.git'),
            ['remote' => 'origin'],
        ];
        yield 'removeRemote' => [
            static fn (ObservedRepository $r) => $r->removeRemote(RemoteName::from('origin')),
            ['remote' => 'origin'],
        ];
        yield 'execute' => [
            static fn (ObservedRepository $r) => $r->execute('status', ['--short']),
            ['command' => 'status'],
        ];
    }

    /**
     * @dataProvider operationContextProvider
     * @param array<string, mixed> $expectedContext
     */
    public function testOperationRecordsStructuredContext(callable $operation, array $expectedContext): void
    {
        $inner = new FakeRepository();
        $observer = new RecordingObserver();
        $repository = new ObservedRepository($inner, $observer);

        $operation($repository);

        self::assertSame($expectedContext, $observer->events[0]['context']);
        self::assertSame($expectedContext, $observer->events[1]['context']);
    }
}
