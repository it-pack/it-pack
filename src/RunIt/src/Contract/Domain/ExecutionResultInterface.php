<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the result metadata of a completed execution.
 *
 * A result carries the terminal status and, for failures, a safe description of
 * the throwable that ended the execution. The description never retains the
 * original exception object.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionResultInterface
{
    public function status(): ExecutionStatusInterface;

    /**
     * Returns the safe throwable description, or null for a successful result.
     */
    public function throwableDescription(): ?ThrowableDescriptionInterface;
}
