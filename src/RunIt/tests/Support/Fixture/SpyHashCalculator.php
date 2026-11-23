<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;

/**
 * Hash calculator spy that records whether calculate() was invoked.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SpyHashCalculator implements HashCalculatorInterface
{
    public bool $called = false;

    public function calculate(string $content): string
    {
        $this->called = true;

        return hash('sha256', $content);
    }
}
