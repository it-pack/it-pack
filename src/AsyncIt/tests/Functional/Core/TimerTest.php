<?php

declare(strict_types=1);

namespace Tests\Functional\Core;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class TimerTest extends TestCase
{
    public function testAwaitDelayResumesTaskAfterDeadline(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler(new SystemMonotonicClock());
        $log = [];

        $scheduler->spawn(static function () use ($scheduler, &$log): void {
            $log[] = 'before';
            $scheduler->awaitDelay(Duration::ofMilliseconds(10));
            $log[] = 'after';
        });

        $scheduler->run();

        self::assertSame(['before', 'after'], $log);
    }

    public function testAwaitDelayUsesMonotonicClock(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler(new SystemMonotonicClock());

        $resumed = false;
        $scheduler->spawn(static function () use ($scheduler, &$resumed): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(100));
            $resumed = true;
        });

        $deadline = hrtime(as_number: true) + Duration::ofMilliseconds(5)->nanoseconds();
        $scheduler->runUntil($deadline);

        self::assertFalse($resumed);
    }

    public function testTimerClearedOnTaskCompletion(): void
    {
        $clock = new DeterministicMonotonicClock();
        $scheduler = FunctionalTestHelper::makeScheduler($clock);

        $resumed = false;
        $task = $scheduler->spawn(static function () use ($scheduler, &$resumed): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(1));
            $resumed = true;
        });

        $task->cancellationScope()->cancel(CancellationReason::ParentCancelled);

        $scheduler->run();

        self::assertSame(TaskStatus::Cancelled, $task->status());
        self::assertFalse($resumed);
    }
}
