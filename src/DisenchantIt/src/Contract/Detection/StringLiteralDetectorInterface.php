<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * String-specific detector port. Retained (pre-1.0, BC) as a subtype of
 * LiteralDetectorInterface; the unified scanner returns a list of the broader
 * LiteralInterface, so callers wanting only strings should filter by kind.
 */
interface StringLiteralDetectorInterface extends LiteralDetectorInterface
{
    /**
     * Maximum accepted source size in bytes. A single source larger than this
     * is rejected before parsing to bound memory use of the parse+traverse step.
     * Overridable via the detector constructor for callers that know their
     * inputs are legitimately large.
     */
    public const int MAX_SOURCE_BYTES = 1_000_000;
}
