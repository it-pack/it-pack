<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;
use PhpParser\NodeVisitor;

/**
 * Port for the AST visitor that collects candidate string literals during a
 * scan. The concrete adapter extends nikic's NodeVisitorAbstract; only the
 * domain-facing method is part of this port — the framework lifecycle hooks
 * (enterNode/leaveNode/...) are owned by the visitor base contract, not here.
 */
interface MagicStringVisitorInterface extends NodeVisitor
{
    /**
     * @return list<StringLiteralInterface>
     */
    public function getFound(): array;
}
