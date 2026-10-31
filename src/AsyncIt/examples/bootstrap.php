<?php

declare(strict_types=1);

use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\CombinatorFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener;
use ItPack\AsyncIt\Application\Factory\SchedulerFactory;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactory;
use ItPack\AsyncIt\Application\Factory\TaskStreamFactory;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;

/**
 * Bootstrap helper for AsyncIt examples.
 *
 * Constructs a reference (pure-PHP, SelectPoller-backed) AsyncRuntime so every
 * example runs identically regardless of which async extensions (Swoole, Swow,
 * Revolt) happen to be installed. The reference runtime uses the same
 * FiberTaskScheduler, EmulatedChannel, and MemoryContextStorage as the conformance
 * harness, guaranteeing deterministic behavior.
 *
 * Examples requiring direct access to internals (scheduler, poller, clock,
 * telemetry recorder, root cancellation scope) can read them from the returned
 * array.
 *
 * @return array{
 *     runtime: AsyncRuntime,
 *     scheduler: FiberTaskScheduler,
 *     rootScope: CancellationScopeInterface,
 *     clock: SystemMonotonicClock,
 *     poller: SelectPoller,
 *     telemetry: ArrayTelemetryRecorder,
 *     storage: MemoryContextStorage,
 * }
 */
function makeReferenceRuntime(): array
{
    $verifierPoller = new SelectPoller();
    $storage = new MemoryContextStorage();
    $clock = new SystemMonotonicClock();
    $publisher = new NullArtifactPublisher();
    $telemetryRecorder = new ArrayTelemetryRecorder();
    $telemetry = new LazyTelemetryBridge(
        static fn (): ArrayTelemetryRecorder => $telemetryRecorder,
    );
    $rootScope = new CancellationScope();

    $executionId = ExecutionId::fromString('example-exec');
    $sequenceAllocator = new InMemorySequenceAllocator();

    $scheduler = new FiberTaskScheduler(
        new FiberFactory(),
        new TaskRecordFactory(),
        new TaskFactory(
            new RandomIdentifierGenerator(),
        ),
        new CancellationScopeFactory(),
        $clock,
        $executionId,
        $verifierPoller,
        $storage,
        new ReferenceExecutionScopeFactory(),
        $publisher,
        new SchedulerLifecycleArtifactFactory(
            $sequenceAllocator,
            $executionId,
        ),
        new TelemetryBridgeEventListener($telemetry),
    );

    $schedulerFactory = new SchedulerFactory(
        new FiberFactory(),
        new TaskRecordFactory(),
        new TaskFactory(
            new RandomIdentifierGenerator(),
        ),
        new CancellationScopeFactory(),
        $clock,
        $verifierPoller,
        $storage,
        $publisher,
    );

    $cancellationScopeFactory = new CancellationScopeFactory();
    $combinatorFactory = new CombinatorFactory($cancellationScopeFactory);
    $taskGroupFactory = new TaskGroupFactory(
        $cancellationScopeFactory,
    );

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
        'poller' => $verifierPoller,
        'telemetry' => $telemetryRecorder,
        'storage' => $storage,
    ];
}

/**
 * Builds a ReferenceExecutionScope for the given discriminant.
 */
function makeScope(string $discriminant): ReferenceExecutionScope
{
    return new ReferenceExecutionScope($discriminant);
}
