<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Contract for conformance checks of MonotonicClockInterface.
 *
 * @see https://w3id.org/itpack/runit#instant
 * @see https://w3id.org/itpack/runit#duration
 */
interface MonotonicClockVerifierInterface
{
    public function port(): string;

    public function verify(MonotonicClockInterface $clock): ConformanceResultInterface;
}
