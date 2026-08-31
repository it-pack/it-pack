<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Result;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Immutable result of a full DisenchantIt run: the rewritten source (constants
 * promoted + aliased to SemConv) and the generated shared SemConv registry.
 */
final readonly class DisenchantResult implements DisenchantResultInterface
{
    public function __construct(
        public string $code,
        public string $semconvSource,
    ) {
    }
}
