<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * String-specific detector port. Retained (pre-1.0, BC) as a subtype of
 * LiteralDetectorInterface; the unified scanner returns a list of the broader
 * LiteralInterface, so callers wanting only strings should filter by kind.
 */
interface StringLiteralDetectorInterface extends LiteralDetectorInterface
{
}
