<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use PhpParser\Parser;

/**
 * Port for building a nikic AST parser. Concrete adapters live in the Factory
 * namespace; the rest of the system depends only on this port, never on
 * PhpParser directly.
 */
interface ParserFactoryInterface
{
    public function build(): Parser;
}
