<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use ItPack\DisenchantIt\ValueObject\Source\NamingContextInterface;
use PhpParser\Node;

/**
 * Port for reading the naming hints around a numeric literal's AST node during
 * the scan: the assigned variable / property / array-key name, any unit suffix
 * it carries, and a preceding line comment. Pure AST + source read — never
 * mutates or executes anything.
 */
interface NamingContextExtractorInterface
{
    public function extract(Node $node, int $line, string $code): NamingContextInterface;
}
