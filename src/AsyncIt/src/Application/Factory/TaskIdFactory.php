<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Domain\Task;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Builds a {@see Task} with a generated {@see TaskId} and the supplied owning
 * execution and cancellation scope.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class TaskIdFactory implements TaskIdFactoryInterface
{
    public function __construct(private IdentifierGeneratorInterface $idGenerator)
    {
    }

    public function create(): TaskIdInterface
    {
        return $this->createWithId(
            $this->idGenerator->generate()
        );
    }

    public function createWithId(
        string $id,
    ): TaskIdInterface {
        return new TaskId($id);
    }
}
