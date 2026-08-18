<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Factory;

use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Domain\Execution\Execution;

/**
 * Default creator of the immutable running execution record.
 *
 * It combines the validated definition, the generated identity, the start instant, and
 * the initial sequence into a running {@see Execution}. Identity allocation and timing
 * live at the call site; the factory only assembles the first artifact.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionFactory implements ExecutionFactoryInterface
{
    public function start(
        ExecutionDefinitionInterface $definition,
        ExecutionIdInterface $id,
        InstantInterface $startedAt,
        ArtifactSequenceInterface $sequence,
    ): ExecutionInterface {
        return Execution::start($id, $definition, $startedAt, $sequence);
    }

    public function create(
        ExecutionDefinitionInterface $definition,
        ExecutionIdInterface $id,
        InstantInterface $startedAt,
        ArtifactSequenceInterface $sequence,
    ): ExecutionInterface {
        return Execution::create($id, $definition, $startedAt, $sequence);
    }
}
