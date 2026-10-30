<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskGroupInterface;
use ItPack\AsyncIt\Contract\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Creates {@see TaskGroupInterface} instances bound to an execution and scheduler.
 *
 * Lives in the `Factory` namespace so construction happens here, never inside
 * schedulers or adapters.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 */
interface TaskGroupFactoryInterface
{
    public function create(
        TaskId $id,
        ExecutionIdInterface $executionId,
        EngineSchedulerInterface $scheduler,
    ): TaskGroupInterface;
}
