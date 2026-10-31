<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Federation;

use ItPack\AsyncIt\Application\Factory\ExecutionScopeFactoryInterface;
use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

use function sprintf;

/**
 * Attaches a RunIt execution scope around task dispatch.
 *
 * Creates a scope via {@see ExecutionScopeFactoryInterface}, attaches it to
 * the {@see ContextStorageInterface} before invoking the next middleware, and
 * detaches it under {@see finally} afterwards. This mirrors the per-resume
 * context attachment performed by {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler}
 * and ensures middleware code runs inside an ambient RunIt context.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerMiddleware
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/runit#Context
 */
final readonly class ContextAttacher implements SchedulerMiddlewareInterface
{
    public function __construct(
        private ContextStorageInterface $contextStorage,
        private ExecutionScopeFactoryInterface $scopeFactory,
    ) {
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        $scope = $this->scopeFactory->create(sprintf('task-%s', $task->id()->value));
        $token = $this->contextStorage->attach($scope);

        try {
            return $next();
        } finally {
            $this->contextStorage->detach($token);
        }
    }
}
