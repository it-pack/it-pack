<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Result;

use Stringable;

/**
 * A single conformance finding surface produced by a verifier.
 *
 * Backs {@see ConformanceViolation} so violations remain mockable and the
 * conformance result stays interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceViolation
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
interface ConformanceViolationInterface extends Stringable
{
    /**
     * Whether this violation is an error (as opposed to a warning).
     */
    public function isError(): bool;
}
