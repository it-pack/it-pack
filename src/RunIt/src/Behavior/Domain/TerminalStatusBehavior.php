<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

/**
 * Terminality shared by the execution and activity status enums.
 *
 * Both status sets treat `succeeded`, `failed`, `cancelled`, and `timed_out` as
 * terminal. Centralizing the comparison keeps the two enums in lock-step: a new
 * terminal status is added in exactly one place.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
trait TerminalStatusBehavior
{
    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled, self::TimedOut], strict: true);
    }
}
