<?php

declare(strict_types=1);

namespace ItPack\CommitItRunit\Tests;

use Codeception\Test\Unit;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\RepositoryOperation;
use ItPack\CommitItRunit\CommitItRunitAdapter;
use ItPack\CommitItRunit\Model\Activity;
use ItPack\CommitItRunit\Model\Occurrence;
use ItPack\CommitItRunit\Model\Snapshot;
use RuntimeException;

/**
 * @covers \ItPack\CommitItRunit\CommitItRunitAdapter
 */
final class CommitItRunitAdapterTest extends Unit
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function operationNameProvider(): array
    {
        return [
            'get_path'         => ['getPath', 'repository.get_path'],
            'has_changes'      => ['hasChanges', 'repository.has_changes'],
            'stage'            => ['stage', 'repository.stage'],
            'stage_all'        => ['stageAll', 'repository.stage_all'],
            'commit'           => ['commit', 'repository.commit'],
            'get_last_commit'  => ['getLastCommit', 'repository.get_last_commit'],
            'get_commit'       => ['getCommit', 'repository.get_commit'],
            'create_branch'    => ['createBranch', 'reference.create_branch'],
            'checkout_branch'  => ['checkoutBranch', 'reference.checkout_branch'],
            'delete_branch'    => ['deleteBranch', 'reference.delete_branch'],
            'list_branches'    => ['listBranches', 'reference.list_branches'],
            'create_tag'       => ['createTag', 'reference.create_tag'],
            'delete_tag'       => ['deleteTag', 'reference.delete_tag'],
            'list_tags'        => ['listTags', 'reference.list_tags'],
            'add_remote'       => ['addRemote', 'remote.add'],
            'remove_remote'    => ['removeRemote', 'remote.remove'],
            'list_remotes'     => ['listRemotes', 'remote.list'],
            'fetch'            => ['fetch', 'remote.fetch'],
            'pull'             => ['pull', 'remote.pull'],
            'push'             => ['push', 'remote.push'],
            'execute'          => ['execute', 'repository.execute'],
            'list_worktrees'   => ['listWorktrees', 'worktree.list'],
            'add_worktree'     => ['add', 'worktree.add'],
            'remove_worktree'  => ['remove', 'worktree.remove'],
            'unknown_default'  => ['customOp', 'repository.customOp'],
        ];
    }

    /**
     * @dataProvider operationNameProvider
     */
    public function testStartEmitsActivityWithNormalizedName(string $operation, string $expectedName): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        $adapter->onStart($operation, ['key' => 'value']);

        self::assertCount(1, $captured);
        $event = $captured[0];
        self::assertSame('activity', $event['type']);
        self::assertSame($expectedName, $event['data']->name);
        self::assertSame(
            ['key' => 'value', 'adapter' => 'CommitIt-RunIt', 'model' => 'Activity/Snapshot/Occurrence'],
            $event['data']->context,
        );
        self::assertInstanceOf(Activity::class, $event['data']);
        self::assertNotEmpty($event['data']->activityId);
    }

    public function testSuccessEmitsSnapshotWithSameActivityId(): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        $adapter->onStart('commit', ['file' => 'test.txt']);
        $adapter->onSuccess('commit', ['file' => 'test.txt']);

        self::assertCount(2, $captured);

        $startEvent = $captured[0];
        $successEvent = $captured[1];

        self::assertSame('activity', $startEvent['type']);
        self::assertSame('snapshot', $successEvent['type']);
        self::assertSame($startEvent['data']->activityId, $successEvent['data']->activityId);
        self::assertInstanceOf(Snapshot::class, $successEvent['data']);
        self::assertSame('repository.commit', $successEvent['data']->name);
    }

    public function testFailureEmitsOccurrenceWithErrorData(): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        $error = new RuntimeException('something went wrong', 42);

        $adapter->onStart('push', ['remote' => 'origin']);
        $adapter->onFailure('push', $error, ['remote' => 'origin']);

        self::assertCount(2, $captured);

        $failureEvent = $captured[1];
        self::assertSame('occurrence', $failureEvent['type']);
        self::assertInstanceOf(Occurrence::class, $failureEvent['data']);
        self::assertSame('remote.push', $failureEvent['data']->name);
        self::assertSame(RuntimeException::class, $failureEvent['data']->error['class']);
        self::assertSame('something went wrong', $failureEvent['data']->error['message']);
        self::assertSame(42, $failureEvent['data']->error['code']);
    }

    public function testObserverFailureIsSwallowedOnStart(): void
    {
        $captured = [];

        $failingAdapter = new CommitItRunitAdapter(
            static function (Activity $a): void {
                throw new RuntimeException('emitter failed');
            },
            static function (Snapshot $s): void {
            },
            static function (Occurrence $o): void {
            },
        );

        $failingAdapter->onStart('commit', []);

        self::assertEmpty($captured);
    }

    public function testObserverFailureIsSwallowedOnSuccess(): void
    {
        $captured = [];

        $failingAdapter = new CommitItRunitAdapter(
            static function (Activity $a): void {
            },
            static function (Snapshot $s): void {
                throw new RuntimeException('emitter failed');
            },
            static function (Occurrence $o): void {
            },
        );

        $failingAdapter->onStart('commit', []);
        $failingAdapter->onSuccess('commit', []);

        self::assertEmpty($captured);
    }

    public function testObserverFailureIsSwallowedOnFailure(): void
    {
        $failingAdapter = new CommitItRunitAdapter(
            static function (Activity $a): void {
            },
            static function (Snapshot $s): void {
            },
            static function (Occurrence $o): void {
                throw new RuntimeException('emitter failed');
            },
        );

        $error = new RuntimeException('original error');

        $failingAdapter->onStart('pull', []);
        $failingAdapter->onFailure('pull', $error, []);

        $this->expectNotToPerformAssertions();
    }

    public function testContextEnrichmentDoesNotOverrideExistingKeys(): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        $adapter->onStart('commit', ['adapter' => 'custom-value', 'model' => 'custom-model']);

        $activity = $captured[0]['data'];
        self::assertSame('custom-value', $activity->context['adapter']);
        self::assertSame('custom-model', $activity->context['model']);
    }

    public function testAdapterImplementsRepositoryObserver(): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        self::assertInstanceOf(RepositoryObserver::class, $adapter);
    }

    public function testAllRepositoryOperationsAreCovered(): void
    {
        $captured = [];
        $adapter = $this->createAdapter($captured);

        foreach (RepositoryOperation::cases() as $operation) {
            $adapter->onStart($operation->value, []);
            $adapter->onSuccess($operation->value, []);
        }

        self::assertCount(count(RepositoryOperation::cases()) * 2, $captured);
    }

    /**
     * @param list<array{type: string, data: Activity|Snapshot|Occurrence}> $captured
     */
    private function createAdapter(array &$captured): CommitItRunitAdapter
    {
        return new CommitItRunitAdapter(
            static function (Activity $a) use (&$captured): void {
                $captured[] = ['type' => 'activity', 'data' => $a];
            },
            static function (Snapshot $s) use (&$captured): void {
                $captured[] = ['type' => 'snapshot', 'data' => $s];
            },
            static function (Occurrence $o) use (&$captured): void {
                $captured[] = ['type' => 'occurrence', 'data' => $o];
            },
        );
    }
}
