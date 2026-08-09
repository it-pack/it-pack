<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Factory;

use ItPack\DisenchantIt\Contract\TraverserFactoryInterface;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

final readonly class NikicTraverserFactory implements TraverserFactoryInterface
{
    public function build(): NodeTraverser
    {
        $traverser = new NodeTraverser();
        // Supplies getAttribute('parent') for every node, which RoleClassifier
        // relies on when classifying a literal.
        $traverser->addVisitor(new ParentConnectingVisitor());

        return $traverser;
    }

    public static function create(): self
    {
        return new self();
    }
}
