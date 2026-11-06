<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Mapper;

use ItPack\RunIt\Contract\Application\CancellationMapperInterface;
use ItPack\RunIt\Contract\Domain\CancellationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Domain\Execution\Cancellation;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

/**
 * Maps a cooperative cancellation to a failed execution result.
 *
 * The consumer-defined cause is preserved verbatim; if a throwable description was supplied it
 * is used, otherwise one is derived from the cause so the failure explains why execution
 * stopped.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DefaultCancellationMapper implements CancellationMapperInterface
{
    public function map(CancellationInterface $cancellation): ExecutionResultInterface
    {
        $description = $cancellation->throwableDescription()
            ?? ThrowableDescription::create(Cancellation::class, $cancellation->cause(), 0, previous: null, trace: null);

        return ExecutionFailure::of(ExecutionStatus::Cancelled, $description);
    }
}
