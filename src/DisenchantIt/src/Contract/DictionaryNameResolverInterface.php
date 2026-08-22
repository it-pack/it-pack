<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;

/**
 * Port that maps a detected literal to an EXPLICIT constant name from an
 * optional naming dictionary, bypassing the generated name. Returns null when
 * the dictionary has no entry for the literal, so the resolver falls back to
 * its generator. Implemented by {@see \ItPack\DisenchantIt\Adapter\DictionaryNameResolver}.
 */
interface DictionaryNameResolverInterface
{
    /**
     * @return string|null explicit constant name for $lit, or null to defer to
     *         the default generator.
     */
    public function resolve(LiteralInterface $lit): ?string;
}
