<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Contract for a guard that prevents recursive publication on one execution path.
 *
 * A guarded publisher enters the guard before publishing and leaves it in a
 * `finally`. If publication is already active when `enter()` is called, the guard
 * throws a recursive-publication exception, which the guarded publisher routes to
 * the internal health reporter instead of publishing again.
 *
 * The guard is a single per-path scope: it is not a distributed lock and does not
 * coordinate across threads or processes.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PublicationGuardInterface
{
    /**
     * Returns whether publication is currently active on this path.
     */
    public function isActive(): bool;

    /**
     * Enters the guard, returning a scope token.
     *
     * @throws \ItPack\RunIt\Contract\Exception\RunItExceptionInterface when publication
     *        is already active (recursive publication detected)
     */
    public function enter(): PublicationGuardTokenInterface;

    /**
     * Leaves the guard scope identified by the token.
     */
    public function leave(PublicationGuardTokenInterface $token): void;
}
