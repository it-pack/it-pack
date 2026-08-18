<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;
use ItPack\RunIt\Contract\Domain\TimeoutInterface;

/**
 * Contract for mapping an elapsed timeout to a failed execution result.
 *
 * Timeouts are cooperative: a consumer (or adapter) that observes the allotted time
 * has elapsed builds a {@see TimeoutInterface} and maps it to a failed execution
 * result carrying the timeout limit, elapsed duration, and cause.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface TimeoutMapperInterface
{
    public function map(TimeoutInterface $timeout): ExecutionResultInterface;
}
