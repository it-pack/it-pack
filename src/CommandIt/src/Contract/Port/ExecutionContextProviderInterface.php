<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

use ItPack\CommandIt\Contract\ContextEnricherInterface;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;

/**
 * Port that stores the ambient command context for the current call stack.
 *
 * Conventional synchronous PHP uses a last-in-first-out stack: entering a context
 * returns a token, and leaving with that exact token restores the previous
 * context. Fiber-aware adapters implement the same contract while keeping the
 * context isolated to their concurrency primitive. A runtime-aware selector
 * consults the {@see RuntimeContextDetectorInterface} to choose the matching
 * storage so the ambient context never leaks across Fibers or requests.
 *
 * The enrichment seam ({@see ContextEnricherInterface}) is inherited so middleware
 * and handlers can attach classified context through the copy-on-write contract.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionContextProviderInterface extends ContextEnricherInterface
{
    /**
     * Returns the context currently attached to this storage, or null when none.
     */
    public function current(): ?CommandContextInterface;

    /**
     * Enters a context and returns a token that must be used to leave it.
     */
    public function enter(CommandContextInterface $context): ContextTokenInterface;

    /**
     * Leaves the context represented by the token.
     *
     * The exact token returned by {@see self::enter()} must be supplied; any
     * other token is rejected so a detached or replaced context is never restored
     * by mistake.
     */
    public function leave(ContextTokenInterface $token): void;
}
