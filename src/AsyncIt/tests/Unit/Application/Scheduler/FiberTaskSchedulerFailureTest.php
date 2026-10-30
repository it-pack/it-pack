<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\TaskInterface;
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
        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(new RandomIdentifierGenerator()),
            new CancellationScopeFactory(),
            new SystemMonotonicClock(),
            ExecutionId::fromString('exec-1'),
            new SelectPoller(),
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            new NullArtifactPublisher(),
        );
    }

    private function createTask(): TaskInterface
    {
        $cancellation = new CancellationScopeFactory()->create();

        return new TaskFactory(new RandomIdentifierGenerator())->create(
            ExecutionId::fromString('test-exec'),
            $cancellation,
        );
    }
}
