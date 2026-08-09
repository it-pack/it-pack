<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use PhpParser\NodeTraverser;

/**
 * Port for building a nikic node traverser, pre-wired with the visitors the
 * detector always needs (e.g. the parent-connecting visitor that
 * RoleClassifier relies on). Concrete adapters live in the Factory namespace.
 */
interface TraverserFactoryInterface
{
    public function build(): NodeTraverser;
}
