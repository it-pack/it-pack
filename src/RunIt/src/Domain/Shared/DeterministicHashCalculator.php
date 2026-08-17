<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;

/**
 * Deterministic SHA-256 content hash calculator.
 *
 * The adapter is dependency-free: it hashes the canonical string it is given. The domain
 * depends only on `HashCalculatorInterface`, so the algorithm can be swapped without touching
 * snapshot code.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class DeterministicHashCalculator implements HashCalculatorInterface
{
    public function calculate(string $content): string
    {
        return hash('sha256', $content);
    }
}
