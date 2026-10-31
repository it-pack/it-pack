<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\Exception\BackpressureExceededException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\AsyncIt\Domain\TaskGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskGroup
 */
final class TaskGroupTest extends TestCase
{
    public function testStartsInCreatedStatus(): void
    {
        $group = $this->createGroup();

        self::assertSame(TaskStatus::Created, $group->status());
    }

    public function testGoSpawnsChildAndReturnsTask(): void
    {
        $child = $this->makeTask(TaskStatus::Created);
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);

        $group = $this->buildGroup($scheduler);

        self::assertSame($child, $group->go(static fn (): string => 'result'));
    }

    public function testGoAfterCloseThrows(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($this->makeTask(TaskStatus::Created));

        $group = $this->buildGroup($scheduler);
        $group->close();

        $this->expectException(InvalidLifecycleTransitionException::class);

        $group->go(static function (): void {
        });
    }

    public function testCanTransitionCreatedToCancelled(): void
    {
        $group = $this->createGroup();

        $group->transitionTo(TaskStatus::Cancelled);

        self::assertSame(TaskStatus::Cancelled, $group->status());
    }

    public function testFailRecordsFirstFailureOnly(): void
    {
        $group = $this->createGroup();
        $first = new RuntimeException('first');
        $second = new RuntimeException('second');

        $group->fail($first);
        $group->fail($second);

        self::assertSame($first, $group->failure());
    }

    public function testFailureReturnsNullWhenNoFailure(): void
    {
        $group = $this->createGroup();

        self::assertNull($group->failure());
    }

    public function testStatusReflectsFailure(): void
    {
        $group = $this->createGroup();
        $group->fail(new RuntimeException('error'));

        self::assertSame(TaskStatus::Failed, $group->status());
    }

    public function testBackpressureErrorPolicyThrowsWhenMaxConcurrencyExceeded(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($this->makeTask(TaskStatus::Created));

        $group = $this->buildGroup($scheduler, 1, BackpressurePolicy::Error);

        $group->go(static fn (): string => 'first');

        $this->expectException(BackpressureExceededException::class);

        $group->go(static fn (): string => 'second');
    }

    public function testBackpressureDropPolicyReturnsNoOpChildWhenMaxConcurrencyExceeded(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $realChild = $this->makeTask(TaskStatus::Created);
        $noOpChild = $this->makeTask(TaskStatus::Succeeded);

        $scheduler->expects($this->exactly(2))->method('spawn')
            ->willReturnOnConsecutiveCalls($realChild, $noOpChild)
        ;

        $group = $this->buildGroup($scheduler, 1, BackpressurePolicy::Drop);

        $first = $group->go(static fn (): string => 'first');
        $second = $group->go(static fn (): string => 'second');

        self::assertSame($realChild, $first);
        self::assertSame($noOpChild, $second);
    }

    public function testBackpressureDefaultPolicyIsBlock(): void
    {
        $group = $this->createGroup();

        self::assertSame(BackpressurePolicy::Block, $group->backpressurePolicy());
    }

    public function testDestructorCancelsChildScopesInsteadOfCompleting(): void
    {
        $childScope = $this->createMock(CancellationScopeInterface::class);
        $childScope->expects($this->once())->method('cancel');

        $child = new TaskStub(
            new TaskId('child-1'),
            ExecutionId::fromString('exec-1'),
            TaskStatus::Created,
            $childScope,
        );

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);

        $group = $this->buildGroup($scheduler);
        $group->go(static fn (): string => 'result');

        unset($group);
    }

    private function makeTask(TaskStatus $status): TaskStub
    {
        return new TaskStub(
            new TaskId('child-1'),
            ExecutionId::fromString('exec-1'),
            $status,
            $this->createMock(CancellationScopeInterface::class),
        );
    }

    private function createGroup(): TaskGroup
    {
        return $this->buildGroup(
            $this->createMock(EngineSchedulerInterface::class),
        );
    }

    private function buildGroup(
        EngineSchedulerInterface $scheduler,
        int $maxConcurrency = 0,
        BackpressurePolicy $policy = BackpressurePolicy::Block,
    ): TaskGroup {
        return new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            $this->createMock(CancellationScopeInterface::class),
            $scheduler,
            $maxConcurrency,
            $policy,
        );
    }
}
