<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Naming;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Source\NamingContext;
use ItPack\DisenchantIt\ValueObject\Source\NamingContextInterface;

/**
 * Port for naming a numeric literal from its NamingContext. Returns null when
 * no name can be derived (callers treat null as "do not promote"). Concrete
 * adapter: NumericNameGenerator.
 */
interface NumericNameGeneratorInterface
{
    public function generate(LiteralInterface $lit, ?NamingContextInterface $ctx): ?string;
}
