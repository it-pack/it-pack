<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\AsyncIt\Domain\RaceGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\RaceGroup
 */
final class RaceGroupTest extends TestCase
{
    public function testStartsInCreatedStatus(): void
    {
        $group = $this->buildGroup(
            $this->createMock(EngineSchedulerInterface::class),
        );

        self::assertSame(TaskStatus::Created, $group->status());
    }

    public function testAddSpawnsChildAndReturnsTask(): void
    {
        $child = $this->makeTask(TaskStatus::Created);
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);

        $group = $this->buildGroup($scheduler);

        self::assertSame($child, $group->add(static fn (): string => 'result'));
    }

    public function testAwaitReturnsFirstCompletedChildResult(): void
    {
        $child1 = $this->makeTask(TaskStatus::Succeeded);
        $child2 = $this->makeTask(TaskStatus::Created);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $scheduler->method('result')->willReturn('first result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        self::assertSame('first result', $group->await());
    }

    public function testAwaitCancelsRemainingChildren(): void
    {
        $child1 = $this->makeTask(TaskStatus::Succeeded);
        $child2Scope = $this->createMock(CancellationScopeInterface::class);
        $child2Scope->expects($this->once())->method('cancel');

        $child2 = new TaskStub(
            new TaskId('child-2'),
            ExecutionId::fromString('exec-1'),
            TaskStatus::Created,
            $child2Scope,
        );

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $scheduler->method('result')->willReturn('first result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        $group->await();
    }

    public function testAwaitAfterResolvedThrows(): void
    {
        $child = $this->makeTask(TaskStatus::Succeeded);
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);
        $scheduler->method('result')->willReturn('result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'x');
        $group->await();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('RaceGroup::await() may only be called once.');

        $group->await();
    }

    public function testAddAfterAwaitThrows(): void
    {
        $child = $this->makeTask(TaskStatus::Created);
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'x');
        $group->await();

        $this->expectException(InvalidLifecycleTransitionException::class);
        $group->add(static fn (): string => 'y');
    }

    public function testEmptyGroupAwaitReturnsNull(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);

        $group = $this->buildGroup($scheduler);

        self::assertNull($group->await());
    }

    private function buildGroup(EngineSchedulerInterface $scheduler): RaceGroup
    {
        return new RaceGroup(
            new TaskId('group-1'),
            $scheduler,
            $this->createMock(CancellationScopeInterface::class),
            ExecutionId::fromString('exec-1'),
        );
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
}
