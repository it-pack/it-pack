<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
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
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    public function createAll(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    public function createAny(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    public function createSelect(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;
}
