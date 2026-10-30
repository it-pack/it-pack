<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Assembles the reference {@see FiberTaskScheduler} for one execution scope.
 *
 * Every collaborator is injected; the only values the factory binds per call are
 * the RunIt {@see ExecutionScopeInterface} and {@see ExecutionIdInterface} the
 * engine wants attached around each resume (real per-fiber/per-coroutine context
 * storage, Assertion A).
 *
 * The {@see InMemorySequenceAllocator} is provided so the scheduler can stamp
 * every published artifact with a monotonic sequence number within the execution
 * (H.2.1 — structured occurrence publication).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SchedulerFactory implements SchedulerFactoryInterface
{
    public function __construct(
        private FiberFactoryInterface $fiberFactory,
        private TaskRecordFactoryInterface $taskRecordFactory,
        private TaskFactoryInterface $taskFactory,
        private CancellationScopeFactoryInterface $cancellationScopeFactory,
        private MonotonicClockInterface $clock,
        private PollerPortInterface $poller,
        private ContextStorageInterface $contextStorage,
        private ArtifactPublisherInterface $publisher,
    ) {
    }

    public function create(ExecutionScopeInterface $scope, ExecutionIdInterface $executionId): EngineSchedulerInterface
    {
        $sequenceAllocator = new InMemorySequenceAllocator();

        return new FiberTaskScheduler(
            $this->fiberFactory,
            $this->taskRecordFactory,
            $this->taskFactory,
            $this->cancellationScopeFactory,
            $this->clock,
            $executionId,
            $this->poller,
            $this->contextStorage,
            $this->createExecutionScopeFactory($scope),
            $this->publisher,
            new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId),
        );
    }

    private function createExecutionScopeFactory(ExecutionScopeInterface $scope): PerRunExecutionScopeFactory
    {
        return new PerRunExecutionScopeFactory($scope);
    }
}
