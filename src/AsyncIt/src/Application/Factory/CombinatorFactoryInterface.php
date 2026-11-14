<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CombinatorInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskIdInterface;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;

/**
 * Creates structured-concurrency combinator instances bound to an execution
 * and scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 */
interface CombinatorFactoryInterface
{
    /** @return CombinatorInterface<mixed> */
    public function createRace(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    /** @return CombinatorInterface<list<mixed>> */
    public function createAll(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    /** @return CombinatorInterface<mixed> */
    public function createAny(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;

    /** @return CombinatorInterface<array{task: TaskInterface|null, result: mixed}> */
    public function createSelect(
        TaskIdInterface $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): CombinatorInterface;
}
