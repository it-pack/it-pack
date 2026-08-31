<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\SemConv;

use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacementInterface;

/**
 * Port for rewriting a class's already-placed constant declaration so its value
 * aliases the SemConv interface's constant instead of the raw literal.
 * Concrete adapter: SemConvAliasRewriter.
 */
interface SemConvAliasRewriterInterface
{
    /**
     * @param list<ConstantPlacementInterface> $placements
     */
    public function rewrite(string $code, array $placements): string;
}
