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
 * Creates {@see RaceGroup}, {@see AllGroup}, {@see AnyGroup}, and
 * {@see SelectGroup} instances bound to an execution and scheduler.
 *
 * Lives in the `Factory` namespace so construction happens here, never inside
 * schedulers or SDK code.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-race
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-all
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-group-strategy-any
 * @see https://w3id.org/itpack/asyncit-taxonomy#await-point-select
 */
final readonly class CombinatorFactory implements CombinatorFactoryInterface
{
    public function __construct(private CancellationScopeFactoryInterface $cancellationScopeFactory)
    {
    }

    public function createRace(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): RaceGroup {
        return new RaceGroup(
            $id,
            $scheduler,
            $this->cancellationScopeFactory->create(),
            $executionId,
        );
    }

    public function createAll(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): AllGroup {
        return new AllGroup(
            $id,
            $scheduler,
            $this->cancellationScopeFactory->create(),
            $executionId,
        );
    }

    public function createAny(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): AnyGroup {
        return new AnyGroup(
            $id,
            $scheduler,
            $this->cancellationScopeFactory->create(),
            $executionId,
        );
    }

    public function createSelect(
        TaskId $id,
        EngineSchedulerInterface $scheduler,
        ExecutionIdInterface $executionId,
    ): SelectGroup {
        return new SelectGroup(
            $id,
            $scheduler,
            $this->cancellationScopeFactory->create(),
            $executionId,
        );
    }
}
