<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use PhpParser\Node;

/**
 * Port for classifying a detected literal by the role it plays in the surrounding
 * AST (constructor arg, method arg, array key/value, comparison, branch value,
 * enum backing value, numeric expression, ...). The default concrete adapter is
 * AST-based (RoleClassifier).
 */
interface RoleClassifierInterface
{
    public function classify(Node $node, ?Node $parent): LiteralRole;

    public function looksIdentifierLike(string $value): bool;
}
