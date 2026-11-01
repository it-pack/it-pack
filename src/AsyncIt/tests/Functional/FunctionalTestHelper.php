<?php

declare(strict_types=1);

namespace Tests\Functional;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\CombinatorFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Factory\TaskStreamFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;

/**
 * Builds a reference (pure-PHP, SelectPoller-backed) FiberTaskScheduler and
 * AsyncRuntime for functional tests.
 *
 * Mirrors examples/bootstrap.php so functional tests exercise the same
 * deterministic reference runtime used by examples and conformance harnesses.
 */
final class FunctionalTestHelper
{
    public static function makeScheduler(
        ?MonotonicClockInterface $clock = null,
    ): FiberTaskScheduler {
        return new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CancellationScopeFactory(),
            $clock ?? new DeterministicMonotonicClock(),
            ExecutionId::fromString('functional-exec'),
            new SelectPoller(),
            new MemoryContextStorage(),
            new ReferenceExecutionScopeFactory(),
            new NullArtifactPublisher(),
            new SchedulerLifecycleArtifactFactory(
                new InMemorySequenceAllocator(),
                ExecutionId::fromString('functional-exec')
            ),
        );
    }

    public static function makeRuntime(): array
    {
        $fixture = require __DIR__ . '/../../examples/bootstrap.php';

        return $fixture();
    }

    public static function makeRuntimeWithClock(MonotonicClockInterface $clock): array
    {
        $scheduler = self::makeScheduler($clock);
        ExecutionId::fromString('functional-exec');
        $poller = new SelectPoller();
        $storage = new MemoryContextStorage();
        $publisher = new NullArtifactPublisher();
        $telemetryRecorder = new ArrayTelemetryRecorder();
        $telemetry = new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => $telemetryRecorder,
        );
        $rootScope = new ReferenceExecutionScope('functional-root');

        $schedulerFactory = new SchedulerFactory(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CancellationScopeFactory(),
            $clock,
            $poller,
            $storage,
            $publisher,
        );

        $cancellationScopeFactory = new CancellationScopeFactory();
        $combinatorFactory = new CombinatorFactory($cancellationScopeFactory);
        $taskGroupFactory = new TaskGroupFactory($cancellationScopeFactory);

        $idGenerator = new RandomIdentifierGenerator();
        $capability = new EngineCapability(supportsFibers: true, supportsCoroutines: true, concurrent: true);
        $channel = new EmulatedChannel();

        $runtime = new AsyncRuntime(
            $schedulerFactory,
            $taskGroupFactory,
            $combinatorFactory,
            new TaskStreamFactory(),
            $idGenerator,
            $capability,
            $channel,
            $telemetry,
        );

        return [
            'runtime' => $runtime,
            'scheduler' => $scheduler,
            'rootScope' => $rootScope,
            'clock' => $clock,
            'poller' => $poller,
            'telemetry' => $telemetryRecorder,
            'storage' => $storage,
        ];
    }
}
