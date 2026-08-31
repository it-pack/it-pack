<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\SchedulerFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskFactoryInterface;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;

/**
 * AsyncIt's reference RunIt execution engine.
 *
 * It implements {@see ExecutionEngineInterface}: {@see self::run()} executes the
 * supplied operation inside the reference reactor and returns its value, while
 * {@see self::capability()} reports the negotiated {@see EngineCapabilityInterface}.
 * The engine attaches the caller's RunIt scope around each Fiber resume and
 * detaches it under `finally` (conformance Assertion A), so an interleaved task
 * never holds ambient context. Publication is fail-safe (recursion-guarded,
 * health-reported) and the clock is the system monotonic clock — both supplied
 * by RunIt first-party adapters through {@see AsyncRuntimeFactory}. The active
 * runtime adapter (Swoole/Swow/Revolt/reference) is negotiated from capabilities,
 * not runtime names; the scheduler, ports, and the {@see self::channel()}/
 * {@see self::telemetry()} bridges stay identical across every selection.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final readonly class AsyncRuntime implements ExecutionEngineInterface
{
    public function __construct(
        private SchedulerFactoryInterface         $schedulerFactory,
        private TaskFactoryInterface              $taskFactory,
        private CancellationScopeFactoryInterface $cancellationScopeFactory,
        private IdentifierGeneratorInterface      $idGenerator,
        private EngineCapability                 $capability,
        private TaskChannelInterface              $channel,
        private TelemetryBridgeInterface          $telemetry,
    ) {
    }

    /**
     * Discovers and assembles the runtime (negotiated adapter) from RunIt
     * first-party adapters and the selected native runtime adapter, returning a
     * ready-to-run {@see ExecutionEngineInterface}.
     */
    public static function discover(): self
    {
        return AsyncRuntimeFactory::create();
    }

    /**
     * The negotiated {@see TaskChannelInterface} (native where supported,
     * emulated otherwise).
     */
    public function channel(): TaskChannelInterface
    {
        return $this->channel;
    }

    /**
     * The lazily-materialised telemetry bridge.
     */
    public function telemetry(): TelemetryBridgeInterface
    {
        return $this->telemetry;
    }

    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        $task = $this->taskFactory->create(
            ExecutionId::fromString($this->idGenerator->generate()),
            $this->cancellationScopeFactory->create(),
        );

        return $this->schedulerFactory->create($scope)->dispatch($task, $operation);
    }

    public function capability(): EngineCapability
    {
        return $this->capability;
    }
}
