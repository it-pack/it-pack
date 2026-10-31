<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK;

use ItPack\AsyncIt\Application\Factory\SchedulerFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactoryInterface;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\CapabilityObserverInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\SchedulerHealthInterface;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use LogicException;

/**
 * AsyncIt's reference RunIt execution engine.
 *
 * It implements {@see ExecutionEngineInterface}: {@see self::run()} executes the
 * supplied operation inside the reference reactor and returns its value, while
 * {@see self::capability()} reports the negotiated {@see EngineCapability}.
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
 *   @api
 */
final class AsyncRuntime implements ExecutionEngineInterface, CapabilityObserverInterface
{
    private ?EngineSchedulerInterface $scheduler = null;

    public function __construct(
        private readonly SchedulerFactoryInterface $schedulerFactory,
        private readonly TaskGroupFactoryInterface $taskGroupFactory,
        private readonly IdentifierGeneratorInterface $idGenerator,
        private readonly EngineCapability $capability,
        private readonly TaskChannelInterface $channel,
        private readonly TelemetryBridgeInterface $telemetry,
    ) {
    }

    public function channel(): TaskChannelInterface
    {
        return $this->channel;
    }

    public function telemetry(): TelemetryBridgeInterface
    {
        return $this->telemetry;
    }

    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        $executionId = ExecutionId::fromString($this->idGenerator->generate());
        $scheduler = $this->schedulerFactory->create($scope, $executionId);
        $this->scheduler = $scheduler;

        $rootId = new TaskId($this->idGenerator->generate());
        $group = $this->taskGroupFactory->create($rootId, $executionId, $scheduler);
        $task = $group->go($operation);
        $group->close();

        try {
            $scheduler->run();

            return $scheduler->result($task);
        } finally {
            $this->scheduler = null;
        }
    }

    /**
     * Spawns a new task on the active scheduler.
     *
     * Must be called from within a running task (i.e. inside an operation
     * passed to {@see run()}). The spawned task is bound to the current
     * execution scope.
     *
     * @see https://w3id.org/itpack/asyncit#Task
     */
    public function spawn(callable $operation, ?TaskId $id = null): TaskInterface
    {
        if (!$this->scheduler instanceof EngineSchedulerInterface) {
            throw new LogicException('spawn() must be called within a running execution.');
        }

        return $this->scheduler->spawn($operation, $id);
    }

    /**
     * Awaits the result of a previously spawned task.
     *
     * Must be called from within a running task.
     *
     * @see https://w3id.org/itpack/asyncit#Awaitable
     */
    public function await(TaskInterface $task): mixed
    {
        if (!$this->scheduler instanceof EngineSchedulerInterface) {
            throw new LogicException('await() must be called within a running execution.');
        }

        return $this->scheduler->await($task);
    }

    public function awaitDelay(DurationInterface $duration): void
    {
        if (!$this->scheduler instanceof EngineSchedulerInterface) {
            throw new LogicException('awaitDelay() must be called within a running execution.');
        }

        $this->scheduler->awaitDelay($duration);
    }

    public function capability(): EngineCapability
    {
        return $this->capability;
    }

    public function health(): ?SchedulerHealthInterface
    {
        return $this->scheduler?->health();
    }

    /**
     * Reacts to observed capability changes from the runtime adapter.
     *
     * When the scheduler is active, it swaps the capability descriptor atomically,
     * incrementing the version. The next reactor tick picks up the new backend
     * (e.g., switches from native to emulated channels).
     */
    public function onCapabilitiesChanged(CapabilityDescriptorInterface $descriptor): void
    {
        $this->scheduler?->swapCapabilities($descriptor);
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
}
