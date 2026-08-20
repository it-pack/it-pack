<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * String-specific visitor port, retained (pre-1.0, BC) as a subtype of
 * MagicLiteralVisitorInterface. The unified visitor collects both strings and
 * numbers; callers that only want strings filter by kind.
 */
interface MagicStringVisitorInterface extends MagicLiteralVisitorInterface
{
}
