<?php

declare(strict_types=1);

namespace Tests\Functional\Operational;

use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\Scheduler\SchedulerHealth
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class SchedulerHealthTest extends TestCase
{
    public function testHealthSnapshotReportsZeroWhenIdle(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $health = $scheduler->health();

        self::assertTrue($health->isHealthy());
        self::assertSame(0, $health->pendingTaskCount());
        self::assertSame(0, $health->activeHandleCount());
    }

    public function testHealthSnapshotReportsPendingTasksAfterSpawn(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();

        $scheduler->spawn(static function () use ($scheduler): void {
            $scheduler->awaitDelay(Duration::ofMilliseconds(500));
        });

        $health = $scheduler->health();

        self::assertSame(1, $health->pendingTaskCount());
    }
}
