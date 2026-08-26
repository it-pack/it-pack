<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Domain\Task;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Creates {@see Task} instances without binding callers to a concrete entity.
 *
 * Lives in the `Factory` namespace so construction happens here, never inside
 * schedulers or adapters (see `ForbidNewOutsideFactoryBuilderRule`).
 *
 * @see https://w3id.org/itpack/asyncit#Task
 */
interface TaskFactoryInterface
{
    public function create(ExecutionIdInterface $executionId, CancellationScopeInterface $scope): Task;
}
