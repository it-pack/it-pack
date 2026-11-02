<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scheduler\TaskCancellationService;
use ItPack\AsyncIt\Application\Scheduler\TaskHealthService;
use ItPack\AsyncIt\Application\Scheduler\TaskIoAwaitService;
use ItPack\AsyncIt\Application\Scheduler\TaskLifecyclePublisher;
use ItPack\AsyncIt\Application\Scheduler\TaskTimerService;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class FiberTaskSchedulerFailureTest extends TestCase
{
    public function testFailurePathPublishesArtifactAndRethrows(): void
    {
        $scheduler = $this->createScheduler();
        $scheduler->submit(
            $this->createTask(),
            static function (): never {
                throw new RuntimeException('boom');
            },
        );

        $this->expectException(RuntimeException::class);
        $scheduler->run();
    }

    public function testResultReturnsPublishedResult(): void
    {
        $scheduler = $this->createScheduler();
        $task = $this->createTask();
        $scheduler->submit($task, static fn (): int => 99);

        $scheduler->run();

        self::assertSame(99, $scheduler->result($task));
    }

    public function testResultReturnsNullForUnknownTask(): void
    {
        $scheduler = $this->createScheduler();
        $scheduler->submit($this->createTask(), static function (): void {
        });
        $scheduler->run();

        $unknownTask = $this->createTask();
        self::assertNull($scheduler->result($unknownTask));
    }

    private function createScheduler(): FiberTaskScheduler
    {
        $executionId = ExecutionId::fromString('exec-1');
        $sequenceAllocator = new InMemorySequenceAllocator();
        $poller = new SelectPoller();
        $clock = new SystemMonotonicClock();

        $lifecycleFactory = new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId);
        $timerService = new TaskTimerService($clock);
        $ioAwaitService = new TaskIoAwaitService($poller, $timerService);
        $lifecyclePublisher = new TaskLifecyclePublisher(new NullArtifactPublisher(), $lifecycleFactory, $clock);
        $cancellationService = new TaskCancellationService();
        $healthService = new TaskHealthService($poller, $clock);

        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CancellationScopeFactory(),
            $clock,
            $executionId,
            $poller,
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            $timerService,
            $ioAwaitService,
            $lifecyclePublisher,
            $cancellationService,
            $healthService,
        );
    }

    private function createTask(): TaskInterface
    {
        $cancellation = new CancellationScopeFactory()->create();

        return new TaskFactory(
            new TaskIdFactory(new RandomIdentifierGenerator()),
        )->create(
            ExecutionId::fromString('test-exec'),
            $cancellation,
        );
    }
}
