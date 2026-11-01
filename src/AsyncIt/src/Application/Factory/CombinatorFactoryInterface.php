<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Domain\AllGroup;
use ItPack\AsyncIt\Domain\AnyGroup;
use ItPack\AsyncIt\Domain\RaceGroup;
use ItPack\AsyncIt\Domain\SelectGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Creates structured-concurrency combinator instances bound to an execution
 * and scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 */
interface CombinatorFactoryInterface
{
    public function createRace(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): RaceGroup;

    public function createAll(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): AllGroup;

    public function createAny(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): AnyGroup;

    public function createSelect(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): SelectGroup;
}
