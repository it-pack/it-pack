<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the lifecycle status of an execution.
 *
 * A status decides whether an execution can still transition. Terminal statuses
 * (`succeeded`, `failed`, `cancelled`, `timed_out`) cannot be left; the closed
 * transition matrix is owned by the domain and enforced by the aggregate
 * itself, with the application-level lifecycle coordinator applying the same
 * rules.
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
    public function label(): string;

    /**
     * Returns whether this status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool;

    /**
     * Returns whether this non-terminal status may legally move to `$to`.
     *
     * Terminal statuses never transition (not even to themselves — idempotent
     * re-completion is handled above the aggregate). Implementations share one
     * closed matrix so the aggregate and the coordinator enforce identical
     * rules.
     */
    public function canTransitionTo(ExecutionStatusInterface $to): bool;
}
