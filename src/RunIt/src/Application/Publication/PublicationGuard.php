<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardTokenInterface;

/**
 * Single-path publication guard based on an active-depth counter.
 *
 * `enter()` throws {@see RecursivePublicationException} when publication is already
 * active; `leave()` always clears the current scope, even when an inner publisher
 * failed, so the guard can never be left stuck open. The guard is not a lock: it
 * coordinates only one execution path within one process.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PublicationGuard implements PublicationGuardInterface
{
    private int $depth = 0;

    private int $sequence = 0;

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function enter(): PublicationGuardTokenInterface
    {
        if ($this->depth > 0) {
            throw new RecursivePublicationException(
                'Recursive artifact publication detected on the active execution path.'
            );
        }

        $this->depth++;
        $this->sequence++;

        return new PublicationGuardToken(spl_object_id($this) . ':' . $this->sequence);
    }

    public function leave(PublicationGuardTokenInterface $token): void
    {
        if ($this->depth > 0) {
            $this->depth--;
        }
    }
}
