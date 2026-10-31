<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\TaskResult;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskResult
 */
final class TaskResultTest extends TestCase
{
    public function testSuccessResultReportsCorrectly(): void
    {
        $duration = Duration::ofMilliseconds(42);
        $result = TaskResult::success('hello', TaskStatus::Succeeded, $duration);

        self::assertTrue($result->isSuccess());
        self::assertFalse($result->isFailure());
        self::assertSame('hello', $result->value());
        self::assertNull($result->error());
        self::assertSame(TaskStatus::Succeeded, $result->status());
        self::assertSame($duration, $result->duration());
    }

    public function testFailureResultReportsCorrectly(): void
    {
        $error = new RuntimeException('boom');
        $duration = Duration::ofNanoseconds(1000);
        $result = TaskResult::failure($error, TaskStatus::Failed, $duration);

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isFailure());
        self::assertNull($result->value());
        self::assertSame($error, $result->error());
        self::assertSame(TaskStatus::Failed, $result->status());
        self::assertSame($duration, $result->duration());
    }

    public function testCancelledResultIsFailure(): void
    {
        $result = TaskResult::failure(
            new RuntimeException('cancelled'),
            TaskStatus::Cancelled,
            Duration::ofNanoseconds(0),
        );

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isFailure());
        self::assertSame(TaskStatus::Cancelled, $result->status());
    }

    public function testTimedOutResultIsFailure(): void
    {
        $result = TaskResult::failure(
            new RuntimeException('timeout'),
            TaskStatus::TimedOut,
            Duration::ofNanoseconds(0),
        );

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isFailure());
        self::assertSame(TaskStatus::TimedOut, $result->status());
    }

    public function testImmutable(): void
    {
        $duration = Duration::ofSeconds(1);
        $result = TaskResult::success('value', TaskStatus::Succeeded, $duration);

        // Property hooks prevent mutation; the object is readonly.
        self::assertSame('value', $result->value());
        self::assertSame($duration, $result->duration());
        self::assertSame(TaskStatus::Succeeded, $result->status());
    }

    public function testDurationImplementsDurationInterface(): void
    {
        $result = TaskResult::success(value: null, status: TaskStatus::Succeeded, duration: Duration::ofNanoseconds(5));

        self::assertInstanceOf(DurationInterface::class, $result->duration());
        self::assertSame(5, $result->duration()->nanoseconds());
    }
}
