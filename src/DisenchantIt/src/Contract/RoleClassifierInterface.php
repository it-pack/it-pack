<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;

/**
 * Port for classifying a detected string literal by the role it plays in the surrounding
 * AST (constructor arg, method arg, array key/value, comparison, branch value,
 * enum backing value, ...). The default concrete adapter is AST-based (RoleClassifier).
 */
interface RoleClassifierInterface
{
    public function classify(String_ $node, ?Node $parent): string;

    public function looksIdentifierLike(string $value): bool;
}
