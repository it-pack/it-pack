<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Source;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Immutable carrier for one PHP source string fed through DisenchantIt. Serves
 * as the cache key for the parsed-scan result: a single SourceCode instance is
 * threaded through detect → rewrite → SemConv within one operation so the
 * (expensive) parse+traverse happens at most once per distinct source.
 */
interface SourceCodeInterface
{
    public string $code { get; }

    /**
     * Stable, content-derived identity used for equality checks and debugging.
     * Two SourceCode instances with identical code share the same identity.
     */
    public function identity(): string;
}
