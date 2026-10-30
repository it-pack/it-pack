<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\AsyncIt\Domain\TaskGroup;
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
        $child = $this->createConfiguredMock(
            TaskInterface::class,
            ['status' => TaskStatus::Created],
        );
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($child);

        $group = $this->buildGroup($scheduler);

        self::assertSame($child, $group->go(static fn (): string => 'result'));
    }

    public function testGoAfterCloseThrows(): void
    {
        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->method('spawn')->willReturn($this->createMock(TaskInterface::class));

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

    private function createGroup(): TaskGroup
    {
        return $this->buildGroup(
            $this->createMock(EngineSchedulerInterface::class),
        );
    }

    private function buildGroup(EngineSchedulerInterface $scheduler): TaskGroup
    {
        return new TaskGroup(
            new TaskId('group-1'),
            ExecutionId::fromString('exec-1'),
            $this->createMock(CancellationScopeInterface::class),
            $scheduler,
        );
    }
}
