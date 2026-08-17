<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Port for calculating a deterministic content hash of a snapshot payload.
 *
 * The hash binds the snapshot to its normalized state so a store can detect
 * tampering and deduplicate identical state. The concrete algorithm is an adapter
 * concern; the domain depends only on this port, never on a specific hashing
 * extension or PSR namespace.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface HashCalculatorInterface
{
    /**
     * Returns a stable hash for the given canonical content.
     */
    public function calculate(string $content): string;
}
