<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;

/**
 * Contract for creating the immutable running execution record.
 *
 * The factory converts a validated definition, a freshly generated identity, a start
 * instant, and the initial sequence into a running {@see ExecutionInterface}. It is
 * the single creation point for the first execution artifact, which keeps identity
 * allocation and timing policy in one place.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionFactoryInterface
{
    public function start(
        ExecutionDefinitionInterface $definition,
        ExecutionIdInterface $id,
        InstantInterface $startedAt,
        ArtifactSequenceInterface $sequence,
    ): ExecutionInterface;

    /**
     * Creates the immutable execution record in the `created` state.
     *
     * The runtime moves it to `running` through the lifecycle coordinator before
     * invoking the consumer operation.
     */
    public function create(
        ExecutionDefinitionInterface $definition,
        ExecutionIdInterface $id,
        InstantInterface $startedAt,
        ArtifactSequenceInterface $sequence,
    ): ExecutionInterface;
}
