<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskGroupInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Domain\TaskGroup;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 */
final readonly class TaskGroupFactory implements TaskGroupFactoryInterface
{
    public function __construct(private CancellationScopeFactoryInterface $cancellationScopeFactory)
    {
    }

    public function create(
        TaskIdInterface $id,
        ExecutionIdInterface $executionId,
        EngineSchedulerInterface $scheduler,
    ): TaskGroupInterface {
        return new TaskGroup(
            $id,
            $executionId,
            $this->cancellationScopeFactory->create(),
            $scheduler,
        );
    }
}
