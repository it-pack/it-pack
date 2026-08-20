<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Domain;

/**
 * Contract for the closed lifecycle status of a command execution.
 *
 * A status decides whether an execution can still transition. Terminal statuses
 * (`succeeded`, `failed`, `cancelled`) cannot be left; the executor enforces the
 * transition matrix.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CommandStatusInterface
{
    /**
     * Returns the stable, lower-case status name.
     */
    public function label(): string;

    /**
     * Returns whether this status is terminal (no further transitions allowed).
     */
    public function isTerminal(): bool;
}
