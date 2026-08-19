<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\PublicationGuardTokenInterface;

/**
 * Opaque publication-guard scope token.
 *
 * Each entered guard scope returns a distinct token. The token is never reused,
 * so an unbalanced or mismatched leave is detectable by the guard.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class PublicationGuardToken implements PublicationGuardTokenInterface
{
    public function __construct(
        private string $id,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }
}
