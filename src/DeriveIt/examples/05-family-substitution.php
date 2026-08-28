<?php

declare(strict_types=1);

/*
 * 05 — Family derivation with type substitution.
 *
 * Derive a set of related classes together so that types referencing one another
 * are rewritten to the corresponding derived interface. DeriveIt::deriveFamily()
 * auto-detects the cross references, so Node's `?Node` and Tree's `Node` both
 * point at `NodeInterface` instead of the concrete class.
 *
 * To emit a delegating adapter per member as well (auto-wired), see example 12.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$sources = DeriveIt::deriveFamily([
    _Fixture\Node::class,
    _Fixture\Tree::class,
]);

show(
    'Family: NodeInterface',
    $sources[_Fixture\Node::class],
    'getNext(): ?NodeInterface — the native Node return type was rewritten.',
);

show(
    'Family: TreeInterface',
    $sources[_Fixture\Tree::class],
    'getRoot(): NodeInterface and build(NodeInterface $seed): NodeInterface.',
);
