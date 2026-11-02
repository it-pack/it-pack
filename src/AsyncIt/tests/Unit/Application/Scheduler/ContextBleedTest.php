<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Scheduler;

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
use ItPack\AsyncIt\Application\Scheduler\TaskCancellationService;
use ItPack\AsyncIt\Application\Scheduler\TaskHealthService;
use ItPack\AsyncIt\Application\Scheduler\TaskIoAwaitService;
use ItPack\AsyncIt\Application\Scheduler\TaskLifecyclePublisher;
use ItPack\AsyncIt\Application\Scheduler\TaskTimerService;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * Conformance Assertion A — Context Isolation (negative test).
 *
 * Proves zero context bleed across suspension/resumption cycles: interleaved
 * concurrent tasks with distinct contexts each observe only their own context
 * after resume. A suspended task's context is detached; a resumed task's own
 * context is re-attached — never a sibling's.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/runit#Execution
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class ContextBleedTest extends TestCase
{
    public function testNoContextBleedAcrossInterleavedSuspendResume(): void
    {
        $storage = new MemoryContextStorage();
        $scheduler = $this->scheduler($storage);
        $observed = [];
        $taskIds = [];

        $scheduler->spawn(function () use ($scheduler, $storage, &$observed, &$taskIds): void {
            $current = $scheduler->currentTask();
            $taskIds['a'] = $current instanceof TaskInterface ? $current->id()->value : 'none';
            $observed['a_first'] = $this->currentDiscriminant($storage);
            $scheduler->awaitDelay(Duration::ofMilliseconds(5));
            $observed['a_after_resume'] = $this->currentDiscriminant($storage);
        });

        $scheduler->spawn(function () use ($storage, &$observed): void {
            $observed['b_while_a_suspended'] = $this->currentDiscriminant($storage);
        });

        $scheduler->spawn(function () use ($storage, &$observed): void {
            $observed['c_while_a_suspended'] = $this->currentDiscriminant($storage);
        });

        $scheduler->run();

        self::assertSame($taskIds['a'], $observed['a_first'], 'Task A must observe its own context first.');
        self::assertSame($taskIds['a'], $observed['a_after_resume'], 'Task A must observe its own context after resume.');
        self::assertNotSame($taskIds['a'], $observed['b_while_a_suspended'], 'Task B must not observe Task A context.');
        self::assertNotSame($taskIds['a'], $observed['c_while_a_suspended'], 'Task C must not observe Task A context.');
    }

    public function testNoContextBleedAcrossMultipleSuspendResumeCycles(): void
    {
        $storage = new MemoryContextStorage();
        $scheduler = $this->scheduler($storage);
        $observed = [];
        $known = [];

        $scheduler->spawn(function () use ($scheduler, $storage, &$observed, &$known): void {
            $known[] = $scheduler->currentTask()?->id()->value ?? 'none';
            for ($i = 0; $i < 3; $i++) {
                $observed[] = $this->currentDiscriminant($storage);
                $scheduler->awaitDelay(Duration::ofMilliseconds(5));
            }
        });

        $scheduler->spawn(function () use ($scheduler, $storage, &$observed, &$known): void {
            $known[] = $scheduler->currentTask()?->id()->value ?? 'none';
            for ($i = 0; $i < 3; $i++) {
                $observed[] = $this->currentDiscriminant($storage);
                $scheduler->awaitDelay(Duration::ofMilliseconds(5));
            }
        });

        $scheduler->run();

        foreach ($observed as $observation) {
            self::assertContains($observation, $known, 'Context bleed: a task observed a foreign context discriminant.');
        }
    }

    private function currentDiscriminant(ContextStorageInterface $storage): string
    {
        $scope = $storage->current();

        if (!$scope instanceof ReferenceExecutionScope) {
            return 'none';
        }

        return $scope->discriminant;
    }

    private function scheduler(ContextStorageInterface $storage): FiberTaskScheduler
    {
        $clock = new SystemMonotonicClock();
        $executionId = ExecutionId::fromString('exec-bleed');
        $sequenceAllocator = new InMemorySequenceAllocator();
        $poller = new PollerFactory()->create(new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
        ]));

        $lifecycleFactory = new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId);
        $timerService = new TaskTimerService($clock);
        $ioAwaitService = new TaskIoAwaitService($poller, $timerService);
        $lifecyclePublisher = new TaskLifecyclePublisher(new NullArtifactPublisher(), $lifecycleFactory, $clock);
        $cancellationService = new TaskCancellationService();
        $healthService = new TaskHealthService($poller, $clock);

        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(new TaskIdFactory(new RandomIdentifierGenerator())),
            new CancellationScopeFactory(),
            $clock,
            $executionId,
            $poller,
            $storage,
            new ReferenceExecutionScopeFactory(),
            $timerService,
            $ioAwaitService,
            $lifecyclePublisher,
            $cancellationService,
            $healthService,
        );
    }
}
