<?php

declare(strict_types=1);

namespace Tests\Functional\Operational;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\GracefulShutdown;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\DeadlineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\GracefulShutdown
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class GracefulShutdownTest extends TestCase
{
    public function testGracefulShutdownCancelsRootScope(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $rootScope = new CancellationScope();

        $clock = new DeterministicMonotonicClock();
        $deadlineScheduler = new readonly class ($scheduler) implements DeadlineSchedulerInterface {
            public function __construct(private FiberTaskScheduler $scheduler)
            {
            }

            public function submit(TaskInterface $task, callable $operation): void
            {
                $this->scheduler->submit($task, $operation);
            }

            public function run(): void
            {
                $this->scheduler->run();
            }

            public function result(TaskInterface $task): mixed
            {
                return $this->scheduler->result($task);
            }

            public function runUntil(int $deadlineNanoseconds): void
            {
                $this->scheduler->runUntil($deadlineNanoseconds);
            }
        };

        $shutdown = new GracefulShutdown(
            $deadlineScheduler,
            $rootScope,
            $clock,
            Duration::ofSeconds(5),
        );

        self::assertFalse($rootScope->isCancelled());

        $shutdown->shutdown();

        self::assertTrue($rootScope->isCancelled());
        self::assertSame(CancellationReason::SystemShutdown, $rootScope->reason());
    }

    public function testGracefulShutdownIsIdempotent(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $rootScope = new CancellationScope();

        $clock = new DeterministicMonotonicClock();
        $deadlineScheduler = new readonly class ($scheduler) implements DeadlineSchedulerInterface {
            public function __construct(private FiberTaskScheduler $scheduler)
            {
            }

            public function submit(TaskInterface $task, callable $operation): void
            {
                $this->scheduler->submit($task, $operation);
            }

            public function run(): void
            {
                $this->scheduler->run();
            }

            public function result(TaskInterface $task): mixed
            {
                return $this->scheduler->result($task);
            }

            public function runUntil(int $deadlineNanoseconds): void
            {
                $this->scheduler->runUntil($deadlineNanoseconds);
            }
        };

        $shutdown = new GracefulShutdown(
            $deadlineScheduler,
            $rootScope,
            $clock,
            Duration::ofSeconds(5),
        );

        $shutdown->shutdown();
        $shutdown->shutdown();

        self::assertTrue($rootScope->isCancelled());
    }
}
