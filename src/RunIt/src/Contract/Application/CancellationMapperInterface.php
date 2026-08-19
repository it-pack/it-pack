<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\CancellationInterface;
use ItPack\RunIt\Contract\Domain\ExecutionResultInterface;

/**
 * Contract for mapping a cooperative cancellation to a failed execution result.
 *
 * RunIt does not forcibly interrupt arbitrary PHP code. A consumer (or adapter) that
 * detects cancellation builds a {@see CancellationInterface} and maps it to a failed
 * execution result carrying the cancellation cause, preserving any consumer-defined
 * throwable description.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CancellationMapperInterface
{
    public function map(CancellationInterface $cancellation): ExecutionResultInterface;
}
