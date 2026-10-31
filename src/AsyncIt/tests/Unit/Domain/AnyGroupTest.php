<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\AnyGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Domain\AnyGroup
 */
final class AnyGroupTest extends TestCase
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

    public function testAwaitReturnsFirstSuccess(): void
    {
        $child1 = $this->makeTask(TaskStatus::Failed);
        $child2 = $this->makeTask(TaskStatus::Succeeded);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $scheduler->method('await')
            ->willThrowException(new RuntimeException('first failed'))
            ->willReturn('second result')
        ;

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        self::assertSame('second result', $group->await());
    }

    public function testAwaitThrowsWhenAllFail(): void
    {
        $child1 = $this->makeTask(TaskStatus::Failed);
        $child2 = $this->makeTask(TaskStatus::Failed);

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturnOnConsecutiveCalls($child1, $child2);
        $failure = new RuntimeException('all failed');
        $scheduler->method('await')->willThrowException($failure);

        $group = $this->buildGroup($scheduler);
        $group->add(static fn (): string => 'first');
        $group->add(static fn (): string => 'second');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('all failed');

        $group->await();
    }

    public function testEmptyGroupAwaitThrows(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);

        $group = $this->buildGroup($scheduler);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AnyGroup: all children failed.');

        $group->await();
    }

    private function buildGroup(EngineSchedulerInterface $scheduler): AnyGroup
    {
        return new AnyGroup(
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
