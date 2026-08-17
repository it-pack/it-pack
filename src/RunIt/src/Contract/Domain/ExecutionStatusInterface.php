<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the lifecycle status of an execution.
 *
 * A status decides whether an execution can still transition. Terminal statuses
 * (`succeeded`, `failed`, `cancelled`, `timed_out`) cannot be left; the runtime
 * enforces the transition matrix in a later milestone.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionStatusInterface
{
    /**
     * Returns the stable, lower-case status name.
     */
    public function name(): string;

    /**
     * Returns whether this status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool;
}
