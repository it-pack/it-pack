<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use PhpParser\NodeVisitor;

/**
 * Port for the AST visitor that collects candidate literals (strings and
 * numbers) during a scan. The concrete adapter extends nikic's
 * NodeVisitorAbstract; only the domain-facing method is part of this port — the
 * framework lifecycle hooks (enterNode/leaveNode/...) are owned by the visitor
 * base contract, not here.
 */
interface MagicLiteralVisitorInterface extends NodeVisitor
{
    /**
     * @return list<LiteralInterface>
     */
    public function getFound(): array;
}
