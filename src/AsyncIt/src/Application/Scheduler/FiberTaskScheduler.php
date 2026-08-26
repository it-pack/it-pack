<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use Fiber;
use ItPack\AsyncIt\Application\Artifact\SchedulerFailureArtifact;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Application\Factory\FiberFactoryInterface;
use ItPack\AsyncIt\Application\Factory\TaskFactoryInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use Throwable;

/**
 * Reference reactor implementing {@see SchedulerInterface}.
 *
 * Every collaborator is injected (factories, poller, clock, context storage,
 * cancellation scope, scope factory, publisher); the scheduler never
 * constructs them. The reactor attaches a RunIt execution scope before each
 * Fiber resume and detaches it under `finally` on suspend, so an interleaved
 * (suspended) task never holds an ambient context (conformance Assertion A).
 * On terminal transition it releases every registered poll handle (Assertion
 * C), and on throw it publishes through the recursion-guarded publisher and
 * rethrows the exact original {@see \Throwable}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberTaskScheduler implements EngineSchedulerInterface
{
    /**
     * @var array<string, TaskRecord>
     */
    private array $records = [];

    /**
     * @var array<string, mixed>
     */
    private array $results = [];

    public function __construct(
        private readonly TaskFactoryInterface $taskFactory,
        private readonly FiberFactoryInterface $fiberFactory,
        private readonly PollerPortInterface $poller,
        private readonly MonotonicClockInterface $clock,
        private readonly ContextStorageInterface $contextStorage,
        private readonly CancellationScopeFactoryInterface $cancellationScopeFactory,
        private readonly ExecutionScopeFactoryInterface $scopeFactory,
        private readonly ArtifactPublisherInterface $publisher,
    ) {
    }

    public function submit(TaskInterface $task, callable $operation): void
    {
        $fiber = $this->fiberFactory->create($operation);
        $this->records[$task->id()->value] = new TaskRecord($task, $fiber);
    }

    public function run(): void
    {
        while ($this->records !== []) {
            foreach ($this->records as $record) {
                if ($record->isDone()) {
                    continue;
                }

                $this->step($record);
            }

            $this->poller->await(0);
            $this->records = array_filter(
                $this->records,
                static fn (TaskRecord $record): bool => !$record->isDone(),
            );
        }
    }

    private function step(TaskRecord $record): void
    {
        $scope = $this->scopeFactory->create($record->task()->id()->value);
        $token = $this->contextStorage->attach($scope);

        try {
            $this->resume($record);
        } finally {
            $this->contextStorage->detach($token);
        }
    }

    /**
     * @throws Throwable
     */
    private function resume(TaskRecord $record): void
    {
        $fiber = $record->fiber();

        try {
            $record->task()->transitionTo(TaskStatus::Running);

            if ($fiber->isStarted()) {
                $fiber->resume();
            } else {
                $fiber->start();
            }

            if ($fiber->isTerminated()) {
                $this->results[$record->task()->id()->value] = $fiber->getReturn();
                $record->task()->complete(TaskStatus::Succeeded);
                $this->releaseHandles($record);
                $record->markDone();

                return;
            }
        } catch (Throwable $error) {
            $this->publisher->publish(new SchedulerFailureArtifact($error->getMessage()));
            $record->task()->complete(TaskStatus::Failed);
            $this->releaseHandles($record);
            $record->markDone();

            throw $error;
        }
    }

    private function releaseHandles(TaskRecord $record): void
    {
        foreach ($record->handles() as $handle) {
            $this->poller->release($handle);
        }

        $record->clearHandles();
    }

    public function dispatch(TaskInterface $task, callable $operation): mixed
    {
        $this->submit($task, $operation);
        $this->run();

        return $this->result($task);
    }

    public function result(TaskInterface $task): mixed
    {
        return $this->results[$task->id()->value] ?? null;
    }
}
