<?php

declare(strict_types=1);

namespace Tests\Functional\Operational;

use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;
use ItPack\AsyncIt\Domain\TaskGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskGroup
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class TaskGroupTest extends TestCase
{
    public function testTaskGroupRunsAllChildrenAndReturnsResultsInOrder(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
        );
        $child1 = $group->go(static fn (): string => 'first');
        $child2 = $group->go(static fn (): string => 'second');
        $child3 = $group->go(static fn (): string => 'third');
        $group->close();

        $results = null;
        $scheduler->spawn(static function () use ($group, &$results): void {
            $results = $group->await();
        });
        $scheduler->run();

        self::assertSame(['first', 'second', 'third'], $results);
        self::assertSame(TaskStatus::Succeeded, $child1->status());
        self::assertSame(TaskStatus::Succeeded, $child2->status());
        self::assertSame(TaskStatus::Succeeded, $child3->status());
    }

    public function testTaskGroupCancellationCascadesToAllSiblings(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler(new SystemMonotonicClock());

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
        );

        $group->go(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(500));
        });
        $child2 = $group->go(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(500));
        });
        $child3 = $group->go(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(500));
        });

        $group->close();

        $group->fail(new RuntimeException('group failure'));

        $scheduler->run();

        self::assertSame(TaskStatus::Cancelled, $child2->status());
        self::assertSame(TaskStatus::Cancelled, $child3->status());
    }

    public function testTaskGroupBackpressureErrorThrowsWhenMaxConcurrencyExceeded(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
            maxConcurrency: 1,
            backpressurePolicy: BackpressurePolicy::Error,
        );

        $group->go(static fn (): string => 'first');

        $this->expectException(BackpressureExceededException::class);
        $this->expectExceptionMessage('maxConcurrency limit reached (policy: error)');

        $group->go(static fn (): string => 'second');
    }

    public function testTaskGroupBackpressureDropReturnsNoOpChild(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
            maxConcurrency: 1,
            backpressurePolicy: BackpressurePolicy::Drop,
        );

        $first = $group->go(static fn (): string => 'real');
        $second = $group->go(static fn (): string => 'dropped');

        self::assertNotSame($first, $second);

        $scheduler->run();

        self::assertSame(TaskStatus::Succeeded, $second->status());
    }

    public function testTaskGroupBlockPolicySuspendsAndResumesWhenSlotFrees(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler(new SystemMonotonicClock());

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
            maxConcurrency: 1,
            backpressurePolicy: BackpressurePolicy::Block,
        );

        $blocking = $group->go(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(50));
        });

        $scheduler->spawn(static function () use ($group): void {
            $group->go(static fn (): string => 'after-block');
        });

        $scheduler->run();

        self::assertSame(TaskStatus::Succeeded, $blocking->status());
    }

    public function testTaskGroupAwaitEmptyReturnsEmptyArray(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
        );
        $group->close();

        $results = $group->await();

        self::assertSame([], $results);
    }

    public function testTaskGroupFailureRecordsFirstFailureOnly(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $group = new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            new CancellationScope(),
            $scheduler,
        );
        $first = new RuntimeException('first');
        $second = new RuntimeException('second');

        $group->fail($first);
        $group->fail($second);

        self::assertSame($first, $group->failure());
        self::assertSame(TaskStatus::Failed, $group->status());
    }
}
