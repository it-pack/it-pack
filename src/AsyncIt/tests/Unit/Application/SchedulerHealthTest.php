<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Scheduler\TaskRecord;
use ItPack\AsyncIt\Application\SchedulerHealth;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Application\SchedulerHealth
 */
final class SchedulerHealthTest extends TestCase
{
    public function testIsHealthyReturnsTrueWhenNoHandlesAndNoErrors(): void
    {
        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, []);

        self::assertTrue($health->isHealthy());
    }

    public function testIsHealthyReturnsFalseWhenHandlesPresent(): void
    {
        $health = SchedulerHealth::capture([], 1, new DeterministicMonotonicClock(), 0, []);

        self::assertFalse($health->isHealthy());
    }

    public function testIsHealthyReturnsFalseWhenErrorsPresent(): void
    {
        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, [
            new RuntimeException('error'),
        ]);

        self::assertFalse($health->isHealthy());
    }

    public function testPendingTaskCountCountsIncompleteRecords(): void
    {
        $runningRecord = $this->createRecord(false);
        $doneRecord = $this->createRecord(true);

        $health = SchedulerHealth::capture(
            [$runningRecord, $doneRecord],
            0,
            new DeterministicMonotonicClock(),
            0,
            [],
        );

        self::assertSame(1, $health->pendingTaskCount());
    }

    public function testActiveHandleCountReturnsInjectedValue(): void
    {
        $health = SchedulerHealth::capture([], 3, new DeterministicMonotonicClock(), 0, []);

        self::assertSame(3, $health->activeHandleCount());
    }

    public function testUptimeReturnsDurationSinceStart(): void
    {
        $clock = $this->createMock(\ItPack\RunIt\Contract\Port\MonotonicClockInterface::class);
        $clock->method('monotonicNanoseconds')->willReturn(5_000_000_000);

        $health = SchedulerHealth::capture([], 0, $clock, 1_000_000_000, []);

        self::assertSame(4_000_000_000, $health->uptime()->nanoseconds());
    }

    public function testRecentErrorsReturnsLastNEntries(): void
    {
        $errors = [
            new RuntimeException('first'),
            new RuntimeException('second'),
            new RuntimeException('third'),
            new RuntimeException('fourth'),
        ];

        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, $errors);

        $recent = $health->recentErrors(2);

        self::assertCount(2, $recent);
        self::assertSame('third', $recent[0]->getMessage());
        self::assertSame('fourth', $recent[1]->getMessage());
    }

    public function testRecentErrorsReturnsAllWhenNExceedsCount(): void
    {
        $errors = [new RuntimeException('only')];

        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, $errors);

        $recent = $health->recentErrors(10);

        self::assertCount(1, $recent);
    }

    public function testRecentErrorsReturnsEmptyWhenNoErrors(): void
    {
        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, []);

        $recent = $health->recentErrors(5);

        self::assertSame([], $recent);
    }

    public function testEmptyRecordsReturnsZeroPending(): void
    {
        $health = SchedulerHealth::capture([], 0, new DeterministicMonotonicClock(), 0, []);

        self::assertSame(0, $health->pendingTaskCount());
    }

    private function createRecord(bool $done): TaskRecord
    {
        $task = $this->createMock(\ItPack\AsyncIt\Contract\TaskInterface::class);
        $fiber = new \Fiber(static function (): void {
        });

        $record = new TaskRecord($task, $fiber);

        if ($done) {
            $record->markDone();
        }

        return $record;
    }
}
