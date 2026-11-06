<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Mapper;

use ItPack\RunIt\Contract\Application\TimeoutMapperInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\TimeoutInterface;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\Timeout;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

/**
 * Maps an elapsed timeout to a failed execution result.
 *
 * The timeout limit and elapsed duration are preserved in the cause text so the failure
 * explains how long was allowed versus how long was measured.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DefaultTimeoutMapper implements TimeoutMapperInterface
{
    public function map(TimeoutInterface $timeout): ExecutionResultInterface
    {
        $cause = sprintf(
            '%s (limit %s, elapsed %s)',
            $timeout->cause(),
            $timeout->limit()->toString(),
            $timeout->elapsed()->toString(),
        );

        $description = $timeout->throwableDescription()
            ?? ThrowableDescription::create(Timeout::class, $cause, 0, previous: null, trace: null);

        return ExecutionFailure::of(ExecutionStatus::TimedOut, $description);
    }
}
