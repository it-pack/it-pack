<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\SchedulerFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskFactoryInterface;
use ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
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
 * by RunIt first-party adapters through {@see AsyncRuntimeFactory}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class AsyncRuntime implements ExecutionEngineInterface
{
    public function __construct(
        private SchedulerFactoryInterface         $schedulerFactory,
        private TaskFactoryInterface              $taskFactory,
        private CancellationScopeFactoryInterface $cancellationScopeFactory,
        private IdentifierGeneratorInterface      $idGenerator,
        private EngineCapabilityInterface         $capability,
    ) {
    }

    /**
     * Discovers and assembles the reference runtime from RunIt first-party
     * adapters, returning a ready-to-run {@see ExecutionEngineInterface}.
     */
    public static function discover(): self
    {
        return AsyncRuntimeFactory::create();
    }

    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        $task = $this->taskFactory->create(
            ExecutionId::fromString($this->idGenerator->generate()),
            $this->cancellationScopeFactory->create(),
        );

        return $this->schedulerFactory->create($scope)->dispatch($task, $operation);
    }

    public function capability(): EngineCapabilityInterface
    {
        return $this->capability;
    }
}
