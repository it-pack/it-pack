<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\Exception\ConflictingCompletionException;
use ItPack\AsyncIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\AsyncIt\Domain\Task;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\Task
 */
final class TaskTest extends TestCase
{
    public function testStartsInCreatedStatus(): void
    {
        $task = $this->createTask();

        self::assertSame(TaskStatus::Created, $task->status());
    }

    public function testCanTransitionCreatedToRunning(): void
    {
        $task = $this->createTask();
        $task->transitionTo(TaskStatus::Running);

        self::assertSame(TaskStatus::Running, $task->status());
    }

    public function testCanTransitionRunningToTerminalStates(): void
    {
        foreach ([TaskStatus::Succeeded, TaskStatus::Failed, TaskStatus::Cancelled, TaskStatus::TimedOut] as $status) {
            $task = $this->createTask();
            $task->transitionTo(TaskStatus::Running);
            $task->transitionTo($status);

            self::assertSame($status, $task->status());
        }
    }

    public function testRepeatTransitionIsIdempotent(): void
    {
        $task = $this->createTask();
        $task->transitionTo(TaskStatus::Created);

        self::assertSame(TaskStatus::Created, $task->status());
    }

    public function testCompleteIsAliasForTransition(): void
    {
        $task = $this->createTask();
        $task->transitionTo(TaskStatus::Running);
        $task->complete(TaskStatus::Succeeded);

        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testCreatedCannotTransitionToSucceeded(): void
    {
        $task = $this->createTask();

        $this->expectException(InvalidLifecycleTransitionException::class);
        $task->transitionTo(TaskStatus::Succeeded);
    }

    public function testTerminalToNonTerminalThrowsInvalidTransition(): void
    {
        $task = $this->createTask();
        $task->transitionTo(TaskStatus::Running);
        $task->transitionTo(TaskStatus::Succeeded);

        $this->expectException(InvalidLifecycleTransitionException::class);
        $task->transitionTo(TaskStatus::Running);
    }

    public function testTerminalToDifferentTerminalThrowsConflict(): void
    {
        $task = $this->createTask();
        $task->transitionTo(TaskStatus::Running);
        $task->transitionTo(TaskStatus::Succeeded);

        $this->expectException(ConflictingCompletionException::class);
        $task->transitionTo(TaskStatus::Failed);
    }

    private function createTask(): Task
    {
        return new Task(
            new TaskId('task-1'),
            $this->createMock(ExecutionIdInterface::class),
            new CancellationScope(),
        );
    }
}
