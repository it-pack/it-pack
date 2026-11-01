<?php

declare(strict_types=1);

namespace Tests\Integration;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\Exception\CancellationException;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests wiring scheduler + poller + cancellation scope together
 * without a full runtime adapter.
 *
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 * @covers \ItPack\AsyncIt\Application\Poller\SelectPoller
 * @covers \ItPack\AsyncIt\Domain\CancellationScope
 * @covers \ItPack\AsyncIt\Application\Factory\PollerFactory
 */
final class SchedulerIntegrationTest extends TestCase
{
    public function testTaskRunsToCompletionWithResult(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();

        $scheduler->submit($task, static fn (): string => 'integration result');

        $scheduler->run();

        self::assertSame(TaskStatus::Succeeded, $task->status());
        self::assertSame('integration result', $scheduler->result($task));
    }

    public function testAwaitDelayResumesAfterDeadline(): void
    {
        $scheduler = $this->scheduler();
        $task = $this->task();
        $log = [];

        $scheduler->submit($task, static function () use ($scheduler, &$log): void {
            $log[] = 'before';
            $scheduler->awaitDelay(Duration::ofNanoseconds(100));
            $log[] = 'after';
        });

        $scheduler->run();

        self::assertSame(['before', 'after'], $log);
        self::assertSame(TaskStatus::Succeeded, $task->status());
    }

    public function testConcurrentTasksAllComplete(): void
    {
        $scheduler = $this->scheduler();

        $taskA = $scheduler->spawn(static fn (): string => 'a');
        $taskB = $scheduler->spawn(static fn (): string => 'b');

        $scheduler->run();

        self::assertSame('a', $scheduler->result($taskA));
        self::assertSame('b', $scheduler->result($taskB));
        self::assertSame(TaskStatus::Succeeded, $taskA->status());
        self::assertSame(TaskStatus::Succeeded, $taskB->status());
    }

    public function testPollerRegisterReturnsValidHandle(): void
    {
        $scheduler = $this->scheduler();

        $stream = fopen('php://temp', 'rb+');
        self::assertNotFalse($stream);

        $handle = $scheduler->registerStream($stream);

        self::assertInstanceOf(StreamPollHandleInterface::class, $handle);
        self::assertNotSame('', (string) $handle);

        fclose($stream);
    }

    public function testCancellationScopeCancelAndThrowIfCancelled(): void
    {
        $scope = new CancellationScope();

        self::assertFalse($scope->isCancelled());

        $scope->cancel();

        self::assertTrue($scope->isCancelled());

        $this->expectException(CancellationException::class);
        $scope->throwIfCancelled();
    }

    private function scheduler(): FiberTaskScheduler
    {
        $executionId = ExecutionId::fromString('exec-integration-1');
        $sequenceAllocator = new InMemorySequenceAllocator();

        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CancellationScopeFactory(),
            new SystemMonotonicClock(),
            $executionId,
            new PollerFactory()->create(new CapabilityDescriptor([
                CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
            ])),
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            new NullArtifactPublisher(),
            new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId),
        );
    }

    private function task(): TaskInterface
    {
        return new TaskFactory(
            new TaskIdFactory(new RandomIdentifierGenerator()),
        )->create(
            ExecutionId::fromString('exec-integration-1'),
            new CancellationScopeFactory()->create(),
        );
    }
}
