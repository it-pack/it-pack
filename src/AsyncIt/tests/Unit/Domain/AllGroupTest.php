<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\AllGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\AllGroup
 */
final class AllGroupTest extends TestCase
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

    public function testAwaitReturnsAllResultsInSpawnOrder(): void
    {
        $child1 = $this->makeTask(TaskStatus::Succeeded);
        $child2 = $this->makeTask(TaskStatus::Succeeded);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $scheduler->method('await')
            ->willReturnOnConsecutiveCalls('result1', 'result2')
        ;

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        self::assertSame(['result1', 'result2'], $group->await());
    }

    public function testAwaitAfterResolvedThrows(): void
    {
        $child = $this->makeTask(TaskStatus::Succeeded);
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);
        $scheduler->method('await')->willReturn('result');

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'x');
        $group->await();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AllGroup::await() may only be called once.');

        $group->await();
    }

    public function testEmptyGroupAwaitReturnsEmptyArray(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);

        $group = $this->buildGroup($scheduler);

        self::assertSame([], $group->await());
    }

    private function buildGroup(EngineSchedulerInterface $scheduler): AllGroup
    {
        return new AllGroup(
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
