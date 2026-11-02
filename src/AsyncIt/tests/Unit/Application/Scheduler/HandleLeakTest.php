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
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * Conformance Assertion C — Deterministic Cleanup (negative test).
 *
 * Proves every {@see \ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface}
 * is released on terminal transition: after task timeout, cancellation, and
 * force-cancellation of abandoned tasks, activeHandleCount() must return 0.
 * A single leaked descriptor is a conformance failure.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/runit#Execution
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class HandleLeakTest extends TestCase
{
    public function testHandlesReleasedAfterIoTimeout(): void
    {
        $poller = $this->poller();
        $scheduler = $this->scheduler($poller);
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $scheduler->spawn(function () use ($scheduler, $pair): void {
            $handle = $scheduler->registerStream($pair[0]);
            $scheduler->awaitReadable($handle, Duration::ofMilliseconds(5));
        });

        $scheduler->run();

        self::assertSame(0, $poller->activeHandleCount(), 'Poll handle leaked after IO timeout.');

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testHandlesReleasedAfterExternalCancellation(): void
    {
        $poller = $this->poller();
        $scheduler = $this->scheduler($poller);
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $task = $scheduler->spawn(function () use ($scheduler, $pair): void {
            $handle = $scheduler->registerStream($pair[0]);
            $scheduler->awaitReadable($handle);
        });

        $task->cancellationScope()->cancel();
        $scheduler->run();

        self::assertSame(0, $poller->activeHandleCount(), 'Poll handle leaked after external cancellation.');

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testHandlesReleasedWhenTaskNeverCompletes(): void
    {
        $poller = $this->poller();
        $scheduler = $this->scheduler($poller);
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        // Task registers a handle and awaits indefinitely — never completes.
        $scheduler->spawn(function () use ($scheduler, $pair): void {
            $handle = $scheduler->registerStream($pair[0]);
            $scheduler->awaitReadable($handle);
        });

        // Force-cancel via deadline expiry (abandoned task termination).
        $scheduler->runUntil(0);

        self::assertSame(0, $poller->activeHandleCount(), 'Poll handle leaked on abandoned task termination.');

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testNoHandlesWhenIdle(): void
    {
        $poller = $this->poller();
        $scheduler = $this->scheduler($poller);

        $scheduler->run();

        self::assertSame(0, $poller->activeHandleCount(), 'Idle scheduler must hold zero handles.');
    }

    private function poller(): PollerPortInterface
    {
        return new PollerFactory()->create(new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
        ]));
    }

    private function scheduler(PollerPortInterface $poller): FiberTaskScheduler
    {
        $clock = new SystemMonotonicClock();
        $executionId = ExecutionId::fromString('exec-leak');
        $sequenceAllocator = new InMemorySequenceAllocator();

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
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            $timerService,
            $ioAwaitService,
            $lifecyclePublisher,
            $cancellationService,
            $healthService,
        );
    }
}
