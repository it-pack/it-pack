<?php

declare(strict_types=1);

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
 *     runtime: \ItPack\AsyncIt\SDK\AsyncRuntime,
 *     scheduler: \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler,
 *     rootScope: \ItPack\AsyncIt\Contract\CancellationScopeInterface,
 *     clock: \ItPack\RunIt\Adapter\Common\SystemMonotonicClock,
 *     poller: \ItPack\AsyncIt\Application\Poller\SelectPoller,
 *     telemetry: \ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder,
 *     storage: \ItPack\AsyncIt\Application\Context\MemoryContextStorage,
 * }
 */
function makeReferenceRuntime(): array
{
    $verifierPoller = new \ItPack\AsyncIt\Application\Poller\SelectPoller();
    $storage = new \ItPack\AsyncIt\Application\Context\MemoryContextStorage();
    $clock = new \ItPack\RunIt\Adapter\Common\SystemMonotonicClock();
    $publisher = new \ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher();
    $telemetryRecorder = new \ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder();
    $telemetry = new \ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge(
        static fn (): \ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder => $telemetryRecorder,
    );
    $rootScope = new \ItPack\AsyncIt\Domain\CancellationScope();

    $executionId = \ItPack\RunIt\Domain\Execution\ExecutionId::fromString('example-exec');
    $sequenceAllocator = new \ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator();

    $scheduler = new \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler(
        new \ItPack\AsyncIt\Application\Factory\FiberFactory(),
        new \ItPack\AsyncIt\Application\Factory\TaskRecordFactory(),
        new \ItPack\AsyncIt\Application\Factory\TaskFactory(
            new \ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator(),
        ),
        new \ItPack\AsyncIt\Application\Factory\CancellationScopeFactory(),
        $clock,
        $executionId,
        $verifierPoller,
        $storage,
        new \ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory(),
        $publisher,
        new \ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory(
            $sequenceAllocator,
            $executionId,
        ),
        new \ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener($telemetry),
    );

    $schedulerFactory = new \ItPack\AsyncIt\Application\Factory\SchedulerFactory(
        new \ItPack\AsyncIt\Application\Factory\FiberFactory(),
        new \ItPack\AsyncIt\Application\Factory\TaskRecordFactory(),
        new \ItPack\AsyncIt\Application\Factory\TaskFactory(
            new \ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator(),
        ),
        new \ItPack\AsyncIt\Application\Factory\CancellationScopeFactory(),
        $clock,
        $verifierPoller,
        $storage,
        $publisher,
    );

    $taskGroupFactory = new \ItPack\AsyncIt\Application\Factory\TaskGroupFactory(
        new \ItPack\AsyncIt\Application\Factory\CancellationScopeFactory(),
    );

    $idGenerator = new \ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator();

    $capability = new \ItPack\RunIt\Contract\Application\EngineCapability(true, true, true);

    $channel = new \ItPack\AsyncIt\Domain\Channel\EmulatedChannel();

    $runtime = new \ItPack\AsyncIt\SDK\AsyncRuntime(
        $schedulerFactory,
        $taskGroupFactory,
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
function makeScope(string $discriminant): \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope
{
    return new \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope($discriminant);
}
