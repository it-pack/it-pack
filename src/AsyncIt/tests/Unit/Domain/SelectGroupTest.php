<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\SelectGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\SelectGroup
 */
final class SelectGroupTest extends TestCase
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

    public function testAwaitReturnsWinningTaskAndResult(): void
    {
        $child1 = $this->makeTask(TaskStatus::Succeeded);
        $child2 = $this->makeTask(TaskStatus::Created);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $scheduler->method('result')->willReturn('first result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        $outcome = $group->await();

        self::assertArrayHasKey('task', $outcome);
        self::assertArrayHasKey('result', $outcome);
        self::assertSame($child1, $outcome['task']);
        self::assertSame('first result', $outcome['result']);
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
        $this->expectExceptionMessage('SelectGroup::await() may only be called once.');

        $group->await();
    }

    public function testEmptyGroupAwaitReturnsNullTask(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);

        $group = $this->buildGroup($scheduler);

        $outcome = $group->await();

        self::assertSame(['task' => null, 'result' => null], $outcome);
    }

    public function testAwaitUsesAwaitAnyWhenSchedulerSupportsIt(): void
    {
        $child = $this->makeTask(TaskStatus::Created);

        $scheduler = new AwaitAnySchedulerStub(
            spawnResult: $child,
            awaitAnyResult: $child->id()->value,
            resultValue: 'awaited result',
        );

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        $outcome = $group->await();

        self::assertSame($child, $outcome['task']);
        self::assertSame('awaited result', $outcome['result']);
        self::assertSame(TaskStatus::Succeeded, $group->status());
    }

    public function testAwaitFallsBackToSequentialAwaitWhenNoAwaitAny(): void
    {
        $child = $this->makeTask(TaskStatus::Created);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);
        $scheduler->method('await')->willReturn('sequential result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');

        $outcome = $group->await();

        self::assertSame($child, $outcome['task']);
        self::assertSame('sequential result', $outcome['result']);
        self::assertSame(TaskStatus::Succeeded, $group->status());
    }

    private function buildGroup(EngineSchedulerInterface $scheduler): SelectGroup
    {
        return new SelectGroup(
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
