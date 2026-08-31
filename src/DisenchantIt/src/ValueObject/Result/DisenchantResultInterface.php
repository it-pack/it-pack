<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Result;

use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Port for the result of a full DisenchantIt run over one source string,
 * including the SemConv (collect + alias) steps: the rewritten source and the
 * generated shared SemConv registry source.
 */
interface DisenchantResultInterface
{
    public string $code { get; }

    public string $semconvSource { get; }
}
