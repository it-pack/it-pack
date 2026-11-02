<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\AsyncIt\Domain\Task;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Builds a {@see Task} with a generated {@see TaskId} and the supplied owning
 * execution and cancellation scope.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class TaskFactory implements TaskFactoryInterface
{
    public function __construct(
        private TaskIdFactoryInterface $taskIdFactory,
    ) {
    }

    public function create(
        ExecutionIdInterface $executionId,
        CancellationScopeInterface $scope
    ): TaskInterface {
        return $this->createWithId(
            $this->taskIdFactory->create(),
            $executionId,
            $scope
        );
    }

    public function createWithId(
        TaskIdInterface $id,
        ExecutionIdInterface $executionId,
        CancellationScopeInterface $scope
    ): TaskInterface {
        return new Task($id, $executionId, $scope);
    }
}
