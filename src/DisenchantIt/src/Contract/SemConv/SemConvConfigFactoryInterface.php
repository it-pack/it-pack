<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\SemConv;

use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfigInterface;

/**
 * Port for building the SemConv generation config. The single construction
 * site for SemConvConfig — consumers receive a SemConvConfigInterface and never
 * `new` it themselves, so tests can inject a custom config without touching
 * the collector/rewriter.
 */
interface SemConvConfigFactoryInterface
{
    public function build(
        ?string $interfaceName = null,
        ?string $namespace = null,
        ?string $outputPath = null,
    ): SemConvConfigInterface;
}
