<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Assembles the reference {@see FiberTaskScheduler} for one execution scope.
 *
 * Every collaborator is injected; the only value the factory binds per call is
 * the RunIt {@see ExecutionScopeInterface} the engine wants attached around each
 * resume (real per-fiber/per-coroutine context storage, Assertion A).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SchedulerFactory implements SchedulerFactoryInterface
{
    public function __construct(
        private FiberFactoryInterface $fiberFactory,
        private TaskRecordFactoryInterface $taskRecordFactory,
        private PollerPortInterface $poller,
        private ContextStorageInterface $contextStorage,
        private ArtifactPublisherInterface $publisher,
    ) {
    }

    public function create(ExecutionScopeInterface $scope): EngineSchedulerInterface
    {
        return new FiberTaskScheduler(
            $this->fiberFactory,
            $this->taskRecordFactory,
            $this->poller,
            $this->contextStorage,
            new PerRunExecutionScopeFactory($scope),
            $this->publisher,
        );
    }
}
