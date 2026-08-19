<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Opaque token returned when entering a publication guard.
 *
 * The token proves a publish call owns the current guard scope. Leaving the guard
 * requires the same token, so an unbalanced or mismatched leave is detectable. The
 * token carries no behavior beyond identity and is never reused.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PublicationGuardTokenInterface
{
    /**
     * Returns a stable unique identifier for this guard scope.
     */
    public function id(): string;
}
