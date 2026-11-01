<?php

declare(strict_types=1);

namespace Tests\Functional\Core;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\AsyncIt\Domain\TaskResult;
use ItPack\RunIt\Domain\Shared\Duration;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskResult
 * @covers \ItPack\AsyncIt\Contract\TaskResultInterface
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class TaskResultTest extends TestCase
{
    public function testTaskResultSuccessFactory(): void
    {
        $result = TaskResult::success(
            'my value',
            TaskStatus::Succeeded,
            Duration::ofNanoseconds(500),
        );

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('my value', $result->value());
        self::assertNull($result->error());
        self::assertSame(TaskStatus::Succeeded, $result->status());
        self::assertSame(500, $result->duration()->nanoseconds());
    }

    public function testTaskResultFailureFactory(): void
    {
        $error = new RuntimeException('failed');

        $result = TaskResult::failure(
            $error,
            TaskStatus::Failed,
            Duration::ofNanoseconds(1000),
        );

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isFailure());
        self::assertNull($result->value());
        self::assertSame($error, $result->error());
        self::assertSame(TaskStatus::Failed, $result->status());
    }

    public function testTaskResultRejectsFailureStatusWithNullError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Success results require TaskStatus::Succeeded.');

        TaskResult::success(value: null, status: TaskStatus::Failed, duration: Duration::ofNanoseconds(100));
    }

    public function testTaskResultRejectsErrorWithNonTerminalStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failure results require a terminal status.');

        TaskResult::failure(new RuntimeException('err'), TaskStatus::Running, Duration::ofNanoseconds(100));
    }

    public function testTaskResultIsReadonly(): void
    {
        self::assertTrue(
            new ReflectionProperty(TaskResult::class, 'status')->isReadOnly(),
        );
    }

    public function testResultTypedReturnsTypedOutcome(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $task = $scheduler->spawn(static fn (): string => 'typed result');
        $scheduler->run();

        self::assertSame(TaskStatus::Succeeded, $task->status());

        $result = $scheduler->resultTyped($task);
        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('typed result', $result->value());
        self::assertNull($result->error());
        self::assertSame(TaskStatus::Succeeded, $result->status());
    }

    public function testResultTypedThrowsForNonTerminalTask(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $task = $scheduler->spawn(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(100));
        });

        $this->expectException(LogicException::class);
        $scheduler->resultTyped($task);
    }

    public function testResultTypedIdempotentOnTerminalTask(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $task = $scheduler->spawn(static fn (): int => 42);
        $scheduler->run();

        $first = $scheduler->resultTyped($task);
        $second = $scheduler->resultTyped($task);

        self::assertSame($first->value(), $second->value());
        self::assertSame($first->status(), $second->status());
    }

    public function testExplicitTaskIdUsedOnSpawn(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $explicitId = new TaskId('my-explicit-id');

        $task = $scheduler->spawn(static fn (): string => 'result', $explicitId);

        self::assertSame('my-explicit-id', $task->id()->value);
    }

    public function testNullResultIsValidSuccess(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $task = $scheduler->spawn(static function (): void {
        });
        $scheduler->run();

        $result = $scheduler->resultTyped($task);
        self::assertTrue($result->isSuccess());
        self::assertNull($result->value());
    }
}
